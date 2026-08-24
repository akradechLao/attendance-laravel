<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::where('company_id', $this->resolveCompanyId($request))
            ->select('id', 'employee_code', 'name', 'nickname', 'position', 'role', 'is_active', 'department', 'division')
            ->orderBy('employee_code')
            ->get();

        return response()->json($employees);
    }

    public function updateRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|in:employee,admin,super_admin']);

        $employee = Employee::findOrFail($id);

        if ($employee->id === $request->user()->id) {
            return response()->json(['message' => 'ไม่สามารถเปลี่ยนสิทธิ์ตัวเองได้'], 400);
        }

        $employee->update(['role' => $request->role]);

        return response()->json(['message' => 'อัพเดทสิทธิ์สำเร็จ', 'employee' => $employee]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => $request->is_active]);

        return response()->json(['message' => 'อัพเดทสถานะสำเร็จ', 'employee' => $employee]);
    }

    public function resetPassword(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['password' => Hash::make('password')]);

        return response()->json(['message' => 'รีเซ็ตรหัสผ่านสำเร็จ (รหัสผ่านใหม่: password)']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'รหัสผ่านเดิมไม่ถูกต้อง'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
    }
}
