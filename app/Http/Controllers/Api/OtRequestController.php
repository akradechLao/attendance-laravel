<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtRequest;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Constants\RoleConstants;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = OtRequest::with('employee');

            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                $query->where('emp_id', $user->id);
            }

            $otRequests = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $otRequests->getCollection()->transform(fn($ot) => $this->formatOt($ot));

            return response()->json([
                'success' => true,
                'data' => $otRequests,
                'message' => 'OT requests retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve OT requests: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->has_ot) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'พนักงานไม่มีสิทธิ์ทำโอที',
                ], 403);
            }

            $validated = $request->validate([
                'date' => 'required|date|after_or_equal:-30 days',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:1000',
            ]);

            $validated['employee_id'] = $user->id;
            $validated['status'] = 'pending_manager';

            $otRequest = OtRequest::create($validated);
            $otRequest->load('employee');

            // Notify supervisor(s) of new OT request
            $supervisorIds = $user->getSupervisorIds();
            if (!empty($supervisorIds)) {
                EmployeeNotification::notifyMultiple(
                    $supervisorIds,
                    'ot_request',
                    'มีคำขอโอทีใหม่',
                    "{$user->name} ({$user->employee_code}) ขอโอทีวันที่ {$request->date} เวลา {$request->start_time}-{$request->end_time}" . ($request->reason ? " เหตุผล: {$request->reason}" : ''),
                    $otRequest->id,
                    'OtRequest'
                );
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatOt($otRequest),
                'message' => 'OT request created successfully.',
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
                'message' => 'Failed to create OT request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function managerApprove(Request $request, $id): JsonResponse
    {
        try {
            $otRequest = OtRequest::findOrFail($id);

            if ($otRequest->status !== 'pending_manager') {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'OT request is not awaiting manager approval.',
                ], 400);
            }

            // Authorization: must be subordinate or HR admin
            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                if (!$user->isSubordinateOf($otRequest->employee_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
                }
            }

            $otRequest->update([
                'status' => 'pending_hr',
                'manager_approved_by' => $request->user()->id ?? null,
                'manager_approved_at' => now(),
            ]);

            // Notify employee that manager approved with name & position
            $emp = $otRequest->employee;
            if ($emp) {
                $approver = Employee::find($request->user()->id ?? null);
                $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'ผู้จัดการ';
                EmployeeNotification::notify(
                    $otRequest->emp_id,
                    'ot_approved',
                    '✅ อนุมัติโอทีโดย ' . ($approver ? $approver->getPositionName() : 'ผู้จัดการ'),
                    "คำขอโอทีวันที่ {$otRequest->date} เวลา {$otRequest->start_time}-{$otRequest->end_time} ได้รับการอนุมัติโดย {$approverText} แล้ว รอ HR อนุมัติขั้นสุดท้าย",
                    $otRequest->id,
                    'OtRequest'
                );
            }

            $otRequest->load('employee');

            return response()->json([
                'success' => true,
                'data' => $this->formatOt($otRequest),
                'message' => 'OT request approved by manager.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'OT request not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to approve OT request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function finalApprove(Request $request, $id): JsonResponse
    {
        try {
            $otRequest = OtRequest::findOrFail($id);

            if ($otRequest->status !== 'pending_hr') {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'OT request is not awaiting HR approval.',
                ], 400);
            }

            $otRequest->update([
                'status' => 'approved',
                'hr_approved_by' => $request->user()->id ?? null,
                'hr_approved_at' => now(),
            ]);

            $this->sendOtNotification($otRequest, 'approved');

            // Send in-app notification to employee with approver's name & position
            $approver = Employee::find($request->user()->id ?? null);
            $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'HR';
            EmployeeNotification::notify(
                $otRequest->emp_id,
                'ot_approved',
                '✅ อนุมัติโอทีสำเร็จ',
                "คำขอโอทีวันที่ {$otRequest->date} เวลา {$otRequest->start_time}-{$otRequest->end_time} ได้รับการอนุมัติขั้นสุดท้ายโดย {$approverText}",
                $otRequest->id,
                'OtRequest'
            );

            $otRequest->load('employee');

            return response()->json([
                'success' => true,
                'data' => $this->formatOt($otRequest),
                'message' => 'OT request approved by HR.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'OT request not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to approve OT request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $otRequest = OtRequest::findOrFail($id);

            if (in_array($otRequest->status, ['approved', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Cannot reject an already processed OT request.',
                ], 400);
            }

            // Authorization: must be subordinate or HR admin
            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                if (!$user->isSubordinateOf($otRequest->employee_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
                }
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $otRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => $request->user()->id ?? null,
                'rejected_at' => now(),
            ]);

            $this->sendOtNotification($otRequest, 'rejected');

            // Send in-app notification to employee with approver's name & position
            $approver = Employee::find($request->user()->id ?? null);
            $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'ผู้อนุมัติ';
            EmployeeNotification::notify(
                $otRequest->emp_id,
                'ot_rejected',
                '❌ ไม่อนุมัติโอที',
                "คำขอโอทีวันที่ {$otRequest->date} เวลา {$otRequest->start_time}-{$otRequest->end_time} ไม่ได้รับการอนุมัติโดย {$approverText}" . ($otRequest->rejection_reason ? " เหตุผล: {$otRequest->rejection_reason}" : ''),
                $otRequest->id,
                'OtRequest'
            );

            $otRequest->load('employee');

            return response()->json([
                'success' => true,
                'data' => $this->formatOt($otRequest),
                'message' => 'OT request rejected successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'OT request not found.',
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
                'message' => 'Failed to reject OT request: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function formatOt(OtRequest $ot): array
    {
        $start = $ot->start_time instanceof \Carbon\Carbon
            ? $ot->start_time->setTimezone('Asia/Bangkok')->format('H:i')
            : $ot->start_time;
        $end = $ot->end_time instanceof \Carbon\Carbon
            ? $ot->end_time->setTimezone('Asia/Bangkok')->format('H:i')
            : $ot->end_time;

        return [
            'id' => $ot->id,
            'emp_id' => $ot->emp_id,
            'date' => Carbon::parse($ot->date)->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => $ot->total_hours,
            'reason' => $ot->reason,
            'status' => $ot->status,
            'rejection_reason' => $ot->rejection_reason,
            'created_at' => $ot->created_at ? Carbon::parse($ot->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'employee' => $ot->employee ? ['id' => $ot->employee->id, 'employee_code' => $ot->employee->employee_code, 'first_name' => $ot->employee->first_name, 'last_name' => $ot->employee->last_name] : null,
        ];
    }

    private function sendOtNotification(OtRequest $otRequest, string $action): void
    {
        try {
            $telegram = new TelegramService();
            $employee = $otRequest->employee;
            if (!$employee) return;

            $emoji = $action === 'approved' ? '✅' : '❌';
            $statusText = $action === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ';

            $message = "{$emoji} <b>OT {$statusText}</b>\n\n";
            $message .= "👤 <b>ชื่อ:</b> {$employee->name} ({$employee->employee_code})\n";
            $message .= "📅 <b>วันที่:</b> {$otRequest->date}\n";
            $message .= "🕐 <b>เวลา:</b> {$otRequest->start_time} - {$otRequest->end_time}\n";
            $message .= "⏱️ <b>จำนวน:</b> {$otRequest->total_hours} ชม.\n";
            if ($action === 'rejected' && $otRequest->rejection_reason) {
                $message .= "❌ <b>เหตุผล:</b> {$otRequest->rejection_reason}\n";
            }

            if ($employee->telegram_chat_id) {
                $telegram->sendToChat($employee->telegram_chat_id, $message);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
