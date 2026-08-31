<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OtSummaryController extends Controller
{
    /**
     * Get OT summary for current cycle (19th prev → 18th current)
     * GET /api/ot-summary
     * Query params: company_id (optional), month (optional, format: YYYY-MM)
     */
    public function index(Request $request)
    {
        $companyId = $request->get('company_id');
        $month = $request->get('month', Carbon::now()->format('Y-m'));

        // Calculate cycle dates
        $cycleEnd = Carbon::parse($month . '-18');
        $cycleStart = Carbon::parse($month . '-18')->subMonth();

        // If current date < 18th, use previous cycle
        if (Carbon::now()->day < 19) {
            $cycleEnd = Carbon::now()->copy()->startOfMonth()->addDays(17);
            $cycleStart = $cycleEnd->copy()->subMonth()->addDays(1);
        }

        // Get company holidays
        $holidays = DB::table('company_holidays')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->pluck('date')
            ->map(function ($d) { return Carbon::parse($d)->format('Y-m-d'); })
            ->toArray();

        // Get approved OT requests in cycle
        $query = DB::table('ot_requests')
            ->join('employees', 'ot_requests.emp_id', '=', 'employees.id')
            ->where('ot_requests.status', 'approved')
            ->whereBetween('ot_requests.date', [$cycleStart->toDateString(), $cycleEnd->toDateString()]);

        if ($companyId) {
            $query->where('ot_requests.company_id', $companyId);
        }

        $otRequests = $query->select(
            'ot_requests.id',
            'ot_requests.emp_id',
            'ot_requests.date',
            'ot_requests.start_time',
            'ot_requests.end_time',
            'employees.name as emp_name',
            'employees.employee_code',
            'employees.position'
        )->get();

        // Group by employee
        $employeeOts = $otRequests->groupBy('emp_id');

        $summaries = [];

        foreach ($employeeOts as $empId => $requests) {
            $empName = $requests->first()->emp_name;
            $empCode = $requests->first()->employee_code;

            $totalHours = 0;
            $hours1x = 0;
            $hours15x = 0;
            $hours2x = 0;
            $hours3x = 0;

            foreach ($requests as $req) {
                $otDate = Carbon::parse($req->date)->format('Y-m-d');
                $start = Carbon::parse($req->start_time);
                $end = Carbon::parse($req->end_time);

                // Handle overnight shifts
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $hours = $start->diffInHours($end);
                $isHoliday = in_array($otDate, $holidays);

                if ($isHoliday) {
                    if ($hours <= 8) {
                        // Holiday ≤8h: monthly=1x, daily=2x
                        // We'll determine rate based on position later
                        // For now, assume monthly (1x)
                        $hours1x += $hours;
                    } else {
                        // Holiday >8h: first 8h at base rate, excess at 3x
                        $hours1x += 8;
                        $hours3x += ($hours - 8);
                    }
                } else {
                    // Weekday OT = 1.5x
                    $hours15x += $hours;
                }

                $totalHours += $hours;
            }

            $summaries[] = [
                'emp_id' => $empId,
                'emp_name' => $empName,
                'employee_code' => $empCode,
                'total_hours' => $totalHours,
                'hours_1x' => $hours1x,
                'hours_15x' => $hours15x,
                'hours_2x' => $hours2x,
                'hours_3x' => $hours3x,
                'ot_days' => $requests->count(),
            ];
        }

        return response()->json([
            'cycle_start' => $cycleStart->format('Y-m-d'),
            'cycle_end' => $cycleEnd->format('Y-m-d'),
            'holidays' => $holidays,
            'summaries' => $summaries,
        ]);
    }
}
