<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $leaves = LeaveRequest::with(['employee', 'leaveType'])
                ->where('company_id', $user->company_id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $leaves->getCollection()->transform(fn($l) => [
                'id' => $l->id,
                'emp_id' => $l->emp_id,
                'leave_type_id' => $l->leave_type_id,
                'start_date' => Carbon::parse($l->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($l->end_date)->format('Y-m-d'),
                'total_days' => (int) ($l->total_days ?? 0),
                'reason' => $l->reason,
                'status' => $l->status,
                'supervisor_note' => $l->supervisor_note,
                'created_at' => $l->created_at ? Carbon::parse($l->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $l->employee ? ['id' => $l->employee->id, 'employee_code' => $l->employee->employee_code, 'first_name' => $l->employee->first_name, 'last_name' => $l->employee->last_name] : null,
                'leave_type' => $l->leaveType ? ['id' => $l->leaveType->id, 'name' => $l->leaveType->name, 'code' => $l->leaveType->code] : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $leaves,
                'message' => 'Leave requests retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve leave requests: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function types(Request $request): JsonResponse
    {
        try {
            $types = LeaveType::where('company_id', $request->user()->company_id)->get();

            return response()->json([
                'success' => true,
                'data' => $types,
                'message' => 'Leave types retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve leave types: ' . $e->getMessage(),
            ], 500);
        }
    }
}
