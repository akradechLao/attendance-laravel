<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupervisorShiftController extends Controller
{
    /**
     * GET /api/supervisor/shift-assign/team
     * Get team members with their available shifts + current assignment
     */
    public function team(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $isSuperAdmin = $user->role === 'super_admin';
        $isAdmin = in_array($user->role, ['admin', 'super_admin']);
        $subordinateIds = !$isAdmin && method_exists($user, 'getAllSubordinateIds') ? $user->getAllSubordinateIds() : [];

        $now = Carbon::now('Asia/Bangkok');
        $currentCycleStart = $now->copy()->startOfMonth()->addDays(18); // 19th of current month
        $nextMonth = $now->copy()->addMonth();
        $nextCycleEnd = $nextMonth->copy()->startOfMonth()->addDays(17); // 18th of next month

        // Cycle: 19th this month - 18th next month
        // Allow assignment until the 19th (inclusive) — last day before new cycle starts
        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $nextMonth->copy()->startOfMonth()->addDays(17)->toDateString();
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17)->toDateString();
        }

        $employeeQuery = Employee::where('is_active', true)
            ->select('id', 'employee_code', 'name', 'department', 'division', 'company_id')
            ->with(['workShifts' => function ($q) {
                $q->select('work_shifts.id', 'work_shifts.group_number', 'work_shifts.start_time', 'work_shifts.end_time', 'work_shifts.work_hours');
            }]);

        if ($isSuperAdmin) {
            // SuperAdmin: see all employees
        } elseif ($isAdmin && $user->company_id) {
            // Admin: see employees from same company
            $employeeQuery->where('company_id', $user->company_id);
        } elseif (!empty($subordinateIds)) {
            // Supervisor: see subordinates only
            $employeeQuery->whereIn('id', $subordinateIds);
        } else {
            return response()->json(['data' => []]);
        }

        $employees = $employeeQuery->orderBy('employee_code')->get();

        // Filter: only employees with > 1 available shift (single-shift employees don't need assignment)
        $employees = $employees->filter(function ($emp) {
            return $emp->workShifts->count() > 1;
        })->values();

        $employees->each(function ($emp) use ($cycleStart, $cycleEnd) {
            // Get current assignment for this cycle
            $assignment = ShiftSchedule::where('emp_id', $emp->id)
                ->where('work_date', '>=', $cycleStart)
                ->where('work_date', '<=', $cycleEnd)
                ->first();

            $emp->current_assignment = $assignment ? [
                'shift_code' => $assignment->shift_code,
                'work_date' => $assignment->work_date,
            ] : null;

            // Format available shifts
            $emp->available_shifts = $emp->workShifts->map(function ($ws) {
                $code = 'WC' . str_pad($ws->group_number + 1, 4, '0', STR_PAD_LEFT);
                return [
                    'work_shift_id' => $ws->id,
                    'shift_code' => $code,
                    'group_number' => $ws->group_number,
                    'start_time' => $ws->start_time instanceof Carbon ? $ws->start_time->format('H:i') : substr($ws->start_time, 0, 5),
                    'end_time' => $ws->end_time instanceof Carbon ? $ws->end_time->format('H:i') : substr($ws->end_time, 0, 5),
                    'work_hours' => $ws->work_hours,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $employees,
            'cycle' => [
                'start' => $cycleStart,
                'end' => $cycleEnd,
                'can_assign' => $now->day <= 19,
                'message' => $now->day <= 19
                    ? 'กำหนดส่ง: ภายในวันที่ 18'
                    : 'เลยกำหนดแล้ว (วันที่ 20-สิ้นเดือน)',
                    : 'เลยกำหนดแล้ว (วันที่ 19-สิ้นเดือน)',
            ],
        ]);
    }

    /**
     * POST /api/supervisor/shift-assign
     * Assign a shift to an employee for the current cycle
     */
    public function assign(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_shift_id' => 'required|exists:work_shifts,id',
        ]);

        $employeeId = $request->employee_id;
        $workShiftId = $request->work_shift_id;

        // Verify the employee is a subordinate (admin/super_admin can assign anyone in their company)
        $isAdmin = in_array($user->role, ['admin', 'super_admin']);
        if (!$isAdmin) {
            $subordinateIds = method_exists($user, 'getAllSubordinateIds') ? $user->getAllSubordinateIds() : [];
            if (!in_array($employeeId, $subordinateIds)) {
                return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์assign กะให้พนักงานคนนี้'], 403);
            }
        }

        // Verify the work_shift is in employee's available shifts
        $employee = Employee::find($employeeId);
        $availableShiftIds = $employee->workShifts->pluck('id')->toArray();
        if (!in_array($workShiftId, $availableShiftIds)) {
            return response()->json(['success' => false, 'message' => 'พนักงานคนนี้ไม่มีกะนี้ใน list ที่เลือกได้'], 400);
        }

        // Calculate cycle dates
        $now = Carbon::now('Asia/Bangkok');
        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->addMonth()->startOfMonth()->addDays(17);
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17);
        }

        $shiftCode = 'WC' . str_pad($employee->workShifts->where('id', $workShiftId)->first()->group_number + 1, 4, '0', STR_PAD_LEFT);

        // Delete existing assignments for this cycle
        ShiftSchedule::where('emp_id', $employeeId)
            ->where('work_date', '>=', $cycleStart->toDateString())
            ->where('work_date', '<=', $cycleEnd->toDateString())
            ->delete();

        // Create new assignments for each day in the cycle
        $current = $cycleStart->copy();
        $records = [];
        while ($current->lte($cycleEnd)) {
            // Skip Sundays
            if ($current->dayOfWeek !== Carbon::SUNDAY) {
                $records[] = [
                    'company_id' => $employee->company_id,
                    'emp_id' => $employeeId,
                    'work_date' => $current->toDateString(),
                    'shift_code' => $shiftCode,
                    'day_type' => 'working',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $current->addDay();
        }

        if (!empty($records)) {
            ShiftSchedule::insert($records);
        }

        return response()->json([
            'success' => true,
            'message' => "assign กะ {$shiftCode} สำเร็จ ({$cycleStart->format('d/m')} - {$cycleEnd->format('d/m')})",
            'data' => [
                'employee_id' => $employeeId,
                'shift_code' => $shiftCode,
                'cycle_start' => $cycleStart->toDateString(),
                'cycle_end' => $cycleEnd->toDateString(),
                'days_created' => count($records),
            ],
        ]);
    }

    /**
     * DELETE /api/supervisor/shift-assign/{employeeId}
     * Remove shift assignment for an employee
     */
    public function remove(Request $request, int $employeeId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $isAdmin = in_array($user->role, ['admin', 'super_admin']);
        if (!$isAdmin) {
            $subordinateIds = method_exists($user, 'getAllSubordinateIds') ? $user->getAllSubordinateIds() : [];
            if (!in_array($employeeId, $subordinateIds)) {
                return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบกะของพนักงานคนนี้'], 403);
            }
        }

        $now = Carbon::now('Asia/Bangkok');
        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $now->copy()->addMonth()->startOfMonth()->addDays(17)->toDateString();
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17)->toDateString();
        }

        $deleted = ShiftSchedule::where('emp_id', $employeeId)
            ->where('work_date', '>=', $cycleStart)
            ->where('work_date', '<=', $cycleEnd)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "ลบกะสำเร็จ ({$deleted} วัน)",
        ]);
    }

    /**
     * GET /api/supervisor/shift-assign/summary
     * Get summary of assignments for current cycle
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $isSuperAdmin = $user->role === 'super_admin';
        $isAdmin = in_array($user->role, ['admin', 'super_admin']);
        $subordinateIds = !$isAdmin && method_exists($user, 'getAllSubordinateIds') ? $user->getAllSubordinateIds() : [];

        $now = Carbon::now('Asia/Bangkok');
        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $now->copy()->addMonth()->startOfMonth()->addDays(17)->toDateString();
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18)->toDateString();
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17)->toDateString();
        }

        // Get employees based on role
        $empQuery = Employee::where('is_active', true)
            ->select('id', 'employee_code', 'name')
            ->with('workShifts:id');

        if ($isSuperAdmin) {
            // SuperAdmin: see all
        } elseif ($isAdmin && $user->company_id) {
            $empQuery->where('company_id', $user->company_id);
        } elseif (!empty($subordinateIds)) {
            $empQuery->whereIn('id', $subordinateIds);
        } else {
            return response()->json(['data' => ['assigned_count' => 0, 'total_count' => 0, 'summary' => []]]);
        }

        $teamMembers = $empQuery->orderBy('employee_code')->get()
            ->filter(fn($emp) => $emp->workShifts->count() > 1)
            ->values();
        $empIds = $teamMembers->pluck('id')->toArray();

        // Get assigned shifts
        $assigned = ShiftSchedule::whereIn('emp_id', $empIds)
            ->where('work_date', $cycleStart)
            ->pluck('shift_code', 'emp_id');

        $summary = $teamMembers->map(function ($emp) use ($assigned) {
            return [
                'employee_id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'name' => $emp->name,
                'assigned_shift' => $assigned->get($emp->id, null),
            ];
        });

        $assignedCount = $assigned->count();
        $totalCount = $teamMembers->count();

        return response()->json([
            'success' => true,
            'data' => $summary,
            'stats' => [
                'total' => $totalCount,
                'assigned' => $assignedCount,
                'pending' => $totalCount - $assignedCount,
            ],
            'cycle' => [
                'start' => $cycleStart,
                'end' => $cycleEnd,
            ],
        ]);
    }
}
