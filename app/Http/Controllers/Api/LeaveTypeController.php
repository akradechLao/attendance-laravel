<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = LeaveType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('leave_types', 'code')->where(fn ($q) => $q->whereNull('company_id')),
            ],
            'max_days' => 'nullable|integer|min:0|max:365',
            'max_days_per_year' => 'nullable|integer|min:0|max:365',
            'accrual' => 'boolean',
            'carry_forward' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $type = LeaveType::create([
            'company_id' => null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'max_days' => $validated['max_days'] ?? 0,
            'max_days_per_year' => $validated['max_days_per_year'] ?? ($validated['max_days'] ?? 0),
            'accrual' => $validated['accrual'] ?? false,
            'carry_forward' => $validated['carry_forward'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'quota_monthly' => 0,
            'advance_days' => 0,
            'quota_daily' => 0,
            'quota_contract' => 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $type,
            'message' => 'เพิ่มชนิดลาสำเร็จ',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $type = LeaveType::whereNull('company_id')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('leave_types', 'code')
                    ->where(fn ($q) => $q->whereNull('company_id'))
                    ->ignore($type->id),
            ],
            'max_days' => 'nullable|integer|min:0|max:365',
            'max_days_per_year' => 'nullable|integer|min:0|max:365',
            'accrual' => 'boolean',
            'carry_forward' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'data' => $type,
            'message' => 'แก้ไขชนิดลาสำเร็จ',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $type = LeaveType::whereNull('company_id')->findOrFail($id);

        if ($type->leaveRequests()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถลบได้: มีคำขอลาใช้ชนิดนี้อยู่ (ปิดใช้งานแทนได้)',
            ], 400);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบชนิดลาสำเร็จ',
        ]);
    }
}
