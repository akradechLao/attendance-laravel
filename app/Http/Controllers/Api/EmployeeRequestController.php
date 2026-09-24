<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OtRequest;
use App\Models\WfhRecord;
use App\Models\EmployeeNotification;
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
            $employee = $request->user();

            $request->validate([
                'start_date' => 'required|date|after_or_equal:-30 days',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $start = Carbon::parse($request->start_date)->setTimezone('Asia/Bangkok');
            $end = Carbon::parse($request->end_date)->setTimezone('Asia/Bangkok');
            $totalDays = $start->diffInDays($end) + 1;

            $leaveType = LeaveType::find($request->leave_type_id);
            if ($leaveType->code === 'maternity') {
                if (!$employee->isFemale()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ลาคลอดสงวนสิทธิ์สำหรับพนักงานเพศหญิงเท่านั้น',
                    ], 400);
                }
                if ($leaveType->max_days > 0 && $totalDays > $leaveType->max_days) {
                    return response()->json([
                        'success' => false,
                        'message' => "ลาแบบคลอดได้สูงสุด {$leaveType->max_days} วันเท่านั้น",
                    ], 400);
                }
            }

            $hasOverlap = LeaveRequest::where('emp_id', $employee->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $request->end_date)
                ->where('end_date', '>=', $request->start_date)
                ->exists();

            if ($hasOverlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณมีคำขอลาในช่วงวันที่นี้อยู่แล้ว',
                ], 400);
            }

            $leaveType = LeaveType::find($request->leave_type_id);
            $leaveService = app(LeaveService::class);
            $balance = $leaveService->getLeaveBalance($employee, $leaveType, $start->year);

            if ($totalDays > $balance['remaining'] && $leaveType->code !== 'unpaid') {
                return response()->json([
                    'success' => false,
                    'message' => "วันลาไม่เพียงพอ (เหลือ {$balance['remaining']} วัน)",
                ], 400);
            }

            $empLevel = PositionConstants::getLevel($employee->position_level);
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

                // Notify employee of auto-approval
                EmployeeNotification::notify(
                    $employee->id,
                    'leave_approved',
                    'อนุมัติลางานอัตโนมัติ',
                    "คำขอลาของคุณ ({$leaveType->name} {$request->start_date} ถึง {$request->end_date}) ได้รับการอนุมัติอัตโนมัติ",
                    $leave->id,
                    'LeaveRequest'
                );
            } else {
                // Notify supervisor(s) of new leave request
                $supervisorIds = method_exists($employee, 'getApproverIdsToNotify')
                    ? $employee->getApproverIdsToNotify('leave')
                    : $employee->getSupervisorIds();
                if (!empty($supervisorIds)) {
                    EmployeeNotification::notifyMultiple(
                        $supervisorIds,
                        'leave_request',
                        'มีคำขอลาใหม่',
                        "{$employee->name} ({$employee->employee_code}) ขอลา {$leaveType->name} วันที่ {$request->start_date} ถึง {$request->end_date}" . ($request->reason ? " เหตุผล: {$request->reason}" : ''),
                        $leave->id,
                        'LeaveRequest'
                    );
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
                'start_date' => 'required|date|after_or_equal:-30 days',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'reason' => 'nullable|string',
            ]);

            $employee = $request->user();

            if (!$employee->has_ot) {
                return response()->json(['success' => false, 'message' => 'พนักงานไม่มีสิทธิ์ทำโอที'], 403);
            }
            if (PositionConstants::isTopManagement($employee->position_level)) {
                return response()->json(['success' => false, 'message' => 'ตำแหน่งนี้ไม่มีสิทธิ์ขอโอที'], 403);
            }

            $startDateTime = Carbon::parse($request->start_date)->setTime(
                Carbon::parse($request->start_time)->hour,
                Carbon::parse($request->start_time)->minute
            );
            $endDateTime = Carbon::parse($request->end_date)->setTime(
                Carbon::parse($request->end_time)->hour,
                Carbon::parse($request->end_time)->minute
            );

            if ($startDateTime >= $endDateTime) {
                return response()->json([
                    'success' => false,
                    'message' => 'เวลาสิ้นสุดต้องหลังกว่าเวลาเริ่มต้น',
                ], 422);
            }

            $totalOtMinutes = $startDateTime->diffInMinutes($endDateTime);
            $totalHours = $totalOtMinutes > 0 ? round($totalOtMinutes / 60, 2) : 0;

            $hasOverlap = OtRequest::where('emp_id', $employee->id)
                ->where('date', '<=', $request->end_date)
                ->where('end_date', '>=', $request->start_date)
                ->where('status', '!=', 'rejected')
                ->exists();

            if ($hasOverlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณมีคำขอโอทีในช่วงเวลานี้อยู่แล้ว',
                ], 400);
            }

            $ot = OtRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'total_hours' => $totalHours,
                'reason' => $request->reason,
                'status' => 'pending_manager',
            ]);

            $notifyStart = Carbon::parse($request->start_date)->setTime(
                Carbon::parse($request->start_time)->hour,
                Carbon::parse($request->start_time)->minute
            );
            $notifyEnd = Carbon::parse($request->end_date)->setTime(
                Carbon::parse($request->end_time)->hour,
                Carbon::parse($request->end_time)->minute
            );

            // Notify supervisor(s) of new OT request
            $supervisorIds = method_exists($employee, 'getApproverIdsToNotify')
                ? $employee->getApproverIdsToNotify('ot')
                : $employee->getSupervisorIds();
            if (!empty($supervisorIds)) {
                EmployeeNotification::notifyMultiple(
                    $supervisorIds,
                    'ot_request',
                    'มีคำขอโอทีใหม่',
                    "{$employee->name} ({$employee->employee_code}) ขอโอที ตั้งแต่ {$request->start_date} {$request->start_time} ถึง {$request->end_date} {$request->end_time}" . ($request->reason ? " เหตุผล: {$request->reason}" : ''),
                    $ot->id,
                    'OtRequest'
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ot->id,
                    'emp_id' => $ot->emp_id,
                    'date' => Carbon::parse($ot->date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($ot->end_date)->format('Y-m-d'),
                    'start_time' => $ot->start_time,
                    'end_time' => $ot->end_time,
                    'total_hours' => $ot->total_hours,
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

            $empLevel = PositionConstants::getLevel($employee->position_level);
            $isAutoApprove = $empLevel <= PositionConstants::HIERARCHY['md'];

            $wfh = WfhRecord::create([
                'emp_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
                'reason' => $request->reason,
                'status' => $isAutoApprove ? 'approved' : 'pending',
                // wfh_records.supervisor_id is FK'd to admin_users, not employees -
                // can't reference the employee themselves here even on self-approval.
                'supervisor_id' => null,
                'supervisor_note' => $isAutoApprove ? 'อนุมัติอัตโนมัติ (ผู้บริหารระดับสูง)' : null,
                'approved_date' => $isAutoApprove ? now() : null,
            ]);

            // ─── ถ้า auto-approved ให้สร้าง RemoteAssignment อัตโนมัติ ───
            if ($isAutoApprove) {
                \App\Models\RemoteAssignment::create([
                    'emp_id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'start_date' => $date->format('Y-m-d'),
                    'end_date' => $date->format('Y-m-d'),
                    'destination' => 'WFH',
                    'reason' => $request->reason ?: 'ปฏิบัติงานนอกสถานที่ (WFH)',
                    'status' => 'approved',
                    // remote_assignments.approved_by is FK'd to admin_users, not
                    // employees - same reasoning as supervisor_id above.
                    'approved_by' => null,
                    'approved_at' => now(),
                ]);

                // Notify employee of auto-approval
                EmployeeNotification::notify(
                    $employee->id,
                    'wfh_approved',
                    'อนุมัติ WFH อัตโนมัติ',
                    "คำขอ WFH ของคุณวันที่ {$date->format('Y-m-d')} ได้รับการอนุมัติอัตโนมัติ",
                    $wfh->id,
                    'WfhRecord'
                );
            } else {
                // Notify supervisor(s) of new WFH request
                $supervisorIds = method_exists($employee, 'getApproverIdsToNotify')
                    ? $employee->getApproverIdsToNotify('wfh')
                    : $employee->getSupervisorIds();
                if (!empty($supervisorIds)) {
                    EmployeeNotification::notifyMultiple(
                        $supervisorIds,
                        'wfh_request',
                        'มีคำขอ WFH ใหม่',
                        "{$employee->name} ({$employee->employee_code}) ขอ WFH วันที่ {$date->format('Y-m-d')}" . ($request->reason ? " เหตุผล: {$request->reason}" : ''),
                        $wfh->id,
                        'WfhRecord'
                    );
                }
            }

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
