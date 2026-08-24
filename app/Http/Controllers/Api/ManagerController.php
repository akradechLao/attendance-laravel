<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function leaveApproval(Request $request)
    {
        $query = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $leaveRequests]);
    }

    public function otApproval(Request $request)
    {
        $query = OtRequest::with('employee')
            ->where('status', 'pending');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $otRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $otRequests]);
    }

    public function teamReport(Request $request)
    {
        $empId = $request->emp_id;
        $month = $request->month;

        $employee = Employee::findOrFail($empId);
        $attendanceLogs = AttendanceLog::where('emp_id', $empId)
            ->whereMonth('date', substr($month, 5, 2))
            ->whereYear('date', substr($month, 0, 4))
            ->get();

        $workingDays = $attendanceLogs->count();
        $onTime = $attendanceLogs->where('check_in_status', 'on_time')->count();
        $late = $attendanceLogs->where('check_in_status', 'late')->count();
        $leave = LeaveRequest::where('emp_id', $empId)
            ->whereMonth('start_date', substr($month, 5, 2))
            ->whereYear('start_date', substr($month, 0, 4))
            ->where('status', 'approved')
            ->count();

        return response()->json([
            'data' => [
                'employee' => $employee,
                'working_days' => $workingDays,
                'on_time' => $onTime,
                'late' => $late,
                'leave' => $leave,
                'records' => $attendanceLogs,
            ]
        ]);
    }
}
