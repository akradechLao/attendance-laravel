<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OtRequest;
use App\Models\WfhRecord;
use App\Services\LeaveService;
use App\Constants\PositionConstants;
use Carbon\Carbon;
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
                'start_date' => 'required|date|after_or_equal:-30 days',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();
            $start = Carbon::parse($request->start_date)->setTimezone('Asia/Bangkok');
            $end = Carbon::parse($request->end_date)->setTimezone('Asia/Bangkok');
            $totalDays = $start->diffInDays($end) + 1;

            $leaveType = LeaveType::find($request->leave_type_id);
            $leaveService = app(LeaveService::class);
            $balance = $leaveService->getLeaveBalance($employee, $leaveType, $start->year);

            if ($totalDays > $balance['remaining'] && $leaveType->code !== 'unpaid') {
                return response()->json([
                    'success' => false,
                    'message' => "วันลาไม่เพียงพอ (เหลือ {$balance['remaining']} วัน)",
                ], 400);
            }

            $empLevel = PositionConstants::getLevel($employee->position);
            $isAutoApprove = $empLevel <= PositionConstants::HIERARCHY['md'];

            $leave = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'status' => $isAutoApprove ? 'approved' : 'pending',
                'supervisor_id' => $isAutoApprove ? $employee->id : null,
            ]);

            if ($isAutoApprove) {
                try {
                    $year = $start->year;
                    $leaveService->deductLeave($employee, $leaveType, $totalDays, $year);
                } catch (\Exception $e) {
                    \Log::warning('Failed to deduct leave balance on auto-approve: ' . $e->getMessage());
                }
            }

            $leave->load('leaveType');

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
                    'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name, 'code' => $leave->leaveType->code] : null,
                ],
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
                'date' => 'required|date|after_or_equal:-30 days',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();
            $empLevel = PositionConstants::getLevel($employee->position);
            $isAutoApprove = $empLevel <= PositionConstants::HIERARCHY['md'];

            $ot = OtRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'reason' => $request->reason,
                'status' => $isAutoApprove ? 'approved' : 'pending_manager',
                'approved_by' => $isAutoApprove ? $employee->id : null,
                'approved_at' => $isAutoApprove ? now() : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ot->id,
                    'emp_id' => $ot->emp_id,
                    'date' => Carbon::parse($ot->date)->format('Y-m-d'),
                    'start_time' => $ot->start_time,
                    'end_time' => $ot->end_time,
                    'reason' => $ot->reason,
                    'status' => $ot->status,
                    'created_at' => $ot->created_at ? Carbon::parse($ot->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                ],
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
                'date' => 'required|date|after_or_equal:-30 days',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();
            $date = Carbon::parse($request->date)->setTimezone('Asia/Bangkok');

            if ($date->dayOfWeek !== Carbon::SATURDAY) {
                return response()->json([
                    'success' => false,
                    'message' => 'WFH กำหนดได้เฉพาะวันเสาร์เท่านั้น',
                ], 400);
            }

            $existing = WfhRecord::where('emp_id', $employee->id)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->where('status', '!=', 'rejected')
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณมีรายการ WFH ประจำเดือนนี้แล้ว',
                ], 400);
            }

            $occupied = WfhRecord::where('date', $date->format('Y-m-d'))
                ->whereIn('status', ['pending', 'approved'])
                ->where('emp_id', '!=', $employee->id)
                ->count();

            if ($occupied > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'วันนี้มีพนักงานอื่นใช้แล้ว กรุณาเลือกวันอื่น',
                ], 400);
            }

            $empLevel = PositionConstants::getLevel($employee->position);
            $isAutoApprove = $empLevel <= PositionConstants::HIERARCHY['md'];

            $wfh = WfhRecord::create([
                'emp_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
                'reason' => $request->reason,
                'status' => $isAutoApprove ? 'approved' : 'pending',
                'supervisor_id' => $isAutoApprove ? $employee->id : null,
                'approved_date' => $isAutoApprove ? now() : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $wfh->id,
                    'emp_id' => $wfh->emp_id,
                    'date' => Carbon::parse($wfh->date)->format('Y-m-d'),
                    'reason' => $wfh->reason,
                    'status' => $wfh->status,
                    'created_at' => $wfh->created_at ? Carbon::parse($wfh->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                ],
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

            $validHash = str_starts_with((string) $employee->password, '$2y$');
            if (!$validHash || !Hash::check($request->current_password, $employee->password)) {
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
