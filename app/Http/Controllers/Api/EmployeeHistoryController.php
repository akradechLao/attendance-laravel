<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AttendanceHelper;
use App\Helpers\ShiftCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Services\ShiftResolver;
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

            // Resolve shifts using ShiftResolver - group by date, combine rounds
            $grouped = $attendance->groupBy(fn($log) => \Carbon\Carbon::parse($log->date)->format('Y-m-d'));
            $attendance = $grouped->map(function ($logs, $dateStr) use ($employee) {
                $resolved = ShiftResolver::resolve($employee, $dateStr);
                $firstIn = AttendanceHelper::getFirstCheckIn($employee->id, $dateStr);
                $lastOut = AttendanceHelper::getLastCheckOut($employee->id, $dateStr);
                $workedHours = AttendanceHelper::calculateWorkedHours($employee->id, $dateStr);
                $lateMinutes = $logs->min('late_minutes') ?? 0;
                $status = $logs->firstWhere('check_in_status', 'late') ? 'late' : 'on_time';

                return [
                    'date' => $dateStr,
                    'check_in' => $firstIn ? \Carbon\Carbon::parse($firstIn)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'check_out' => $lastOut ? \Carbon\Carbon::parse($lastOut)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'status' => $status,
                    'late_minutes' => (int) $lateMinutes,
                    'shift_code' => $resolved['shift_code'],
                    'schedule_start' => $resolved['start_time'],
                    'schedule_end' => $resolved['end_time'],
                    'worked_hours' => $workedHours,
                ];
            })->values();

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

        // Resolve shifts using ShiftResolver - group by date, combine rounds
        $grouped = $attendance->groupBy(fn($log) => \Carbon\Carbon::parse($log->date)->format('Y-m-d'));
        $attendance = $grouped->map(function ($logs, $dateStr) use ($employee) {
            $resolved = ShiftResolver::resolve($employee, $dateStr);
            $firstIn = AttendanceHelper::getFirstCheckIn($employee->id, $dateStr);
            $lastOut = AttendanceHelper::getLastCheckOut($employee->id, $dateStr);
            $workedHours = AttendanceHelper::calculateWorkedHours($employee->id, $dateStr);
            $lateMinutes = $logs->min('late_minutes') ?? 0;
            $status = $logs->firstWhere('check_in_status', 'late') ? 'late' : 'on_time';

            return [
                'date' => $dateStr,
                'check_in' => $firstIn ? \Carbon\Carbon::parse($firstIn)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'check_out' => $lastOut ? \Carbon\Carbon::parse($lastOut)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'status' => $status,
                'late_minutes' => (int) $lateMinutes,
                'shift_code' => $resolved['shift_code'],
                'schedule_start' => $resolved['start_time'],
                'schedule_end' => $resolved['end_time'],
                'worked_hours' => $workedHours,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }
}
