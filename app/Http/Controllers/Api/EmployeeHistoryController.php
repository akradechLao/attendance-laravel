<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ShiftCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;

class EmployeeHistoryController extends Controller
{
    public function index(Request $request, $empId)
    {
        $employee = Employee::findOrFail($empId);

        $attendance = AttendanceLog::where('emp_id', $empId)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $leave = LeaveRequest::with('leaveType')
            ->where('emp_id', $empId)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => [
                'attendance' => $attendance,
                'leave' => $leave,
            ]
        ]);
    }

    public function myHistory(Request $request)
    {
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $now = \Carbon\Carbon::now('Asia/Bangkok');
        $statMonth = $request->input('month') ? (int) $request->input('month') : null;
        $statYear = $request->input('year') ? (int) $request->input('year') : null;

        if ($statMonth && $statYear) {
            $monthStart = \Carbon\Carbon::createFromDate($statYear, $statMonth, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $attendance = AttendanceLog::where('emp_id', $employee->id)
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->orderBy('date', 'desc')
                ->get();

            // Get shift schedules for this month
            $dateRange = collect(range(0, $monthEnd->diffInDays($monthStart)))
                ->map(fn($i) => $monthStart->copy()->addDays($i)->format('Y-m-d'));
            $shiftMap = ShiftSchedule::where('emp_id', $employee->id)
                ->whereIn('work_date', $dateRange)
                ->get()
                ->keyBy('work_date');

            $attendance = $attendance->map(function ($log) use ($shiftMap) {
                $dateStr = \Carbon\Carbon::parse($log->date)->format('Y-m-d');
                $schedule = $shiftMap->get($dateStr);
                $shiftCode = $schedule ? $schedule->shift_code : null;
                $times = $shiftCode ? ShiftCodeHelper::getTimes($shiftCode) : ['start' => null, 'end' => null];

                return [
                    'id' => $log->id,
                    'date' => $dateStr,
                    'check_in' => $log->check_in ? \Carbon\Carbon::parse($log->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'check_out' => $log->check_out ? \Carbon\Carbon::parse($log->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'status' => $log->check_in_status,
                    'late_minutes' => (int) ($log->late_minutes ?? 0),
                    'note' => $log->adjustment_note,
                    'shift_code' => $shiftCode,
                    'schedule_start' => $times['start'],
                    'schedule_end' => $times['end'],
                ];
            });

            $leave = LeaveRequest::with('leaveType:id,name,code')
                ->where('emp_id', $employee->id)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                      ->orWhereBetween('end_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($l) => [
                    'id' => $l->id,
                    'leave_type' => $l->leaveType?->name,
                    'start_date' => \Carbon\Carbon::parse($l->start_date)->format('Y-m-d'),
                    'end_date' => \Carbon\Carbon::parse($l->end_date)->format('Y-m-d'),
                    'total_days' => (int) $l->total_days,
                    'reason' => $l->reason,
                    'status' => $l->status,
                ]);

            $summary = [
                'total_days' => $attendance->count(),
                'on_time' => $attendance->filter(fn($r) => $r['status'] === 'on_time')->count(),
                'late' => $attendance->filter(fn($r) => $r['status'] === 'late')->count(),
                'total_late_minutes' => $attendance->sum('late_minutes'),
                'leave_days' => $leave->filter(fn($r) => $r['status'] === 'approved')->sum('total_days'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'attendance' => $attendance,
                    'leave' => $leave,
                ],
                'summary' => $summary,
                'month' => $statMonth,
                'year' => $statYear,
            ]);
        }

        // No month filter
        $attendance = AttendanceLog::where('emp_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit($request->get('limit', 30))
            ->get();

        // Get shift schedules for the date range
        $dates = $attendance->map(fn($l) => \Carbon\Carbon::parse($l->date)->format('Y-m-d'))->toArray();
        $shiftMap = ShiftSchedule::where('emp_id', $employee->id)
            ->whereIn('work_date', $dates)
            ->get()
            ->keyBy('work_date');

        $attendance = $attendance->map(function ($log) use ($shiftMap) {
            $dateStr = \Carbon\Carbon::parse($log->date)->format('Y-m-d');
            $schedule = $shiftMap->get($dateStr);
            $shiftCode = $schedule ? $schedule->shift_code : null;
            $times = $shiftCode ? ShiftCodeHelper::getTimes($shiftCode) : ['start' => null, 'end' => null];

            return [
                'id' => $log->id,
                'date' => $dateStr,
                'check_in' => $log->check_in ? \Carbon\Carbon::parse($log->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'check_out' => $log->check_out ? \Carbon\Carbon::parse($log->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'status' => $log->check_in_status,
                'late_minutes' => (int) ($log->late_minutes ?? 0),
                'note' => $log->adjustment_note,
                'shift_code' => $shiftCode,
                'schedule_start' => $times['start'],
                'schedule_end' => $times['end'],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }
}
