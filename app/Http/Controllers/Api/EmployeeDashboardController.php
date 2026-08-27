<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ShiftCodeHelper;
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

            $today = Carbon::now('Asia/Bangkok')->today();

            $statMonth = $request->input('month') ? (int) $request->input('month') : $today->month;
            $statYear = $request->input('year') ? (int) $request->input('year') : $today->year;
            $statDate = Carbon::createFromDate($statYear, $statMonth, 1)->setTimezone('Asia/Bangkok');
            $monthStart = $statDate->copy()->startOfMonth();
            $monthEnd = $statDate->copy()->endOfMonth();

            $todayLog = AttendanceLog::where('emp_id', $employee->id)
                ->where('date', $today->format('Y-m-d'))
                ->first();

            $todayStr = $today->format('Y-m-d');

            // 1. Get today's actual shift from shift_schedules
            $todaySchedule = ShiftSchedule::where('emp_id', $employee->id)
                ->where('work_date', $todayStr)
                ->first();

            $todayShiftCode = $todaySchedule ? $todaySchedule->shift_code : null;
            $todayTimes = $todayShiftCode ? ShiftCodeHelper::getTimes($todayShiftCode) : ['start' => null, 'end' => null];

            // 2. Get ALL assigned work shifts with date ranges
            $assignedShifts = [];
            $activeShiftCode = null;
            $activeShiftTimes = ['start' => null, 'end' => null];
            try {
                $employee->load('workShifts');
                foreach ($employee->workShifts as $ws) {
                    $pivot = $ws->pivot;
                    $isActive = true;
                    if ($pivot->start_date && $todayStr < $pivot->start_date) $isActive = false;
                    if ($pivot->end_date && $todayStr > $pivot->end_date) $isActive = false;

                    $code = ShiftCodeHelper::codeFromGroup($ws->group_number) ?? "WC" . str_pad($ws->group_number + 1, 4, '0', STR_PAD_LEFT);
                    $sTime = $ws->start_time instanceof \Carbon\Carbon ? $ws->start_time->format('H:i') : substr($ws->start_time, 0, 5);
                    $eTime = $ws->end_time instanceof \Carbon\Carbon ? $ws->end_time->format('H:i') : substr($ws->end_time, 0, 5);

                    if ($isActive && !$activeShiftCode) {
                        $activeShiftCode = $code;
                        $activeShiftTimes = ['start' => $sTime, 'end' => $eTime];
                    }

                    $assignedShifts[] = [
                        'group_number' => $ws->group_number,
                        'shift_code' => $code,
                        'start_time' => $sTime,
                        'end_time' => $eTime,
                        'work_hours' => $ws->work_hours,
                        'is_overnight' => $ws->is_overnight,
                        'start_date' => $pivot->start_date instanceof \Carbon\Carbon ? $pivot->start_date->format('Y-m-d') : $pivot->start_date,
                        'end_date' => $pivot->end_date instanceof \Carbon\Carbon ? $pivot->end_date->format('Y-m-d') : $pivot->end_date,
                        'is_active' => $isActive,
                    ];
                }
            } catch (\Exception $e) {}

            // 3. Fallback: if no shift_schedules entry, use active employee_shifts
            if (!$todayShiftCode && $activeShiftCode) {
                $todayShiftCode = $activeShiftCode;
                $todayTimes = $activeShiftTimes;
            }

            // 3. Calculate worked hours
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

            // 4. Monthly stats
            $monthLogs = AttendanceLog::where('emp_id', $employee->id)
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->get();

            $workingDays = $monthLogs->count();
            $lateDays = $monthLogs->where('check_in_status', 'late')->count();
            $onTimeDays = $monthLogs->where('check_in_status', 'on_time')->count();
            $totalLateMinutes = (int) $monthLogs->sum('late_minutes');

            $absentDays = 0;
            if ($monthStart->lte($today)) {
                $effectiveEnd = $monthEnd->lte($today) ? $monthEnd : $today;
                $totalDaysInPeriod = (int) $monthStart->diffInDays($effectiveEnd) + 1;
                $absentDays = max(0, (int) ($totalDaysInPeriod - $workingDays));
            }

            $leaveDays = 0;
            try {
                $leaveDays = LeaveRequest::where('emp_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $monthEnd->format('Y-m-d'))
                    ->where('end_date', '>=', $monthStart->format('Y-m-d'))
                    ->sum('total_days');
            } catch (\Exception $e) {}

            $otHours = 0;
            try {
                $otHours = OtRequest::where('emp_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereMonth('date', $statMonth)
                    ->whereYear('date', $statYear)
                    ->sum('total_hours');
            } catch (\Exception $e) {}

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

            // Build today data
            $todayData = [
                'date' => $today->format('Y-m-d'),
                'check_in' => $todayLog && $todayLog->check_in ? Carbon::parse($todayLog->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'check_out' => $todayLog && $todayLog->check_out ? Carbon::parse($todayLog->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'status' => $todayLog ? $todayLog->check_in_status : null,
                'late_minutes' => $todayLog ? (int) ($todayLog->late_minutes ?? 0) : null,
                'is_checked_in' => (bool) $todayLog,
                'is_checked_out' => $todayLog && !is_null($todayLog->check_out),
                'schedule_start' => $todayTimes['start'],
                'schedule_end' => $todayTimes['end'],
                'today_shift_code' => $todayShiftCode,
                'worked_hours' => $workedHours,
                'assigned_shifts' => $assignedShifts,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $todayData,
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
