<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function leaveApproval(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if (method_exists($user, 'getAllSubordinateIds')) {
            $subordinateIds = $user->getAllSubordinateIds();
        } else {
            $subordinateIds = Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        if (empty($subordinateIds)) {
            return response()->json(['data' => []]);
        }

        $query = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->whereIn('emp_id', $subordinateIds);

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $leaveRequests]);
    }

    public function otApproval(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if (method_exists($user, 'getAllSubordinateIds')) {
            $subordinateIds = $user->getAllSubordinateIds();
        } else {
            $subordinateIds = Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        if (empty($subordinateIds)) {
            return response()->json(['data' => []]);
        }

        $query = OtRequest::with('employee')
            ->where('status', 'pending')
            ->whereIn('emp_id', $subordinateIds);

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $otRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $otRequests]);
    }

    public function teamCalendar(Request $request)
    {
        $date = $request->date ?? date('Y-m-d');
        $user = $request->user();
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $teamMembers = Employee::with(['attendanceLogs' => function ($query) use ($date) {
            $query->where('date', $date);
        }])->where('reports_to', $employee->id)->get();

        return response()->json(['data' => $teamMembers]);
    }
}
