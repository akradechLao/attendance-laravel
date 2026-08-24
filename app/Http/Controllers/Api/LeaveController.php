<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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

            $leave->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id ?? null,
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $leave->load(['employee', 'leaveType']),
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

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $leave->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => $request->user()->id ?? null,
                'rejected_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $leave->load(['employee', 'leaveType']),
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
}
