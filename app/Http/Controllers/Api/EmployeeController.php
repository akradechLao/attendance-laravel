<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $employees = Employee::with('company')
                ->withCount('faceData')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Employees retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $employee = Employee::with(['company', 'faceData', 'attendance'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employee,
                'message' => 'Employee retrieved successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'code' => 'required|string|max:50|unique:employees,code',
                'name' => 'required|string|max:255',
                'position' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'has_ot' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $validated['has_ot'] = $validated['has_ot'] ?? false;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $employee = Employee::create($validated);

            return response()->json([
                'success' => true,
                'data' => $employee->load('company'),
                'message' => 'Employee created successfully.',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            $validated = $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'code' => 'sometimes|string|max:50|unique:employees,code,' . $id,
                'name' => 'sometimes|string|max:255',
                'position' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'has_ot' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $employee->update($validated);

            return response()->json([
                'success' => true,
                'data' => $employee->load('company'),
                'message' => 'Employee updated successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Employee deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function faceData($id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);
            $faceData = $employee->faceData;

            return response()->json([
                'success' => true,
                'data' => $faceData,
                'message' => 'Face data retrieved successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve face data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function registerFace(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'angle' => 'required|string|in:front,left,right,up,down',
                'face_encoding' => 'required|string',
            ]);

            $faceData = EmployeeFaceData::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'angle' => $validated['angle'],
                ],
                [
                    'face_encoding' => $validated['face_encoding'],
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $faceData,
                'message' => 'Face data registered successfully.',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to register face data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyFace($id): JsonResponse
    {
        try {
            $faceData = EmployeeFaceData::findOrFail($id);
            $faceData->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Face data deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Face data not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete face data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            $request->validate([
                'password' => 'required|string|min:1',
            ]);

            $employee->password = $request->password;
            $employee->save();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'รหัสผ่านถูกตั้งใหม่แล้ว',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'ไม่พบพนักงาน',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'ตั้งรหัสผ่านล้มเหลว: ' . $e->getMessage(),
            ], 500);
        }
    }
}
