<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
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
                'employee' => $r->employee ? ['id' => $r->employee->id, 'employee_code' => $r->employee->employee_code, 'name' => $r->employee->name, 'first_name' => $r->employee->first_name, 'last_name' => $r->employee->last_name] : null,
                'supervisor' => $r->supervisor ? ['id' => $r->supervisor->id, 'name' => $r->supervisor->name, 'first_name' => $r->supervisor->first_name, 'last_name' => $r->supervisor->last_name] : null,
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

        // Employees do not block each other: each may book one Saturday per month.
        // The only date marked unavailable is the caller's own booking for the month.
        // (The remaining monthly quota is reported by my-requests.)
        $employee = $request->user();
        $ownDates = WfhRecord::where('emp_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('status', ['pending', 'approved', 'cancel_requested', 'change_requested'])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->all();

        $availableDays = [];
        $current = $start->copy();

        while ($current <= $end) {
            // Only Saturdays
            if ($current->dayOfWeek === Carbon::SATURDAY) {
                $dateStr = $current->format('Y-m-d');
                $availableDays[] = [
                    'date' => $dateStr,
                    'day' => $current->format('d'),
                    'day_name' => $current->locale('th')->isoFormat('ddd'),
                    'occupied' => in_array($dateStr, $ownDates, true),
                    'is_mine' => in_array($dateStr, $ownDates, true),
                ];
            }
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $availableDays,
        ]);
    }

    // store() removed - POST /api/wfh now routes to EmployeeRequestController::storeWfh,
    // which has this same validation plus MD-level auto-approve. Keeping two parallel
    // WFH-submission implementations is exactly how the auto-approve gap this file
    // fixes went unnoticed: one endpoint got the fix, the other silently didn't.

    public function approve(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        if (!in_array($record->status, ['pending', 'cancel_requested', 'change_requested'])) {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้ดำเนินการแล้ว',
            ], 400);
        }

        // Authorization: must be subordinate / delegated approver / HR admin
        $user = $request->user();
        if (method_exists($user, 'canApproveRequest')) {
            if (!$user->canApproveRequest($record->emp_id, 'wfh')) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        } else {
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        if ($record->status === 'cancel_requested') {
            return $this->approveCancel($request, $record);
        }

        if ($record->status === 'change_requested') {
            return $this->approveChange($request, $record);
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
                // wfh_records.supervisor_id is FK'd to admin_users, not employees - a real
                // Employee-supervisor (not HR/Admin) approving here can't be referenced.
                'supervisor_id' => $request->user() instanceof \App\Models\AdminUser ? $request->user()->id : null,
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
                    // remote_assignments.approved_by FK -> admin_users: server-side เท่านั้น (กัน client spoof)
                    'approved_by' => $request->user() instanceof AdminUser ? $request->user()->id : null,
                    'approved_at' => now(),
                ]);
            }
        });

        // Send in-app notification to employee with approver's name
        $approverText = $this->approverText($request);
        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_approved',
            '✅ อนุมัติ WFH',
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
                'employee' => $record->employee ? ['id' => $record->employee->id, 'employee_code' => $record->employee->employee_code, 'name' => $record->employee->name, 'first_name' => $record->employee->first_name, 'last_name' => $record->employee->last_name] : null,
                'supervisor' => $record->supervisor ? ['id' => $record->supervisor->id, 'name' => $record->supervisor->name, 'first_name' => $record->supervisor->first_name, 'last_name' => $record->supervisor->last_name] : null,
            ],
            'message' => 'อนุมัติ WFH สำเร็จ',
        ]);
    }

    /** อนุมัติการขอยกเลิก WFH ที่อนุมัติแล้ว → ลบ record + RemoteAssignment */
    private function approveCancel(Request $request, WfhRecord $record): JsonResponse
    {
        $wfhDate = Carbon::parse($record->date)->format('Y-m-d');
        $approverText = $this->approverText($request);

        DB::transaction(function () use ($record, $wfhDate) {
            RemoteAssignment::where('emp_id', $record->emp_id)
                ->where('start_date', $wfhDate)
                ->where('end_date', $wfhDate)
                ->where('destination', 'WFH')
                ->delete();

            $record->delete();
        });

        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_rejected',
            '🚫 ยกเลิก WFH สำเร็จ',
            "การยกเลิก WFH วันที่ {$wfhDate} ของคุณได้รับการอนุมัติโดย {$approverText}",
        );

        return response()->json([
            'success' => true,
            'message' => 'อนุมัติการยกเลิก WFH สำเร็จ',
        ]);
    }

    /** อนุมัติการขอเปลี่ยนวัน/เหตุผล → apply แล้วคงสถานะ approved */
    private function approveChange(Request $request, WfhRecord $record): JsonResponse
    {
        $newDate = $record->requested_date
            ? Carbon::parse($record->requested_date)->format('Y-m-d')
            : Carbon::parse($record->date)->format('Y-m-d');
        $oldDate = Carbon::parse($record->date)->format('Y-m-d');
        $approverText = $this->approverText($request);

        $dateConflict = WfhRecord::where('date', $newDate)
            ->whereIn('status', ['pending', 'approved', 'cancel_requested', 'change_requested'])
            ->where('emp_id', $record->emp_id)
            ->where('id', '!=', $record->id)
            ->exists();

        if ($dateConflict) {
            return response()->json([
                'success' => false,
                'message' => 'วันเสาร์นี้มีรายการใช้แล้ว',
            ], 400);
        }

        DB::transaction(function () use ($record, $newDate, $oldDate, $request) {
            $employee = $record->employee;

            $record->update([
                'date' => $newDate,
                'approved_date' => $newDate,
                'reason' => $record->requested_reason ?? $record->reason,
                'supervisor_id' => $request->user() instanceof AdminUser ? $request->user()->id : null,
                'supervisor_note' => $request->get('supervisor_note'),
                'status' => 'approved',
                'requested_date' => null,
                'requested_reason' => null,
                'requested_at' => null,
            ]);

            if ($employee) {
                // ย้าย RemoteAssignment จากวันเดิมไปวันใหม่
                if ($oldDate !== $newDate) {
                    RemoteAssignment::where('emp_id', $record->emp_id)
                        ->where('start_date', $oldDate)
                        ->where('end_date', $oldDate)
                        ->where('destination', 'WFH')
                        ->delete();
                }

                $assignment = RemoteAssignment::where('emp_id', $record->emp_id)
                    ->where('start_date', $newDate)
                    ->where('end_date', $newDate)
                    ->where('destination', 'WFH')
                    ->first();

                if ($assignment) {
                    $assignment->update(['reason' => $record->reason ?: 'ปฏิบัติงานนอกสถานที่ (WFH)']);
                } else {
                    RemoteAssignment::create([
                        'emp_id' => $record->emp_id,
                        'company_id' => $employee->company_id,
                        'start_date' => $newDate,
                        'end_date' => $newDate,
                        'destination' => 'WFH',
                        'reason' => $record->reason ?: 'ปฏิบัติงานนอกสถานที่ (WFH)',
                        'status' => 'approved',
                        'approved_by' => $request->user() instanceof AdminUser ? $request->user()->id : null,
                        'approved_at' => now(),
                    ]);
                }
            }
        });

        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_approved',
            '✅ อนุมัติการเปลี่ยนแปลง WFH',
            "การเปลี่ยน WFH เป็นวันที่ {$newDate} ได้รับการอนุมัติโดย {$approverText}",
            $record->id,
            'WfhRecord'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'date' => $newDate,
                'status' => $record->status,
            ],
            'message' => 'อนุมัติการเปลี่ยนแปลง WFH สำเร็จ',
        ]);
    }

    /** ชื่อผู้อนุมัติสำหรับข้อความแจ้งเตือน (ไม่ใช้ id จาก client — กัน spoof) */
    private function approverText(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof AdminUser) {
            return 'ผู้อนุมัติ (' . ($user->name ?? 'HR') . ')';
        }
        if ($user instanceof Employee) {
            return "คุณ {$user->name}";
        }
        return 'ผู้อนุมัติ';
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        if (!in_array($record->status, ['pending', 'cancel_requested', 'change_requested'])) {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้ดำเนินการแล้ว',
            ], 400);
        }

        // Authorization: must be subordinate / delegated approver / HR admin
        $user = $request->user();
        if (method_exists($user, 'canApproveRequest')) {
            if (!$user->canApproveRequest($record->emp_id, 'wfh')) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        } else {
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $approverText = $this->approverText($request);
        $wfhDate = Carbon::parse($record->date)->format('Y-m-d');

        // ─── ไม่อนุมัติการขอยกเลิก → คงสถานะ approved ───
        if ($record->status === 'cancel_requested') {
            $record->update([
                'supervisor_id' => $request->user() instanceof AdminUser ? $request->user()->id : null,
                'supervisor_note' => $request->get('supervisor_note', ''),
                'status' => 'approved',
                'cancel_reason' => null,
                'requested_at' => null,
            ]);

            EmployeeNotification::notify(
                $record->emp_id,
                'wfh_approved',
                'ไม่อนุมัติการยกเลิก WFH',
                "การยกเลิก WFH วันที่ {$wfhDate} ไม่ได้รับการอนุมัติโดย {$approverText}" . ($record->supervisor_note ? " เหตุผล: {$record->supervisor_note}" : ''),
                $record->id,
                'WfhRecord'
            );

            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => 'ไม่อนุมัติการยกเลิก — WFH คงเดิม',
            ]);
        }

        // ─── ไม่อนุมัติการขอเปลี่ยน → คงวันเดิม ───
        if ($record->status === 'change_requested') {
            $record->update([
                'supervisor_id' => $request->user() instanceof AdminUser ? $request->user()->id : null,
                'supervisor_note' => $request->get('supervisor_note', ''),
                'status' => 'approved',
                'requested_date' => null,
                'requested_reason' => null,
                'requested_at' => null,
            ]);

            EmployeeNotification::notify(
                $record->emp_id,
                'wfh_approved',
                'ไม่อนุมัติการเปลี่ยนแปลง WFH',
                "การเปลี่ยน WFH วันที่ {$wfhDate} ไม่ได้รับการอนุมัติโดย {$approverText} — คงวันเดิม" . ($record->supervisor_note ? " เหตุผล: {$record->supervisor_note}" : ''),
                $record->id,
                'WfhRecord'
            );

            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => 'ไม่อนุมัติการเปลี่ยนแปลง — คงวันเดิม',
            ]);
        }

        $record->update([
            // wfh_records.supervisor_id is FK'd to admin_users, not employees - a real
                // Employee-supervisor (not HR/Admin) approving here can't be referenced.
                'supervisor_id' => $request->user() instanceof \App\Models\AdminUser ? $request->user()->id : null,
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'rejected',
        ]);

        // ─── ลบ RemoteAssignment ของวัน WFH นี้ (ถ้ามี) ───
        RemoteAssignment::where('emp_id', $record->emp_id)
            ->where('start_date', $wfhDate)
            ->where('end_date', $wfhDate)
            ->where('destination', 'WFH')
            ->delete();

        EmployeeNotification::notify(
            $record->emp_id,
            'wfh_rejected',
            '❌ ไม่อนุมัติ WFH',
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

        // คำขอที่ยังไม่อนุมัติ → ยกเลิกเองได้ทันที / ที่อนุมัติแล้วต้องขอยกเลิกผ่านหัวหน้า
        if ($record->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้อนุมัติแล้ว ต้องขอยกเลิกผ่านการอนุมัติ',
            ], 400);
        }

        if (!in_array($record->status, ['pending', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้รอดำเนินการอยู่',
            ], 400);
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

    /**
     * พนักงานแก้ไขคำขอเอง:
     * - pending   → แก้ได้ทันที (ยังไม่ต้องรอใคร)
     * - approved  → ยื่นขอเปลี่ยน (status = change_requested) รอหัวหน้าอนุมัติ
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $record = WfhRecord::findOrFail($id);

        if ($record->emp_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'date' => 'sometimes|date',
            'reason' => 'sometimes|nullable|string|max:500',
        ]);

        // ─── ยังไม่อนุมัติ → แก้ได้เองทันที ───
        if ($record->status === 'pending') {
            $date = $request->has('date')
                ? Carbon::parse($request->date)->setTimezone('Asia/Bangkok')
                : Carbon::parse($record->date);

            if ($date->dayOfWeek !== Carbon::SATURDAY) {
                return response()->json(['success' => false, 'message' => 'WFH กำหนดได้เฉพาะวันเสาร์เท่านั้น'], 400);
            }

            $dateConflict = WfhRecord::where('emp_id', $record->emp_id)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->where('status', '!=', 'rejected')
                ->where('id', '!=', $record->id)
                ->exists();

            if ($dateConflict) {
                return response()->json(['success' => false, 'message' => 'คุณมีรายการ WFH ประจำเดือนนี้แล้ว'], 400);
            }

            $record->update([
                'date' => $date->format('Y-m-d'),
                'reason' => $request->has('reason') ? $request->reason : $record->reason,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['id' => $record->id, 'date' => Carbon::parse($record->date)->format('Y-m-d'), 'reason' => $record->reason, 'status' => $record->status],
                'message' => 'แก้ไขคำขอสำเร็จ',
            ]);
        }

        // ─── อนุมัติแล้ว → ยื่นขอเปลี่ยน รอหัวหน้าอนุมัติ ───
        if ($record->status === 'approved') {
            if (!$request->has('date') && !$request->has('reason')) {
                return response()->json(['success' => false, 'message' => 'ไม่มีข้อมูลที่ต้องการเปลี่ยน'], 400);
            }

            if ($request->has('date')) {
                $newDate = Carbon::parse($request->date)->setTimezone('Asia/Bangkok');

                if ($newDate->dayOfWeek !== Carbon::SATURDAY) {
                    return response()->json(['success' => false, 'message' => 'WFH กำหนดได้เฉพาะวันเสาร์เท่านั้น'], 400);
                }

                if ($newDate->toDateString() <= now()->toDateString()) {
                    return response()->json(['success' => false, 'message' => 'เปลี่ยนเป็นวันปัจจุบัน/วันย้อนหลังไม่ได้'], 400);
                }

                $dateConflict = WfhRecord::where('emp_id', $record->emp_id)
                    ->where('status', '!=', 'rejected')
                    ->where('id', '!=', $record->id)
                    ->where(function ($q) use ($newDate) {
                        $q->where('date', $newDate->format('Y-m-d'))
                          ->orWhere('requested_date', $newDate->format('Y-m-d'));
                    })
                    ->exists();

                if ($dateConflict) {
                    return response()->json(['success' => false, 'message' => 'วันเสาร์นี้มีรายการใช้แล้ว'], 400);
                }

                $record->requested_date = $newDate->format('Y-m-d');
            }

            if ($request->has('reason')) {
                $record->requested_reason = $request->reason;
            }

            if ($record->requested_date === null && $record->requested_reason === null) {
                return response()->json(['success' => false, 'message' => 'ไม่มีข้อมูลที่ต้องการเปลี่ยน'], 400);
            }

            $record->status = 'change_requested';
            $record->requested_at = now();
            $record->save();

            $employee = $record->employee;
            if ($employee) {
                $supervisorIds = method_exists($employee, 'getApproverIdsToNotify')
                    ? $employee->getApproverIdsToNotify('wfh')
                    : $employee->getSupervisorIds();
                if (!empty($supervisorIds)) {
                    $newDateText = $record->requested_date
                        ? 'เป็นวันที่ ' . Carbon::parse($record->requested_date)->format('Y-m-d')
                        : 'แก้ไขเหตุผล';
                    EmployeeNotification::notifyMultiple(
                        $supervisorIds,
                        'wfh_request',
                        'มีคำขอเปลี่ยนแปลง WFH',
                        "{$employee->name} ({$employee->employee_code}) ขอเปลี่ยน WFH วันที่ " .
                            Carbon::parse($record->date)->format('Y-m-d') . " {$newDateText}" .
                            ($record->requested_reason ? " เหตุผล: {$record->requested_reason}" : ''),
                        $record->id,
                        'WfhRecord'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $record->id,
                    'date' => Carbon::parse($record->date)->format('Y-m-d'),
                    'requested_date' => $record->requested_date ? Carbon::parse($record->requested_date)->format('Y-m-d') : null,
                    'status' => $record->status,
                ],
                'message' => 'ส่งคำขอเปลี่ยนแปลง รอหัวหน้าอนุมัติ',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'รายการนี้แก้ไขไม่ได้แล้ว',
        ], 400);
    }

    /**
     * พนักงานขอยกเลิก:
     * - pending   → ยกเลิกเองได้ทันที (ไม่ต้องรอ)
     * - approved  → ยื่นขอยกเลิก (status = cancel_requested) รอหัวหน้าอนุมัติ
     */
    public function requestCancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $record = WfhRecord::findOrFail($id);

        if ($record->emp_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // ─── ยังไม่อนุมัติ → ยกเลิกเองได้เลย ───
        if ($record->status === 'pending') {
            $wfhDate = Carbon::parse($record->date)->format('Y-m-d');
            RemoteAssignment::where('emp_id', $record->emp_id)
                ->where('start_date', $wfhDate)
                ->where('end_date', $wfhDate)
                ->where('destination', 'WFH')
                ->delete();
            $record->delete();

            return response()->json(['success' => true, 'message' => 'ยกเลิกคำขอสำเร็จ']);
        }

        // ─── อนุมัติแล้ว → ต้องรอหัวหน้าอนุมัติการยกเลิก ───
        if ($record->status === 'approved') {
            if (Carbon::parse($record->date)->toDateString() <= now()->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ผ่านวันที่แล้ว ติดต่อ HR โดยตรง',
                ], 400);
            }

            $record->status = 'cancel_requested';
            $record->cancel_reason = $request->input('reason');
            $record->requested_at = now();
            $record->save();

            $employee = $record->employee;
            if ($employee) {
                $supervisorIds = method_exists($employee, 'getApproverIdsToNotify')
                    ? $employee->getApproverIdsToNotify('wfh')
                    : $employee->getSupervisorIds();
                if (!empty($supervisorIds)) {
                    EmployeeNotification::notifyMultiple(
                        $supervisorIds,
                        'wfh_request',
                        'มีคำขอยกเลิก WFH',
                        "{$employee->name} ({$employee->employee_code}) ขอยกเลิก WFH วันที่ " .
                            Carbon::parse($record->date)->format('Y-m-d') .
                            ($record->cancel_reason ? " เหตุผล: {$record->cancel_reason}" : ''),
                        $record->id,
                        'WfhRecord'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['id' => $record->id, 'status' => $record->status],
                'message' => 'ส่งคำขอยกเลิก รอหัวหน้าอนุมัติ',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'รายการนี้รอดำเนินการอยู่',
        ], 400);
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
                'requested_date' => $r->requested_date ? Carbon::parse($r->requested_date)->format('Y-m-d') : null,
                'requested_reason' => $r->requested_reason,
                'cancel_reason' => $r->cancel_reason,
                'reason' => $r->reason,
                'supervisor_note' => $r->supervisor_note,
                'status' => $r->status,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'supervisor' => $r->supervisor ? ['id' => $r->supervisor->id, 'name' => $r->supervisor->name, 'first_name' => $r->supervisor->first_name, 'last_name' => $r->supervisor->last_name] : null,
            ]);

        // pending / approved (รวมสถานะรอเปลี่ยนแปลง) = ใช้สิทธิ์เดือนนั้น
        $used = $records->whereIn('status', ['pending', 'approved', 'cancel_requested', 'change_requested'])->count();

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
        if (method_exists($user, 'getApprovableIds')) {
            $employeeIds = $user->getApprovableIds('wfh');
        } elseif (method_exists($user, 'getAllSubordinateIds')) {
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
                'requested_date' => $r->requested_date ? Carbon::parse($r->requested_date)->format('Y-m-d') : null,
                'requested_reason' => $r->requested_reason,
                'cancel_reason' => $r->cancel_reason,
                'reason' => $r->reason,
                'supervisor_note' => $r->supervisor_note,
                'status' => $r->status,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                'employee' => $r->employee ? ['id' => $r->employee->id, 'employee_code' => $r->employee->employee_code, 'name' => $r->employee->name, 'first_name' => $r->employee->first_name, 'last_name' => $r->employee->last_name] : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}
