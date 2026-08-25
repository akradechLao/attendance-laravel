<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use App\Models\WfhRecord;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Today's attendance
        $todayLog = AttendanceLog::where('emp_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->first();

        // Today's scheduled shift
        $todaySchedule = ShiftSchedule::where('emp_id', $employee->id)
            ->where('work_date', $today->format('Y-m-d'))
            ->first();
        $scheduleStart = null;
        $scheduleEnd = null;
        if ($employee->workShifts->count() > 0) {
            $defaultShift = $employee->workShifts->first();
            $scheduleStart = $defaultShift->start_time;
            $scheduleEnd = $defaultShift->end_time;
        }

        // Calculate worked hours
        $workedHours = null;
        if ($todayLog && $todayLog->check_in && $todayLog->check_out) {
            $in = Carbon::parse($todayLog->check_in);
            $out = Carbon::parse($todayLog->check_out);
            $workedHours = round($in->diffInMinutes($out) / 60, 1);
        } elseif ($todayLog && $todayLog->check_in && !$todayLog->check_out) {
            $in = Carbon::parse($todayLog->check_in);
            $now = Carbon::now();
            $workedHours = round($in->diffInMinutes($now) / 60, 1);
        }

        // Monthly stats
        $monthLogs = AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->get();

        $workingDays = $monthLogs->count();
        $lateDays = $monthLogs->where('check_in_status', 'late')->count();
        $onTimeDays = $monthLogs->where('check_in_status', 'on_time')->count();
        $totalLateMinutes = $monthLogs->sum('late_minutes');
        $absentDays = $monthStart->diffInDays($today) + 1 - $workingDays;
        if ($absentDays < 0) $absentDays = 0;

        // Approved leave days this month
        $leaveDays = LeaveRequest::where('emp_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->format('Y-m-d'))
            ->where('end_date', '>=', $monthStart->format('Y-m-d'))
            ->sum('total_days');

        // Approved OT hours this month (from ot_requests)
        $otHours = OtRequest::where('emp_id', $employee->id)
            ->where('status', 'approved')
            ->whereMonth('ot_date', $today->month)
            ->whereYear('ot_date', $today->year)
            ->sum('total_hours');

        // Pending requests count
        $pendingLeave = LeaveRequest::where('emp_id', $employee->id)->where('status', 'pending')->count();
        $pendingOt = OtRequest::where('emp_id', $employee->id)->where('status', 'pending')->count();
        $pendingWfh = WfhRecord::where('emp_id', $employee->id)->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayLog ? [
                    'date' => $todayLog->date,
                    'check_in' => $todayLog->check_in,
                    'check_out' => $todayLog->check_out,
                    'status' => $todayLog->check_in_status,
                    'late_minutes' => $todayLog->late_minutes,
                    'is_checked_in' => true,
                    'is_checked_out' => !is_null($todayLog->check_out),
                    'schedule_start' => $scheduleStart,
                    'schedule_end' => $scheduleEnd,
                    'worked_hours' => $workedHours,
                ] : [
                    'date' => $today->format('Y-m-d'),
                    'check_in' => null,
                    'check_out' => null,
                    'status' => null,
                    'late_minutes' => null,
                    'is_checked_in' => false,
                    'is_checked_out' => false,
                    'schedule_start' => $scheduleStart,
                    'schedule_end' => $scheduleEnd,
                    'worked_hours' => null,
                ],
                'month' => [
                    'working_days' => $workingDays,
                    'on_time' => $onTimeDays,
                    'late' => $lateDays,
                    'absent' => $absentDays,
                    'leave_days' => $leaveDays,
                    'total_late_minutes' => $totalLateMinutes,
                    'ot_hours' => $otHours,
                ],
                'pending' => [
                    'leave' => $pendingLeave,
                    'ot' => $pendingOt,
                    'wfh' => $pendingWfh,
                ],
            ],
        ]);
    }
}
