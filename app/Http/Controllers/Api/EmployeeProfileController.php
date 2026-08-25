<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $employee->load(['company', 'workShifts', 'assignedOfficeLocations', 'faceData']);

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
