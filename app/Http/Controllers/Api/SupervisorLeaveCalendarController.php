<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorLeaveCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        // Get team members (direct reports)
        $teamIds = Employee::where('reports_to', $employee->id)->pluck('id')->toArray();

        if (empty($teamIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'today' => [],
                    'tomorrow' => [],
                    'this_week' => [],
                    'upcoming' => [],
                ],
            ]);
        }

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $weekEnd = Carbon::today()->addDays(7);

        // Get all approved leaves that overlap with our date range
        $leaves = LeaveRequest::whereIn('emp_id', $teamIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $weekEnd->format('Y-m-d'))
            ->where('end_date', '>=', $today->format('Y-m-d'))
            ->with(['employee:id,name,employee_code,department,position', 'leaveType:id,name,code'])
            ->orderBy('start_date')
            ->get();

        $todayLeaves = $leaves->filter(fn($l) =>
            $l->start_date <= $today->format('Y-m-d') && $l->end_date >= $today->format('Y-m-d')
        )->values();

        $tomorrowLeaves = $leaves->filter(fn($l) =>
            $l->start_date <= $tomorrow->format('Y-m-d') && $l->end_date >= $tomorrow->format('Y-m-d')
        )->values();

        $thisWeekLeaves = $leaves->filter(fn($l) =>
            $l->start_date > $today->format('Y-m-d') && $l->start_date <= $weekEnd->format('Y-m-d')
        )->values();

        $upcoming = $leaves->filter(fn($l) =>
            $l->start_date > $weekEnd->format('Y-m-d')
        )->values();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $this->formatLeaves($todayLeaves, $today),
                'tomorrow' => $this->formatLeaves($tomorrowLeaves, $tomorrow),
                'this_week' => $this->formatLeaves($thisWeekLeaves, $today),
                'upcoming' => $this->formatLeaves($upcoming, $today),
            ],
        ]);
    }

    private function formatLeaves($leaves, Carbon $referenceDate): array
    {
        return $leaves->map(fn($l) => [
            'id' => $l->id,
            'employee' => [
                'name' => $l->employee?->name,
                'code' => $l->employee?->employee_code,
                'department' => $l->employee?->department,
                'position' => $l->employee?->position,
            ],
            'leave_type' => $l->leaveType?->name,
            'start_date' => $l->start_date,
            'end_date' => $l->end_date,
            'total_days' => $l->total_days,
            'reason' => $l->reason,
            'days_remaining' => Carbon::parse($l->end_date)->diffInDays($referenceDate, false) >= 0
                ? Carbon::parse($l->end_date)->diffInDays($referenceDate) + 1
                : 0,
        ])->toArray();
    }
}
