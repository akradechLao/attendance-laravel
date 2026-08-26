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
        try {
            $employee = $request->user();
            if (!$employee) {
                return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
            }

            $today = Carbon::today();

            // Support optional month/year params for historical stats
            $statMonth = $request->input('month') ? (int) $request->input('month') : $today->month;
            $statYear = $request->input('year') ? (int) $request->input('year') : $today->year;
            $statDate = Carbon::createFromDate($statYear, $statMonth, 1);
            $monthStart = $statDate->copy()->startOfMonth();
            $monthEnd = $statDate->copy()->endOfMonth();

            // Today's attendance (always current day)
            $todayLog = AttendanceLog::where('emp_id', $employee->id)
                ->where('date', $today->format('Y-m-d'))
                ->first();

            // Get schedule from workShifts or shift_schedules
            $scheduleStart = null;
            $scheduleEnd = null;
            try {
                if ($employee->workShifts()->count() > 0) {
                    $defaultShift = $employee->workShifts()->first();
                    $scheduleStart = $defaultShift->start_time;
                    $scheduleEnd = $defaultShift->end_time;
                }
            } catch (\Exception $e) {
            }

            if (!$scheduleStart && !$scheduleEnd) {
                try {
                    $todaySchedule = ShiftSchedule::where('emp_id', $employee->id)
                        ->where('work_date', $today->format('Y-m-d'))
                        ->first();
                    if ($todaySchedule && $todaySchedule->start_time) {
                        $scheduleStart = $todaySchedule->start_time;
                        $scheduleEnd = $todaySchedule->end_time;
                    }
                } catch (\Exception $e) {}
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

            // Monthly stats for selected month
            $monthLogs = AttendanceLog::where('emp_id', $employee->id)
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->get();

            $workingDays = $monthLogs->count();
            $lateDays = $monthLogs->where('check_in_status', 'late')->count();
            $onTimeDays = $monthLogs->where('check_in_status', 'on_time')->count();
            $totalLateMinutes = $monthLogs->sum('late_minutes');

            // Absent days: only meaningful for current month or past months up to today
            $absentDays = 0;
            if ($monthStart->lte($today)) {
                $effectiveEnd = $monthEnd->lte($today) ? $monthEnd : $today;
                $totalDaysInPeriod = $monthStart->diffInDays($effectiveEnd) + 1;
                $absentDays = $totalDaysInPeriod - $workingDays;
                if ($absentDays < 0) $absentDays = 0;
            }

            // Approved leave days in selected month
            $leaveDays = 0;
            try {
                $leaveDays = LeaveRequest::where('emp_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $monthEnd->format('Y-m-d'))
                    ->where('end_date', '>=', $monthStart->format('Y-m-d'))
                    ->sum('total_days');
            } catch (\Exception $e) {}

            // Approved OT hours in selected month
            $otHours = 0;
            try {
                $otHours = OtRequest::where('emp_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereMonth('ot_date', $statMonth)
                    ->whereYear('ot_date', $statYear)
                    ->sum('total_hours');
            } catch (\Exception $e) {}

            // Pending requests (always current)
            $pendingLeave = 0;
            $pendingOt = 0;
            $pendingWfh = 0;
            try {
                $pendingLeave = LeaveRequest::where('emp_id', $employee->id)->where('status', 'pending')->count();
            } catch (\Exception $e) {}
            try {
                $pendingOt = OtRequest::where('emp_id', $employee->id)->where('status', 'pending')->count();
            } catch (\Exception $e) {}
            try {
                $pendingWfh = WfhRecord::where('emp_id', $employee->id)->where('status', 'pending')->count();
            } catch (\Exception $e) {}

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
                        'month' => $statMonth,
                        'year' => $statYear,
                    ],
                    'pending' => [
                        'leave' => $pendingLeave,
                        'ot' => $pendingOt,
                        'wfh' => $pendingWfh,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
