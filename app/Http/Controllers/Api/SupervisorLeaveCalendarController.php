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
        $employee = $request->user();
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

        // start_date/end_date เป็น Carbon object (cast 'date' บน model) - ต้อง format เป็น
        // string ก่อนเทียบเสมอ ไม่งั้นการเทียบ Carbon <=> string จะไม่ถูกต้อง (เคยทำให้ leave
        // ของวันนี้หลุดไปอยู่ bucket "สัปดาห์นี้" แทนที่จะเป็น "วันนี้")
        $todayLeaves = $leaves->filter(fn($l) =>
            $l->start_date->format('Y-m-d') <= $today->format('Y-m-d') && $l->end_date->format('Y-m-d') >= $today->format('Y-m-d')
        )->values();

        $tomorrowLeaves = $leaves->filter(fn($l) =>
            $l->start_date->format('Y-m-d') <= $tomorrow->format('Y-m-d') && $l->end_date->format('Y-m-d') >= $tomorrow->format('Y-m-d')
        )->values();

        $thisWeekLeaves = $leaves->filter(fn($l) =>
            $l->start_date->format('Y-m-d') > $today->format('Y-m-d') && $l->start_date->format('Y-m-d') <= $weekEnd->format('Y-m-d')
        )->values();

        $upcoming = $leaves->filter(fn($l) =>
            $l->start_date->format('Y-m-d') > $weekEnd->format('Y-m-d')
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

    /**
     * รายการลาของทีมทั้งหมดในเดือนที่เลือก (ไม่แบ่งเป็นบัคเก็ต) สำหรับมุมมองปฏิทินรายเดือน
     */
    public function monthly(Request $request): JsonResponse
    {
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $teamIds = Employee::where('reports_to', $employee->id)->pluck('id')->toArray();

        if (empty($teamIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $leaves = LeaveRequest::whereIn('emp_id', $teamIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->format('Y-m-d'))
            ->where('end_date', '>=', $monthStart->format('Y-m-d'))
            ->with(['employee:id,name,employee_code,department,position', 'leaveType:id,name,code'])
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->formatLeaves($leaves, Carbon::today()),
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
            'start_date' => Carbon::parse($l->start_date)->format('Y-m-d'),
            'end_date' => Carbon::parse($l->end_date)->format('Y-m-d'),
            'total_days' => $l->total_days,
            'reason' => $l->reason,
            'days_remaining' => Carbon::parse($l->end_date)->diffInDays($referenceDate, false) >= 0
                ? Carbon::parse($l->end_date)->diffInDays($referenceDate) + 1
                : 0,
        ])->toArray();
    }
}
