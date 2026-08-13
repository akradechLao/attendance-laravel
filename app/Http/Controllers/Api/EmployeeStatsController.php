<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employeeId = $request->get('emp_id');
        $employee = Employee::find($employeeId);

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'No employee found'], 404);
        }

        $today = Carbon::now('Asia/Bangkok');

        $monthTotal = AttendanceLog::where('emp_id', $employeeId)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->count();

        $onTimeDays = AttendanceLog::where('emp_id', $employeeId)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('status', 'on_time')
            ->count();

        $lateDays = AttendanceLog::where('emp_id', $employeeId)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('status', 'late')
            ->count();

        $yearTotal = AttendanceLog::where('emp_id', $employeeId)
            ->whereYear('date', $today->year)
            ->count();

        $recentRecords = AttendanceLog::where('emp_id', $employeeId)
            ->where('date', '>=', $today->copy()->subDays(14)->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'date' => $log->date,
                    'check_in' => $log->check_in,
                    'check_out' => $log->check_out,
                    'status' => $log->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'department' => $employee->department,
                    'position' => $employee->position,
                ],
                'summary' => [
                    'month_total' => $monthTotal,
                    'on_time_days' => $onTimeDays,
                    'late_days' => $lateDays,
                    'year_total' => $yearTotal,
                ],
                'recent_records' => $recentRecords,
            ],
        ]);
    }
}
