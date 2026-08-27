<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Services\LeaveService;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function balance(Request $request): JsonResponse
    {
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'No employee found'], 404);
        }

        $year = $request->get('year', now()->setTimezone('Asia/Bangkok')->year);
        $balances = $this->leaveService->getAllBalances($employee, $year);

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employee = $request->user();
        $validated['emp_id'] = $employee->id;
        $leaveType = LeaveType::find($validated['leave_type_id']);
        $start = Carbon::parse($validated['start_date'])->setTimezone('Asia/Bangkok');
        $end = Carbon::parse($validated['end_date'])->setTimezone('Asia/Bangkok');
        $totalDays = $start->diffInDays($end) + 1;

        $year = $start->year;
        $balance = $this->leaveService->getLeaveBalance($employee, $leaveType, $year);

        if ($totalDays > $balance['remaining'] && $leaveType->code !== 'unpaid') {
            return response()->json([
                'success' => false,
                'message' => "วันลาไม่เพียงพอ (เหลือ {$balance['remaining']} วัน)",
            ], 400);
        }

        $validated['company_id'] = $employee->company_id;
        $validated['total_days'] = $totalDays;
        $validated['status'] = 'pending';

        $leave = LeaveRequest::create($validated);

        $leave->load(['employee', 'leaveType']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $leave->id,
                'emp_id' => $leave->emp_id,
                'leave_type_id' => $leave->leave_type_id,
                'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                'total_days' => (int) $leave->total_days,
                'reason' => $leave->reason,
                'status' => $leave->status,
                'created_at' => $leave->created_at ? Carbon::parse($leave->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $leave->employee ? ['id' => $leave->employee->id, 'employee_code' => $leave->employee->employee_code, 'first_name' => $leave->employee->first_name, 'last_name' => $leave->employee->last_name] : null,
                'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name, 'code' => $leave->leaveType->code] : null,
            ],
            'message' => 'ส่งคำขอลาสำเร็จ',
        ], 201);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'รายการนี้ดำเนินการแล้ว'], 400);
        }

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($leave->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }
        $employee = Employee::find($leave->emp_id);
        $leaveType = LeaveType::find($leave->leave_type_id);
        $year = Carbon::parse($leave->start_date)->setTimezone('Asia/Bangkok')->year;

        $this->leaveService->deductLeave($employee, $leaveType, $leave->total_days, $year);

        $leave->update([
            'status' => 'approved',
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
        ]);

        $leave->load(['employee', 'leaveType']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $leave->id,
                'emp_id' => $leave->emp_id,
                'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                'total_days' => (int) $leave->total_days,
                'status' => $leave->status,
                'employee' => $leave->employee ? ['id' => $leave->employee->id, 'employee_code' => $leave->employee->employee_code, 'first_name' => $leave->employee->first_name, 'last_name' => $leave->employee->last_name] : null,
                'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name] : null,
            ],
            'message' => 'อนุมัติลาสำเร็จ',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($leave->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $leave->update([
            'status' => 'rejected',
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
        ]);

        $leave->load(['employee', 'leaveType']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $leave->id,
                'emp_id' => $leave->emp_id,
                'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                'total_days' => (int) $leave->total_days,
                'status' => $leave->status,
                'employee' => $leave->employee ? ['id' => $leave->employee->id, 'employee_code' => $leave->employee->employee_code, 'first_name' => $leave->employee->first_name, 'last_name' => $leave->employee->last_name] : null,
                'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name] : null,
            ],
            'message' => 'ปฏิเสธคำขอลา',
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employee = $request->user();
        $leaves = LeaveRequest::where('emp_id', $employee->id)
            ->with('leaveType')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'emp_id' => $l->emp_id,
                'leave_type_id' => $l->leave_type_id,
                'start_date' => Carbon::parse($l->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($l->end_date)->format('Y-m-d'),
                'total_days' => (int) $l->total_days,
                'reason' => $l->reason,
                'status' => $l->status,
                'supervisor_note' => $l->supervisor_note,
                'created_at' => $l->created_at ? Carbon::parse($l->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'leave_type' => $l->leaveType ? ['id' => $l->leaveType->id, 'name' => $l->leaveType->name, 'code' => $l->leaveType->code] : null,
            ]);

        return response()->json(['success' => true, 'data' => $leaves]);
    }

    public function teamLeaves(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if (method_exists($user, 'getAllSubordinateIds')) {
            $employeeIds = $user->getAllSubordinateIds();
        } else {
            $employeeIds = \App\Models\Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $leaves = LeaveRequest::whereIn('emp_id', $employeeIds)
            ->with(['employee', 'leaveType'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'emp_id' => $l->emp_id,
                'start_date' => Carbon::parse($l->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($l->end_date)->format('Y-m-d'),
                'total_days' => (int) $l->total_days,
                'status' => $l->status,
                'created_at' => $l->created_at ? Carbon::parse($l->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $l->employee ? ['id' => $l->employee->id, 'employee_code' => $l->employee->employee_code, 'first_name' => $l->employee->first_name, 'last_name' => $l->employee->last_name] : null,
                'leave_type' => $l->leaveType ? ['id' => $l->leaveType->id, 'name' => $l->leaveType->name] : null,
            ]);

        return response()->json(['success' => true, 'data' => $leaves]);
    }
}
