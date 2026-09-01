<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Constants\RoleConstants;
use App\Services\LeaveService;
use App\Services\AuditLogService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $leaves = LeaveRequest::with(['employee', 'leaveType'])
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
                'rejection_reason' => $l->rejection_reason,
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

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string|max:1000',
            ]);

            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $validated['total_days'] = $startDate->diffInDays($endDate) + 1;
            $validated['status'] = 'pending';

            $leave = LeaveRequest::create($validated);

            return response()->json([
                'success' => true,
                'data' => $leave->load(['employee', 'leaveType']),
                'message' => 'Leave request created successfully.',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create leave request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($id);

            if ($leave->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Leave request is not in pending status.',
                ], 400);
            }

            // Authorization: must be subordinate or HR admin
            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                if (!$user->isSubordinateOf($leave->emp_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
                }
            }

            $leave->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id ?? null,
                'approved_at' => now(),
            ]);

            // Deduct leave balance
            try {
                $leaveService = app(LeaveService::class);
                $employee = $leave->employee;
                $leaveType = $leave->leaveType;
                if ($employee && $leaveType) {
                    $year = Carbon::parse($leave->start_date)->year;
                    $leaveService->deductLeave($employee, $leaveType, $leave->total_days ?? 1, $year);
                }
            } catch (\Exception $e) {
                // Log but don't fail the approval
                \Log::warning('Failed to deduct leave balance: ' . $e->getMessage());
            }

            AuditLogService::action('approve', $leave, 'อนุมัติใบลา ' . ($leave->employee->name ?? $leave->emp_id), $request);

            // Send Telegram notification
            $this->sendLeaveNotification($leave, 'approved');

            // Send in-app notification to employee with approver's name & position
            $leaveType = $leave->leaveType;
            $approverId = $request->get('supervisor_id') ?? $request->user()->id;
            $approver = $approverId ? Employee::find($approverId) : null;
            $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'ผู้อนุมัติ';
            EmployeeNotification::notify(
                $leave->emp_id,
                'leave_approved',
                '✅ อนุมัติลางาน',
                "คำขอลาของคุณ (" . ($leaveType->name ?? '-') . " {$leave->start_date} ถึง {$leave->end_date}) ได้รับการอนุมัติโดย {$approverText}",
                $leave->id,
                'LeaveRequest'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $leave->id,
                    'emp_id' => $leave->emp_id,
                    'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                    'total_days' => (int) ($leave->total_days ?? 0),
                    'status' => $leave->status,
                    'employee' => $leave->employee ? ['id' => $leave->employee->id, 'employee_code' => $leave->employee->employee_code, 'first_name' => $leave->employee->first_name, 'last_name' => $leave->employee->last_name] : null,
                    'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name] : null,
                ],
                'message' => 'Leave request approved successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Leave request not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to approve leave request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($id);

            if ($leave->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Leave request is not in pending status.',
                ], 400);
            }

            // Authorization: must be subordinate or HR admin
            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                if (!$user->isSubordinateOf($leave->emp_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
                }
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $leave->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => $request->user()->id ?? null,
                'rejected_at' => now(),
            ]);

            AuditLogService::action('reject', $leave, 'ไม่อนุมัติใบลา ' . ($leave->employee->name ?? $leave->emp_id) . ': ' . $validated['rejection_reason'], $request);

            // Send Telegram notification
            $this->sendLeaveNotification($leave, 'rejected');

            // Send in-app notification to employee with approver's position
            $leaveType = $leave->leaveType;
            $approverId = $request->get('supervisor_id') ?? $request->user()->id;
            $approver = $approverId ? Employee::find($approverId) : null;
            $approverPosition = $approver ? $approver->getPositionName() : 'ผู้อนุมัติ';
            EmployeeNotification::notify(
                $leave->emp_id,
                'leave_rejected',
                '❌ ไม่อนุมัติลางาน',
                "คำขอลาของคุณ (" . ($leaveType->name ?? '-') . " {$leave->start_date} ถึง {$leave->end_date}) ไม่ได้รับการอนุมัติโดย {$approverText}" . ($leave->rejection_reason ? " เหตุผล: {$leave->rejection_reason}" : ''),
                $leave->id,
                'LeaveRequest'
            );

            $leave->load(['employee', 'leaveType']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $leave->id,
                    'emp_id' => $leave->emp_id,
                    'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                    'total_days' => (int) ($leave->total_days ?? 0),
                    'status' => $leave->status,
                    'employee' => $leave->employee ? ['id' => $leave->employee->id, 'employee_code' => $leave->employee->employee_code, 'first_name' => $leave->employee->first_name, 'last_name' => $leave->employee->last_name] : null,
                    'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name] : null,
                ],
                'message' => 'Leave request rejected successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Leave request not found.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to reject leave request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function types(): JsonResponse
    {
        try {
            $types = LeaveType::all();

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

    private function sendLeaveNotification(LeaveRequest $leave, string $action): void
    {
        try {
            $telegram = new TelegramService();
            $employee = $leave->employee;
            $leaveType = $leave->leaveType;
            if (!$employee) return;

            $emoji = $action === 'approved' ? '✅' : '❌';
            $statusText = $action === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ';
            $typeName = $leaveType?->name ?? '-';

            $message = "{$emoji} <b>ใบลา{$statusText}</b>\n\n";
            $message .= "👤 <b>ชื่อ:</b> {$employee->name}\n";
            $message .= "📋 <b>ประเภท:</b> {$typeName}\n";
            $message .= "📅 <b>วันที่:</b> {$leave->start_date} - {$leave->end_date}\n";
            $message .= "📝 <b>จำนวน:</b> {$leave->total_days} วัน\n";
            if ($action === 'rejected' && $leave->rejection_reason) {
                $message .= "❌ <b>เหตุผล:</b> {$leave->rejection_reason}\n";
            }

            if ($employee->telegram_chat_id) {
                $telegram->sendToChat($employee->telegram_chat_id, $message);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
