<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtRequest;
use App\Models\Employee;
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
            $message .= "👤 <b>ชื่อ:</b> {$employee->name}\n";
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
