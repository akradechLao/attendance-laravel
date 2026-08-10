<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LateForcedLeave;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $companyId = $request->get('company_id');

            $query = AttendanceLog::where('date', $date)
                ->with(['employee', 'employee.company'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->orderBy('check_in', 'desc');

            $records = $query->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'employee_id' => $log->emp_id,
                    'employee_name' => $log->employee->name ?? '-',
                    'employee_code' => $log->employee->employee_code ?? '-',
                    'company_name' => $log->employee->company->name ?? '-',
                    'date' => $log->date,
                    'check_in' => $log->check_in instanceof Carbon ? $log->check_in->format('H:i') : $log->check_in,
                    'check_out' => $log->check_out instanceof Carbon ? $log->check_out->format('H:i') : $log->check_out,
                    'original_status' => $log->original_status ?? $log->check_in_status,
                    'final_status' => $log->final_status ?? $log->original_status ?? $log->check_in_status,
                    'late_minutes' => $log->late_minutes,
                    'adjusted_by' => $log->adjustedBy->name ?? null,
                    'adjusted_at' => $log->adjusted_at ? $log->adjusted_at->format('Y-m-d H:i') : null,
                    'adjustment_note' => $log->adjustment_note,
                ];
            });

            return response()->json(['success' => true, 'data' => $records]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Failed to retrieve records: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function adjust(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'final_status' => 'required|in:on_time,late',
                'adjustment_note' => 'nullable|string|max:500',
            ]);

            $log = AttendanceLog::findOrFail($id);
            $admin = $request->user();

            $log->update([
                'final_status' => $validated['final_status'],
                'adjusted_by' => $admin->id ?? null,
                'adjusted_at' => Carbon::now(),
                'adjustment_note' => $validated['adjustment_note'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'final_status' => $log->final_status,
                    'adjusted_by' => $admin->name ?? 'Admin',
                    'adjusted_at' => $log->adjusted_at->format('Y-m-d H:i'),
                ],
                'message' => 'ปรับแก้สถานะสำเร็จ',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function forcedLeaves(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $status = $request->get('status');
            $companyId = $request->get('company_id');

            $query = LateForcedLeave::where('date', $date)
                ->with(['employee', 'employee.company', 'approvedBy'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            if ($status) {
                $query->where('status', $status);
            }

            $records = $query->orderBy('created_at', 'desc')->get()->map(function ($leave) {
                return [
                    'id' => $leave->id,
                    'employee_name' => $leave->employee->name ?? '-',
                    'employee_code' => $leave->employee->employee_code ?? '-',
                    'company_name' => $leave->employee->company->name ?? '-',
                    'date' => $leave->date,
                    'late_minutes' => $leave->late_minutes,
                    'leave_minutes' => $leave->leave_minutes,
                    'leave_type' => $leave->leave_type,
                    'status' => $leave->status,
                    'status_label' => $leave->status === 'pending' ? 'รออนุมัติ' : ($leave->status === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ'),
                    'reason' => $leave->reason,
                    'approved_by' => $leave->approvedBy->name ?? null,
                    'approved_at' => $leave->approved_at ? $leave->approved_at->format('Y-m-d H:i') : null,
                    'rejection_reason' => $leave->rejection_reason,
                ];
            });

            return response()->json(['success' => true, 'data' => $records]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function approveForcedLeave(Request $request, $id): JsonResponse
    {
        try {
            $leave = LateForcedLeave::findOrFail($id);
            if ($leave->status !== 'pending') {
                return response()->json(['success' => false, 'data' => null, 'message' => 'ไม่อยู่ในสถานะรออนุมัติ'], 400);
            }

            $admin = $request->user();
            $leave->update([
                'status' => 'approved',
                'approved_by' => $admin->id ?? null,
                'approved_at' => Carbon::now(),
            ]);

            return response()->json(['success' => true, 'data' => $leave, 'message' => 'อนุมัติลากิจบังคับสำเร็จ']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function rejectForcedLeave(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate(['rejection_reason' => 'required|string|max:500']);
            $leave = LateForcedLeave::findOrFail($id);
            if ($leave->status !== 'pending') {
                return response()->json(['success' => false, 'data' => null, 'message' => 'ไม่อยู่ในสถานะรออนุมัติ'], 400);
            }

            $admin = $request->user();
            $leave->update([
                'status' => 'rejected',
                'approved_by' => $admin->id ?? null,
                'approved_at' => Carbon::now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json(['success' => true, 'data' => $leave, 'message' => 'ไม่อนุมัติลากิจบังคับสำเร็จ']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }
}
