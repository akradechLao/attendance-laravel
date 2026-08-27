<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmployeeProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $employee->load(['company', 'workShifts', 'assignedOfficeLocations', 'faceData']);

        $scheduleType = $employee->workShifts->count() > 0 ? 'shift' : 'monthly';

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
                'company' => $employee->company?->full_name ?? $employee->company?->name,
                'company_name' => $employee->company?->name,
                'company_full_name_en' => $employee->company?->full_name_en,
                'schedule_type' => $scheduleType,
                'work_shifts' => $employee->workShifts->map(function ($s) use ($scheduleType) {
                    $today = now()->format('Y-m-d');
                    $start = $s->pivot->start_date ? Carbon::parse($s->pivot->start_date)->format('Y-m-d') : null;
                    $end = $s->pivot->end_date ? Carbon::parse($s->pivot->end_date)->format('Y-m-d') : null;
                    $isActive = (!$start || $today >= $start) && (!$end || $today <= $end);
                    return [
                        'group_number' => $s->group_number,
                        'shift_code' => \App\Helpers\ShiftCodeHelper::codeFromGroup($s->group_number) ?? 'WC' . str_pad($s->group_number + 1, 4, '0', STR_PAD_LEFT),
                        'start_time' => \App\Helpers\ShiftCodeHelper::getStartTime(\App\Helpers\ShiftCodeHelper::codeFromGroup($s->group_number) ?? 'WC' . str_pad($s->group_number + 1, 4, '0', STR_PAD_LEFT)),
                        'end_time' => \App\Helpers\ShiftCodeHelper::getEndTime(\App\Helpers\ShiftCodeHelper::codeFromGroup($s->group_number) ?? 'WC' . str_pad($s->group_number + 1, 4, '0', STR_PAD_LEFT)),
                        'work_hours' => $s->work_hours,
                        'pivot_start' => $start,
                        'pivot_end' => $end,
                        'is_active' => $isActive,
                    ];
                }),
                'office_location' => $employee->assignedOfficeLocations->first()?->name,
                'has_ot' => $employee->has_ot,
                'status' => $employee->status ?? 'active',
                'start_date' => $employee->start_date ? Carbon::parse($employee->start_date)->format('Y-m-d') : null,
                'face_data_count' => $employee->faceData->count(),
                'photo' => $employee->photo,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $request->user();

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

    public function uploadPhoto(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $file = $request->file('photo');
        $filename = 'profile_' . $employee->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/profile-photos', $filename);

        // Delete old photo if exists
        if ($employee->photo) {
            $oldPath = str_replace('storage/', 'storage/app/public/', $employee->photo);
            if (\Storage::disk('local')->exists($oldPath)) {
                \Storage::disk('local')->delete($oldPath);
            }
        }

        $employee->update(['photo' => '/storage/profile-photos/' . $filename]);

        return response()->json([
            'success' => true,
            'photo' => $employee->photo,
            'message' => 'อัปโหลดรูปโปรไฟล์สำเร็จ',
        ]);
    }
}
