<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

class LeaveService
{
    public function requestLeave(array $data): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $employee = Employee::findOrFail($data['emp_id']);

        $totalDays = $this->calculateDays($data['start_date'], $data['end_date']);

        $leaveRequest = LeaveRequest::create([
            'company_id' => $data['company_id'],
            'emp_id' => $data['emp_id'],
            'leave_type_id' => $data['leave_type_id'],
            'reason' => $data['reason'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $totalDays,
            'status' => 'pending',
            'supervisor_id' => $data['supervisor_id'] ?? null,
        ]);

        return $leaveRequest;
    }

    public function approveLeave(int $leaveRequestId, int $approverId): LeaveRequest
    {
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);
        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_date' => now(),
        ]);

        return $leaveRequest;
    }

    public function rejectLeave(int $leaveRequestId, int $rejectorId, string $reason): LeaveRequest
    {
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);
        $leaveRequest->update([
            'status' => 'rejected',
            'rejected_by' => $rejectorId,
            'rejection_reason' => $reason,
        ]);

        return $leaveRequest;
    }

    public function getLeaveBalance(int $empId, int $leaveTypeId): int
    {
        $employee = Employee::findOrFail($empId);
        $leaveType = LeaveType::findOrFail($leaveTypeId);

        $used = LeaveRequest::where('emp_id', $empId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereYear('start_date', now()->year)
            ->sum('total_days');

        return max(0, $leaveType->quota_daily - $used);
    }

    private function calculateDays(string $startDate, string $endDate): int
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        return $start->diffInDays($end) + 1;
    }
}
