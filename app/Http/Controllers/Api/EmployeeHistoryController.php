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
}
