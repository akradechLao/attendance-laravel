<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAuthController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'query' => 'nullable|string',
            ]);

            $query = $request->get('query', '');

            $employees = Employee::where('company_id', $request->company_id)
                ->where(function ($q) use ($query) {
                    if ($query) {
                        $q->where('name', 'like', "%{$query}%")
                          ->orWhere('employee_code', 'like', "%{$query}%")
                          ->orWhere('nickname', 'like', "%{$query}%");
                    }
                })
                ->with('company')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Employees retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
            ]);

            $employee = Employee::findOrFail($request->employee_id);

            if ($request->has_ot && !$employee->has_ot) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Employee does not have OT access.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $employee->load('company'),
                'message' => 'Employee verified successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
