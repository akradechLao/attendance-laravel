<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
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

        $now = \Carbon\Carbon::now();
        $statMonth = $request->input('month') ? (int) $request->input('month') : null;
        $statYear = $request->input('year') ? (int) $request->input('year') : null;

        // If month/year provided, filter by that month; otherwise return recent records
        if ($statMonth && $statYear) {
            $monthStart = \Carbon\Carbon::createFromDate($statYear, $statMonth, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $attendance = AttendanceLog::where('emp_id', $employee->id)
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->orderBy('date', 'desc')
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'date' => $log->date,
                    'check_in' => $log->check_in,
                    'check_out' => $log->check_out,
                    'status' => $log->check_in_status,
                    'late_minutes' => $log->late_minutes,
                    'note' => $log->note,
                ]);

            $leave = LeaveRequest::with('leaveType')
                ->where('emp_id', $employee->id)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                      ->orWhereBetween('end_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // Summary stats for selected month
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

        // No month filter: return recent records
        $attendance = AttendanceLog::where('emp_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit($request->get('limit', 30))
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'date' => $log->date,
                'check_in' => $log->check_in,
                'check_out' => $log->check_out,
                'status' => $log->check_in_status,
                'late_minutes' => $log->late_minutes,
                'note' => $log->note,
            ]);

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }
}
