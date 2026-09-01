<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WfhRecord;
use App\Models\RemoteAssignment;
use App\Models\EmployeeNotification;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WfhRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WfhRecord::with(['employee', 'supervisor']);

        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            $query->where('emp_id', $user->id);
        } elseif ($request->has('emp_id') && $request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        if ($request->has('month')) {
            $month = Carbon::parse($request->month)->setTimezone('Asia/Bangkok');
            $query->whereYear('date', $month->year)
                  ->whereMonth('date', $month->month);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $records = $query->orderBy('date', 'desc')->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'emp_id' => $r->emp_id,
                'date' => Carbon::parse($r->date)->format('Y-m-d'),
                'approved_date' => $r->approved_date ? Carbon::parse($r->approved_date)->format('Y-m-d') : null,
                'reason' => $r->reason,
                'supervisor_note' => $r->supervisor_note,
                'status' => $r->status,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $r->employee ? ['id' => $r->employee->id, 'employee_code' => $r->employee->employee_code, 'first_name' => $r->employee->first_name, 'last_name' => $r->employee->last_name] : null,
                'supervisor' => $r->supervisor ? ['id' => $r->supervisor->id, 'first_name' => $r->supervisor->first_name, 'last_name' => $r->supervisor->last_name] : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function availableSaturdays(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->setTimezone('Asia/Bangkok')->format('Y-m'));
        $start = Carbon::parse($month)->setTimezone('Asia/Bangkok')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $availableDays = [];
        $current = $start->copy();

        while ($current <= $end) {
            // Only Saturdays
            if ($current->dayOfWeek === Carbon::SATURDAY) {
                $occupied = WfhRecord::where('date', $current->format('Y-m-d'))
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                $availableDays[] = [
                    'date' => $current->format('Y-m-d'),
                    'day' => $current->format('d'),
                    'day_name' => $current->locale('th')->isoFormat('ddd'),
                    'occupied' => $occupied > 0,
                ];
            }
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $availableDays,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:-30 days',
            'reason' => 'nullable|string',
        ]);

        $employee = $request->user();
        $date = Carbon::parse($request->date)->setTimezone('Asia/Bangkok');

        // Allow Saturdays only
        if ($date->dayOfWeek !== Carbon::SATURDAY) {
            return response()->json([
                'success' => false,
                'message' => 'WFH กำหนดได้เฉพาะวันเสาร์เท่านั้น',
            ], 400);
        }

        $month = $date->format('Y-m');
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

        $record = WfhRecord::create([
            'emp_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'reason' => $request->get('reason', ''),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'emp_id' => $record->emp_id,
                'date' => Carbon::parse($record->date)->format('Y-m-d'),
                'reason' => $record->reason,
                'status' => $record->status,
                'created_at' => $record->created_at ? Carbon::parse($record->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $record->employee ? ['id' => $record->employee->id, 'employee_code' => $record->employee->employee_code, 'first_name' => $record->employee->first_name, 'last_name' => $record->employee->last_name] : null,
            ],
            'message' => 'ส่งคำขอ WFH สำเร็จ รอหัวหน้าอนุมัติ',
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        if ($record->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้ดำเนินการแล้ว',
            ], 400);
        }

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($record->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $approvedDate = $request->get('approved_date', $record->date);

        $dateConflict = WfhRecord::where('date', $approvedDate)
            ->whereIn('status', ['pending', 'approved'])
            ->where('emp_id', $record->emp_id)
            ->where('id', '!=', $id)
            ->exists();

        if ($dateConflict) {
            return response()->json([
                'success' => false,
                'message' => 'วันเสาร์นี้มีคนใช้แล้ว',
            ], 400);
        }

        DB::transaction(function () use ($record, $request, $approvedDate) {
            $record->update([
                'date' => $approvedDate,
                'approved_date' => $approvedDate,
                'supervisor_id' => $request->get('supervisor_id'),
                'supervisor_note' => $request->get('supervisor_note'),
                'status' => 'approved',
            ]);

            // ─── สร้าง RemoteAssignment อัตโนมัติ เพื่อให้พนักงาน remote scan ได้ ───
            $employee = $record->employee;
            if ($employee) {
                $approvedDateStr = Carbon::parse($approvedDate)->format('Y-m-d');

                // ลบ RemoteAssignment เดิมของวันนี้ (ถ้ามี)
                RemoteAssignment::where('emp_id', $record->emp_id)
                    ->where('start_date', $approvedDateStr)
                    ->where('end_date', $approvedDateStr)
                    ->delete();

                RemoteAssignment::create([
                    'emp_id' => $record->emp_id,
                    'company_id' => $employee->company_id,
                    'start_date' => $approvedDateStr,
                    'end_date' => $approvedDateStr,
                    'destination' => 'WFH',
                    'reason' => $record->reason ?: 'ปฏิบัติงานนอกสถานที่ (WFH)',
                    'status' => 'approved',
                    'approved_by' => $request->get('supervisor_id'),
                    'approved_at' => now(),
                ]);
            }
        });

        // Send in-app notification to employee with approver's name & position
        $approverId = $request->get('supervisor_id');
        $approver = $approverId ? Employee::find($approverId) : null;
        $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'ผู้อนุมัติ';
        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_approved',
            'อนุมัติ WFH',
            "คำขอ WFH วันที่ {$approvedDate} ของคุณได้รับการอนุมัติโดย {$approverText}",
            $record->id,
            'WfhRecord'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'emp_id' => $record->emp_id,
                'date' => Carbon::parse($record->date)->format('Y-m-d'),
                'approved_date' => $record->approved_date ? Carbon::parse($record->approved_date)->format('Y-m-d') : null,
                'reason' => $record->reason,
                'supervisor_note' => $record->supervisor_note,
                'status' => $record->status,
                'employee' => $record->employee ? ['id' => $record->employee->id, 'employee_code' => $record->employee->employee_code, 'first_name' => $record->employee->first_name, 'last_name' => $record->employee->last_name] : null,
                'supervisor' => $record->supervisor ? ['id' => $record->supervisor->id, 'first_name' => $record->supervisor->first_name, 'last_name' => $record->supervisor->last_name] : null,
            ],
            'message' => 'อนุมัติ WFH สำเร็จ',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        if (!in_array($record->status, ['pending', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้ดำเนินการแล้ว',
            ], 400);
        }

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($record->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $record->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'rejected',
        ]);

        // ─── ลบ RemoteAssignment ของวัน WFH นี้ (ถ้ามี) ───
        $wfhDate = Carbon::parse($record->date)->format('Y-m-d');
        RemoteAssignment::where('emp_id', $record->emp_id)
            ->where('start_date', $wfhDate)
            ->where('end_date', $wfhDate)
            ->where('destination', 'WFH')
            ->delete();

        // Send in-app notification to employee with approver's name & position
        $approverId = $request->get('supervisor_id');
        $approver = $approverId ? Employee::find($approverId) : null;
        $approverText = $approver ? "คุณ {$approver->name} ({$approver->getPositionName()})" : 'ผู้อนุมัติ';
        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_rejected',
            'ไม่อนุมัติ WFH',
            "คำขอ WFH วันที่ {$wfhDate} ของคุณไม่ได้รับการอนุมัติโดย {$approverText}" . ($record->supervisor_note ? " เหตุผล: {$record->supervisor_note}" : ''),
            $record->id,
            'WfhRecord'
        );

        return response()->json([
            'success' => true,
            'data' => $record,
            'message' => 'ปฏิเสธคำขอ WFH',
        ]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $record = WfhRecord::findOrFail($id);

        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if ($record->emp_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }

        // ─── ลบ RemoteAssignment ของวัน WFH นี้ (ถ้ามี) ───
        $wfhDate = Carbon::parse($record->date)->format('Y-m-d');
        RemoteAssignment::where('emp_id', $record->emp_id)
            ->where('start_date', $wfhDate)
            ->where('end_date', $wfhDate)
            ->where('destination', 'WFH')
            ->delete();

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิก WFH สำเร็จ',
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employee = $request->user();
        $month = $request->get('month', now()->setTimezone('Asia/Bangkok')->format('Y-m'));

        $quota = $employee->wfh_quota ?? 1;

        $monthDate = Carbon::parse($month)->setTimezone('Asia/Bangkok');

        $records = WfhRecord::where('emp_id', $employee->id)
            ->whereYear('date', $monthDate->year)
            ->whereMonth('date', $monthDate->month)
            ->with('supervisor')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'emp_id' => $r->emp_id,
                'date' => Carbon::parse($r->date)->format('Y-m-d'),
                'approved_date' => $r->approved_date ? Carbon::parse($r->approved_date)->format('Y-m-d') : null,
                'reason' => $r->reason,
                'supervisor_note' => $r->supervisor_note,
                'status' => $r->status,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'supervisor' => $r->supervisor ? ['id' => $r->supervisor->id, 'first_name' => $r->supervisor->first_name, 'last_name' => $r->supervisor->last_name] : null,
            ]);

        $used = $records->where('status', 'approved')->count();

        return response()->json([
            'success' => true,
            'data' => $records,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'quota' => $quota,
        ]);
    }

    public function teamRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $month = $request->get('month', now()->setTimezone('Asia/Bangkok')->format('Y-m'));
        if (method_exists($user, 'getAllSubordinateIds')) {
            $employeeIds = $user->getAllSubordinateIds();
        } else {
            $employeeIds = \App\Models\Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $monthDate = Carbon::parse($month)->setTimezone('Asia/Bangkok');

        $records = WfhRecord::whereIn('emp_id', $employeeIds)
            ->whereYear('date', $monthDate->year)
            ->whereMonth('date', $monthDate->month)
            ->with('employee')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'emp_id' => $r->emp_id,
                'date' => Carbon::parse($r->date)->format('Y-m-d'),
                'approved_date' => $r->approved_date ? Carbon::parse($r->approved_date)->format('Y-m-d') : null,
                'reason' => $r->reason,
                'supervisor_note' => $r->supervisor_note,
                'status' => $r->status,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $r->employee ? ['id' => $r->employee->id, 'employee_code' => $r->employee->employee_code, 'first_name' => $r->employee->first_name, 'last_name' => $r->employee->last_name] : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}
