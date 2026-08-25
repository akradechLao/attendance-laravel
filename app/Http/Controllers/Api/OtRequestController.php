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
            $otRequests = OtRequest::with('employee')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

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
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:1000',
            ]);

            $validated['status'] = 'pending_manager';

            $otRequest = OtRequest::create($validated);

            return response()->json([
                'success' => true,
                'data' => $otRequest->load('employee'),
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

            return response()->json([
                'success' => true,
                'data' => $otRequest->load('employee'),
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

            return response()->json([
                'success' => true,
                'data' => $otRequest->load('employee'),
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

            return response()->json([
                'success' => true,
                'data' => $otRequest->load('employee'),
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
