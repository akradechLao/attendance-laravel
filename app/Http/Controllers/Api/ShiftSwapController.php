<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwap;
use App\Models\WorkShift;
use App\Models\LeaveRequest;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShiftSwap::with(['requester', 'target', 'supervisor']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('supervisor_id') && $request->supervisor_id) {
            $supervisor = Employee::find($request->supervisor_id);
            if ($supervisor) {
                $employeeIds = $supervisor->getAllSubordinateIds();
                $employeeIds[] = $supervisor->id;
                $query->whereIn('requester_id', $employeeIds);
            }
        }

        $swaps = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requester_id' => 'required|exists:employees,id',
            'target_id' => 'required|exists:employees,id',
            'swap_date' => 'required|date',
            'requester_shift' => 'required|string',
            'target_shift' => 'required|string',
            'reason' => 'nullable|string',
            'request_replacement_day' => 'nullable|boolean',
        ]);

        if ($validated['requester_id'] == $validated['target_id']) {
            return response()->json(['success' => false, 'message' => 'ไม่สามารถสลับกับตัวเองได้'], 400);
        }

        $requestReplacementDay = $validated['request_replacement_day'] ?? false;
        unset($validated['request_replacement_day']);

        $swap = ShiftSwap::create($validated);

        // Store replacement day flag in supervisor_note temporarily
        if ($requestReplacementDay) {
            $swap->update(['supervisor_note' => 'REQUEST_REPLACEMENT_DAY']);
        }

        return response()->json([
            'success' => true,
            'data' => $swap->load(['requester', 'target']),
            'message' => 'ส่งคำขอสลับกะสำเร็จ',
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $swap = ShiftSwap::findOrFail($id);

        if ($swap->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'รายการนี้ดำเนินการแล้ว'], 400);
        }

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            $approver = $user->employee ?? Employee::find($user->id);
            if (!$approver || (!$approver->isSubordinateOf($swap->requester_id) && !$approver->isSubordinateOf($swap->target_id))) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        // Actually swap the shift_schedules
        $requesterSchedule = ShiftSchedule::where('emp_id', $swap->requester_id)
            ->where('work_date', $swap->swap_date)
            ->first();
        $targetSchedule = ShiftSchedule::where('emp_id', $swap->target_id)
            ->where('work_date', $swap->swap_date)
            ->first();

        if ($requesterSchedule && $targetSchedule) {
            $tmpCode = $requesterSchedule->shift_code;
            $tmpType = $requesterSchedule->day_type;
            $requesterSchedule->update([
                'shift_code' => $targetSchedule->shift_code,
                'day_type' => $targetSchedule->day_type,
            ]);
            $targetSchedule->update([
                'shift_code' => $tmpCode,
                'day_type' => $tmpType,
            ]);
        }

        $swap->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'approved',
        ]);

        // Create replacement day off leave request if requested
        if ($swap->supervisor_note === 'REQUEST_REPLACEMENT_DAY') {
            $leaveDate = Carbon::parse($swap->swap_date);
            LeaveRequest::create([
                'emp_id' => $swap->requester_id,
                'leave_type_id' => 1,
                'start_date' => $leaveDate->format('Y-m-d'),
                'end_date' => $leaveDate->format('Y-m-d'),
                'total_days' => 1,
                'reason' => 'วันหยุดทดแทนจากการสลับกะ ' . $swap->swap_date,
                'status' => 'approved',
                'approved_by' => $request->user()->id ?? null,
                'approved_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $swap,
            'message' => 'อนุมัติสลับกะสำเร็จ',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $swap = ShiftSwap::findOrFail($id);

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            $approver = $user->employee ?? Employee::find($user->id);
            if (!$approver || (!$approver->isSubordinateOf($swap->requester_id) && !$approver->isSubordinateOf($swap->target_id))) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $swap->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'rejected',
        ]);

        return response()->json([
            'success' => true,
            'data' => $swap,
            'message' => 'ปฏิเสธคำขอสลับกะ',
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $swaps = ShiftSwap::where('requester_id', $employee->id)
            ->orWhere('target_id', $employee->id)
            ->with(['requester', 'target', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function teamSwaps(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee ?? Employee::find($user->id);

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $employeeIds = $employee->getAllSubordinateIds();
        $employeeIds[] = $employee->id;

        $swaps = ShiftSwap::whereIn('requester_id', $employeeIds)
            ->with(['requester', 'target'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function availableEmployees(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $date = $request->get('date');
        if (!$date) {
            return response()->json(['success' => false, 'message' => 'กรุณาระบุวันที่'], 400);
        }

        // Get employee's own schedule for this date
        $mySchedule = ShiftSchedule::where('emp_id', $employee->id)
            ->where('work_date', $date)
            ->first();

        $myShiftCode = $mySchedule ? $mySchedule->shift_code : null;

        // Get all employees in same company who have a schedule on this date (exclude self)
        $schedules = ShiftSchedule::where('company_id', $employee->company_id)
            ->where('work_date', $date)
            ->where('emp_id', '!=', $employee->id)
            ->with('employee:id,name,employee_code,nickname')
            ->get()
            ->filter(fn($s) => $s->employee);

        $available = $schedules->map(fn($s) => [
            'id' => $s->employee->id,
            'name' => $s->employee->name,
            'nickname' => $s->employee->nickname,
            'employee_code' => $s->employee->employee_code,
            'shift_code' => $s->shift_code,
            'shift_label' => $this->getShiftLabel($s->shift_code),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'my_schedule' => $mySchedule ? [
                    'shift_code' => $mySchedule->shift_code,
                    'shift_label' => $this->getShiftLabel($mySchedule->shift_code),
                    'day_type' => $mySchedule->day_type,
                ] : null,
                'available_employees' => $available,
            ],
        ]);
    }

    private function getShiftLabel(string $code): string
    {
        $labels = [
            'WC0001' => '07:30-16:30',
            'WC0002' => '08:00-17:00',
            'WC0003' => '16:00-01:00',
            'WC0004' => '00:00-09:00',
            'WC0005' => '09:00-18:00',
            'WC0006' => '20:00-05:00',
            'WC007'  => '21:00-06:00',
            'WC008'  => '08:00-16:30',
            'WC009'  => '16:00-00:30',
            'WC010'  => '00:00-08:30',
            'WC011'  => '08:00-20:00',
            'WC012'  => '20:00-08:00',
            'WC013'  => '16:00-00:00',
            'WC014'  => '00:00-08:00',
            'WC015'  => '07:00-16:00',
            'WC016'  => '19:00-04:00',
        ];
        return $labels[$code] ?? $code;
    }
}
