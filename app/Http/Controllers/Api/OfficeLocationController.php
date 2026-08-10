<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OfficeLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $locations = OfficeLocation::withCount('assignedEmployees')->get();

            return response()->json([
                'success' => true,
                'data' => $locations,
                'message' => 'Office locations retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve office locations: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_meters' => 'required|numeric|min:10',
                'work_start_time' => 'nullable|string',
                'work_end_time' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if (empty($validated['work_start_time'])) unset($validated['work_start_time']);
            if (empty($validated['work_end_time'])) unset($validated['work_end_time']);

            $location = OfficeLocation::create($validated);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Office location created successfully.',
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
                'message' => 'Failed to create office location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);

            $validated = $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'name' => 'sometimes|string|max:255',
                'address' => 'nullable|string|max:500',
                'latitude' => 'sometimes|numeric|between:-90,90',
                'longitude' => 'sometimes|numeric|between:-180,180',
                'radius_meters' => 'sometimes|numeric|min:10',
                'work_start_time' => 'nullable|string',
                'work_end_time' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if (isset($validated['work_start_time']) && empty($validated['work_start_time'])) unset($validated['work_start_time']);
            if (isset($validated['work_end_time']) && empty($validated['work_end_time'])) unset($validated['work_end_time']);

            $location->update($validated);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Office location updated successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Office location not found.',
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
                'message' => 'Failed to update office location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);
            $location->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Office location deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Office location not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete office location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getEmployees($id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);
            $employees = $location->assignedEmployees()
                ->with('company:id,name,code_prefix')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to get employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignEmployees(Request $request, $id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);

            $validated = $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'exists:employees,id',
            ]);

            $location->assignedEmployees()->syncWithoutDetaching($validated['employee_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Employees assigned successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function removeEmployees(Request $request, $id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);

            $validated = $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'exists:employees,id',
            ]);

            $location->assignedEmployees()->detach($validated['employee_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Employees removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUnassignedEmployees(Request $request, $id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);
            $assignedIds = $location->assignedEmployees()->pluck('employees.id')->toArray();

            $query = Employee::where('company_id', $location->company_id)
                ->where('is_active', true)
                ->whereNotIn('employees.id', $assignedIds);

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('employee_code', 'like', "%{$search}%")
                      ->orWhere('division', 'like', "%{$search}%")
                      ->orWhere('department', 'like', "%{$search}%");
                });
            }

            $employees = $query->get(['id', 'name', 'employee_code', 'division', 'department']);

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to get unassigned employees: ' . $e->getMessage(),
            ], 500);
        }
    }
}
