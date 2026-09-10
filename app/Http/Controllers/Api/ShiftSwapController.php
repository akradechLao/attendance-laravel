<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwap;
use App\Models\WorkShift;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Constants\RoleConstants;
use App\Constants\PositionConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShiftSwap::with(['requester:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'target:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id']);

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
            'target_id' => 'required|exists:employees,id',
            'swap_date' => 'required|date',
            'requester_shift' => 'required|string',
            'target_shift' => 'required|string',
            'reason' => 'nullable|string',
            'request_replacement_day' => 'nullable|boolean',
        ]);

        $employee = $request->user();
        $validated['requester_id'] = $employee->id;

        if ($validated['requester_id'] == $validated['target_id']) {
            return response()->json(['success' => false, 'message' => 'ไม่สามารถสลับกับตัวเองได้'], 400);
        }

        // Assistant MD-and-above don't work fixed shifts, so there is nothing
        // for them to swap - the frontend already hides this page for them,
        // but the API must not rely on that alone.
        if (PositionConstants::isTopManagement($employee->position)) {
            return response()->json(['success' => false, 'message' => 'ตำแหน่งนี้ไม่มีสิทธิ์ขอสลับเวร'], 403);
        }

        $swap = ShiftSwap::create($validated);

        return response()->json([
            'success' => true,
            'data' => $swap->load(['requester:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'target:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id']),
            'message' => 'ส่งคำขอสลับกะสำเร็จ',
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $swap = ShiftSwap::findOrFail($id);

        if ($swap->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'รายการนี้ดำเนินการแล้ว'], 400);
        }

        // Authorization: must be subordinate or HR admin of the same company
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($swap->requester_id) && !$user->isSubordinateOf($swap->target_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        } elseif ($swap->requester?->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden: cross-company access denied'], 403);
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
        if ($swap->request_replacement_day) {
            $requester = Employee::find($swap->requester_id);
            // ลากิจ (code "personal") is the closest existing leave type to a
            // compensatory day off, and leave_types is scoped per company - there is
            // no single "leave_type_id 1" that's valid for every company.
            $leaveType = $requester
                ? LeaveType::where('company_id', $requester->company_id)->where('code', 'personal')->first()
                : null;

            if ($requester && $leaveType) {
                $leaveDate = Carbon::parse($swap->swap_date);
                LeaveRequest::create([
                    'company_id' => $requester->company_id,
                    'emp_id' => $requester->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $leaveDate->format('Y-m-d'),
                    'end_date' => $leaveDate->format('Y-m-d'),
                    'total_days' => 1,
                    'reason' => 'วันหยุดทดแทนจากการสลับกะ ' . $leaveDate->format('d/m/Y'),
                    'status' => 'approved',
                ]);

                // Deduct leave balance
                $leaveService = app(LeaveService::class);
                $leaveService->deductLeave($requester, $leaveType, 1, $leaveDate->year);
            }
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

        // Authorization: must be subordinate or HR admin of the same company
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($swap->requester_id) && !$user->isSubordinateOf($swap->target_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        } elseif ($swap->requester?->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden: cross-company access denied'], 403);
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
        $employee = $request->user();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $swaps = ShiftSwap::where('requester_id', $employee->id)
            ->orWhere('target_id', $employee->id)
            ->with(['requester:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'target:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function teamSwaps(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if (method_exists($user, 'getAllSubordinateIds')) {
            $employeeIds = $user->getAllSubordinateIds();
            $employeeIds[] = $user->id;
        } else {
            $employeeIds = \App\Models\Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        $swaps = ShiftSwap::whereIn('requester_id', $employeeIds)
            ->with(['requester:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'target:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function availableEmployees(Request $request): JsonResponse
    {
        $employee = $request->user();
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

        // Get all employees in same company who have a schedule on this date (exclude self).
        // Top management shouldn't appear as swap partners even if a schedule was
        // mistakenly assigned to one - they don't work fixed shifts.
        $schedules = ShiftSchedule::where('company_id', $employee->company_id)
            ->where('work_date', $date)
            ->where('emp_id', '!=', $employee->id)
            ->with('employee:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id')
            ->get()
            ->filter(fn($s) => $s->employee && !PositionConstants::isTopManagement($s->employee->position));

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
        $label = \App\Helpers\ShiftCodeHelper::getLabel($code);
        return $label !== $code ? $label : $code;
    }
}
