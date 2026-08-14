<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Services\LeaveService;
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
        $employee = Employee::find($request->get('emp_id'));
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'No employee found'], 404);
        }

        $year = $request->get('year', Carbon::now()->year);
        $balances = $this->leaveService->getAllBalances($employee, $year);

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employee = Employee::find($validated['emp_id']);
        $leaveType = LeaveType::find($validated['leave_type_id']);
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
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

        return response()->json([
            'success' => true,
            'data' => $leave->load(['employee', 'leaveType']),
            'message' => 'ส่งคำขอลาสำเร็จ',
        ], 201);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'รายการนี้ดำเนินการแล้ว'], 400);
        }

        $employee = Employee::find($leave->emp_id);
        $leaveType = LeaveType::find($leave->leave_type_id);
        $year = Carbon::parse($leave->start_date)->year;

        $this->leaveService->deductLeave($employee, $leaveType, $leave->total_days, $year);

        $leave->update([
            'status' => 'approved',
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
        ]);

        return response()->json(['success' => true, 'data' => $leave, 'message' => 'อนุมัติลาสำเร็จ']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        $leave->update([
            'status' => 'rejected',
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
        ]);

        return response()->json(['success' => true, 'data' => $leave, 'message' => 'ปฏิเสธคำขอลา']);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $leaves = LeaveRequest::where('emp_id', $request->get('emp_id'))
            ->with('leaveType')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $leaves]);
    }

    public function teamLeaves(Request $request): JsonResponse
    {
        $supervisorId = $request->get('supervisor_id');
        $employeeIds = Employee::where('supervisor_id', $supervisorId)->pluck('id')->toArray();

        $leaves = LeaveRequest::whereIn('emp_id', $employeeIds)
            ->with(['employee', 'leaveType'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $leaves]);
    }
}
