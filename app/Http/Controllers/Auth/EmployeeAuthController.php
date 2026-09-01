<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
                ->select('id', 'name', 'nickname', 'employee_code', 'photo', 'company_id', 'has_ot', 'position', 'department', 'division', 'is_active')
                ->with('company:id,name,code_prefix')
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

    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_code' => 'required|string',
                'password' => 'required|string',
                'company_id' => 'required|exists:companies,id',
            ]);

            $employee = Employee::where('employee_code', $request->employee_code)
                ->where('company_id', $request->company_id)
                ->first();

            if (!$employee || !$employee->password || !Hash::check($request->password, $employee->password)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง',
                ], 401);
            }

            $token = $employee->createToken('employee-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $employee->select('id', 'name', 'nickname', 'employee_code', 'photo', 'company_id', 'has_ot', 'position', 'level', 'department', 'division', 'reports_to', 'role', 'is_active')
                        ->load('company:id,name,code_prefix', 'workShifts'),
                    'token' => $token,
                ],
                'message' => 'เข้าสู่ระบบสำเร็จ',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เข้าสู่ระบบล้มเหลว: ' . $e->getMessage(),
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
                'data' => $employee->select('id', 'name', 'nickname', 'employee_code', 'photo', 'company_id', 'has_ot', 'position', 'level', 'department', 'division', 'reports_to', 'role', 'is_active')
                    ->load(['company:id,name,code_prefix', 'workShifts']),
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
