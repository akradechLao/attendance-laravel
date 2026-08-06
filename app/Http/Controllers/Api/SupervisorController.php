<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class SupervisorController extends Controller
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

    public function teamCalendar(Request $request)
    {
        $date = $request->date ?? date('Y-m-d');

        $teamMembers = Employee::with(['attendanceLogs' => function ($query) use ($date) {
            $query->where('date', $date);
        }])->where('reports_to', auth()->id())->get();

        return response()->json(['data' => $teamMembers]);
    }
}
