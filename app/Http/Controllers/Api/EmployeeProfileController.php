<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $employee->load(['company', 'workShifts', 'assignedOfficeLocations']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'nickname' => $employee->nickname,
                'department' => $employee->department,
                'position' => $employee->position,
                'phone' => $employee->phone,
                'email' => $employee->email,
                'company' => $employee->company?->name,
                'work_shifts' => $employee->workShifts->map(fn($s) => [
                    'group_number' => $s->group_number,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'work_hours' => $s->work_hours,
                ]),
                'office_location' => $employee->assignedOfficeLocations->first()?->name,
                'has_ot' => $employee->has_ot,
                'status' => $employee->status,
                'start_date' => $employee->start_date,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'data' => $employee->fresh(),
        ]);
    }
}
