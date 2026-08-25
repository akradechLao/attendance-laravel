<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use App\Models\WfhRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeRequestController extends Controller
{
    public function pendingCount(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            $leaveCount = LeaveRequest::where('emp_id', $employee->id)
                ->where('status', 'pending')
                ->count();

            $otCount = OtRequest::where('emp_id', $employee->id)
                ->where('status', 'pending_manager')
                ->count();

            $wfhCount = WfhRecord::where('emp_id', $employee->id)
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'leave' => $leaveCount,
                    'ot' => $otCount,
                    'wfh' => $wfhCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeLeave(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();

            $leave = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => $leave,
                'message' => 'ส่งคำขอลาสำเร็จ',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeOt(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();

            $ot = OtRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => $ot,
                'message' => 'ส่งคำขอโอทีสำเร็จ',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeWfh(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();

            $existing = WfhRecord::where('emp_id', $employee->id)
                ->where('date', $request->date)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'วันนี้มีคำขอปฏิบัติงานนอกสถานที่แล้ว',
                ], 400);
            }

            $wfh = WfhRecord::create([
                'emp_id' => $employee->id,
                'date' => $request->date,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => $wfh,
                'message' => 'ส่งคำขอสำเร็จ',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:4|confirmed',
            ]);

            $employee = $request->user();

            if (!Hash::check($request->current_password, $employee->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านเดิมไม่ถูกต้อง',
                ], 400);
            }

            $employee->password = $request->new_password;
            $employee->save();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'เปลี่ยนรหัสผ่านสำเร็จ',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
