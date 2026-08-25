<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OtRequest;
use App\Models\ShiftSchedule;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualEntryController extends Controller
{
    // ============================================================
    // ATTENDANCE
    // ============================================================

    public function attendanceIndex(Request $request): JsonResponse
    {
        $query = AttendanceLog::with('employee:id,name,employee_code,department');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }
        if ($request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->department) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $logs = $query->orderBy('date', 'desc')->orderBy('check_in', 'desc')
            ->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function attendanceStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'required|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i|after:check_in',
            'check_in_status' => 'nullable|in:on_time,late,early',
            'note' => 'nullable|string|max:500',
        ]);

        $employee = Employee::find($validated['emp_id']);
        $existing = AttendanceLog::where('emp_id', $validated['emp_id'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'มีข้อมูลเข้างานวันนี้แล้ว กรุณาแก้ไขแทน',
            ], 400);
        }

        $log = AttendanceLog::create([
            'emp_id' => $validated['emp_id'],
            'date' => $validated['date'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'] ?? null,
            'check_in_status' => $validated['check_in_status'] ?? 'on_time',
            'scan_type' => 'manual',
            'adjusted_by' => $request->user()->id,
            'adjusted_at' => now(),
            'adjustment_note' => $validated['note'] ?? 'บันทึกโดย HR (Manual Entry)',
            'is_verified' => true,
        ]);

        AuditLogService::created($log, $request);

        return response()->json([
            'success' => true,
            'data' => $log->load('employee'),
            'message' => 'บันทึกเข้างานสำเร็จ',
        ], 201);
    }

    public function attendanceUpdate(Request $request, $id): JsonResponse
    {
        $log = AttendanceLog::findOrFail($id);

        $validated = $request->validate([
            'check_in' => 'sometimes|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'check_in_status' => 'nullable|in:on_time,late,early',
            'note' => 'nullable|string|max:500',
        ]);

        $log->update([
            'check_in' => $validated['check_in'] ?? $log->check_in,
            'check_out' => $validated['check_out'] ?? $log->check_out,
            'check_in_status' => $validated['check_in_status'] ?? $log->check_in_status,
            'adjusted_by' => $request->user()->id,
            'adjusted_at' => now(),
            'adjustment_note' => $validated['note'] ?? $log->adjustment_note,
        ]);

        return response()->json([
            'success' => true,
            'data' => $log->load('employee'),
            'message' => 'แก้ไขข้อมูลเข้างานสำเร็จ',
        ]);
    }

    public function attendanceDestroy($id): JsonResponse
    {
        $log = AttendanceLog::findOrFail($id);
        AuditLogService::deleted($log);
        $log->delete();

        return response()->json(['success' => true, 'message' => 'ลบข้อมูลเข้างานสำเร็จ']);
    }

    // ============================================================
    // OVERTIME
    // ============================================================

    public function otIndex(Request $request): JsonResponse
    {
        $query = OtRequest::with('employee:id,name,employee_code,department');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }
        if ($request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->department) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $ots = $query->orderBy('date', 'desc')
            ->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $ots]);
    }

    public function otStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,approved',
        ]);

        $employee = Employee::find($validated['emp_id']);

        $ot = OtRequest::create([
            'company_id' => $employee->company_id,
            'emp_id' => $validated['emp_id'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'reason' => $validated['reason'] ?? 'บันทึกโดย HR (Manual Entry)',
            'status' => $validated['status'] ?? 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        AuditLogService::created($ot, $request);

        return response()->json([
            'success' => true,
            'data' => $ot->load('employee'),
            'message' => 'บันทึก OT สำเร็จ',
        ], 201);
    }

    public function otUpdate(Request $request, $id): JsonResponse
    {
        $ot = OtRequest::findOrFail($id);

        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'reason' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,approved,rejected',
        ]);

        $ot->update($validated);

        return response()->json([
            'success' => true,
            'data' => $ot->load('employee'),
            'message' => 'แก้ไขข้อมูล OT สำเร็จ',
        ]);
    }

    public function otDestroy($id): JsonResponse
    {
        $ot = OtRequest::findOrFail($id);
        AuditLogService::deleted($ot);
        $ot->delete();

        return response()->json(['success' => true, 'message' => 'ลบข้อมูล OT สำเร็จ']);
    }

    // ============================================================
    // SHIFT SCHEDULE
    // ============================================================

    public function shiftIndex(Request $request): JsonResponse
    {
        $query = ShiftSchedule::with('employee:id,name,employee_code,department');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }
        if ($request->date_from) {
            $query->where('work_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('work_date', '<=', $request->date_to);
        }
        if ($request->department) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $shifts = $query->orderBy('work_date', 'desc')
            ->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $shifts]);
    }

    public function shiftStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'work_date' => 'required|date',
            'shift_code' => 'required|string',
            'day_type' => 'nullable|in:working,holiday,day_off',
        ]);

        $employee = Employee::find($validated['emp_id']);
        $existing = ShiftSchedule::where('emp_id', $validated['emp_id'])
            ->where('work_date', $validated['work_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'มีข้อมูลกะวันนี้แล้ว กรุณาแก้ไขแทน',
            ], 400);
        }

        $shift = ShiftSchedule::create([
            'company_id' => $employee->company_id,
            'emp_id' => $validated['emp_id'],
            'work_date' => $validated['work_date'],
            'shift_code' => $validated['shift_code'],
            'day_type' => $validated['day_type'] ?? 'working',
        ]);

        AuditLogService::created($shift, $request);

        return response()->json([
            'success' => true,
            'data' => $shift->load('employee'),
            'message' => 'บันทึกกะสำเร็จ',
        ], 201);
    }

    public function shiftUpdate(Request $request, $id): JsonResponse
    {
        $shift = ShiftSchedule::findOrFail($id);

        $validated = $request->validate([
            'shift_code' => 'sometimes|string',
            'day_type' => 'nullable|in:working,holiday,day_off',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'data' => $shift->load('employee'),
            'message' => 'แก้ไขกะสำเร็จ',
        ]);
    }

    public function shiftDestroy($id): JsonResponse
    {
        $shift = ShiftSchedule::findOrFail($id);
        AuditLogService::deleted($shift);
        $shift->delete();

        return response()->json(['success' => true, 'message' => 'ลบข้อมูลกะสำเร็จ']);
    }

    // ============================================================
    // LEAVE
    // ============================================================

    public function leaveIndex(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employee:id,name,employee_code,department', 'leaveType:id,name,code']);

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }
        if ($request->date_from) {
            $query->where('start_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('end_date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->department) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $leaves = $query->orderBy('start_date', 'desc')
            ->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $leaves]);
    }

    public function leaveStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,approved',
        ]);

        $employee = Employee::find($validated['emp_id']);
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;

        $leave = LeaveRequest::create([
            'company_id' => $employee->company_id,
            'emp_id' => $validated['emp_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'] ?? 'บันทึกโดย HR (Manual Entry)',
            'status' => $validated['status'] ?? 'approved',
            'supervisor_id' => $request->user()->id,
            'supervisor_note' => 'บันทึกโดย HR (Manual Entry)',
        ]);

        AuditLogService::created($leave, $request);

        return response()->json([
            'success' => true,
            'data' => $leave->load(['employee', 'leaveType']),
            'message' => 'บันทึกลาสำเร็จ',
        ], 201);
    }

    public function leaveUpdate(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,approved,rejected',
        ]);

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $start = Carbon::parse($validated['start_date'] ?? $leave->start_date);
            $end = Carbon::parse($validated['end_date'] ?? $leave->end_date);
            $validated['total_days'] = $start->diffInDays($end) + 1;
        }

        $leave->update($validated);

        return response()->json([
            'success' => true,
            'data' => $leave->load(['employee', 'leaveType']),
            'message' => 'แก้ไขข้อมูลลาสำเร็จ',
        ]);
    }

    public function leaveDestroy($id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        AuditLogService::deleted($leave);
        $leave->delete();

        return response()->json(['success' => true, 'message' => 'ลบข้อมูลลาสำเร็จ']);
    }
}
