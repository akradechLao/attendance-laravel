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
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

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
                'note' => $log->note,
            ]);

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }
}
