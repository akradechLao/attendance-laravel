<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeHolidayController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $year = (int) $request->get('year', Carbon::now()->year);

        // Company holidays
        $holidays = CompanyHoliday::where('company_id', $employee->company_id)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'date' => Carbon::parse($h->date)->format('Y-m-d'),
                'name' => $h->name,
                'type' => $h->type ?? 'company',
            ]);

        // Leave balance
        $balances = $this->leaveService->getAllBalances($employee, $year);

        // My approved leaves this year (for calendar marking)
        $myLeaves = LeaveRequest::where('emp_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->with('leaveType:id,name,code')
            ->get()
            ->map(fn($l) => [
                'start_date' => Carbon::parse($l->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($l->end_date)->format('Y-m-d'),
                'type' => $l->leaveType?->name,
                'days' => (int) $l->total_days,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'holidays' => $holidays,
                'balances' => $balances,
                'my_leaves' => $myLeaves,
                'year' => $year,
            ],
        ]);
    }
}
