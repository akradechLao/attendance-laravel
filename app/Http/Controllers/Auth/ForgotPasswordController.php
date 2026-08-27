<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Employee;
use App\Models\PasswordResetToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'user_type' => 'required|in:admin,employee',
        ]);

        if ($request->user_type === 'admin') {
            $user = AdminUser::where('username', $request->username)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้งานนี้ในระบบ',
                ], 404);
            }
            $email = $user->username;
        } else {
            $user = Employee::where('employee_code', $request->username)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบพนักงานนี้ในระบบ',
                ], 404);
            }
            $email = $user->employee_code;
        }

        $token = Str::random(6);

        PasswordResetToken::where('email', $email)->delete();

        PasswordResetToken::create([
            'email' => $email,
            'token' => $token,
            'user_type' => $request->user_type,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'รหัสรีเซ็ต: ' . $token,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'token' => 'required|string',
            'new_password' => 'required|string|min:4|confirmed',
            'user_type' => 'required|in:admin,employee',
        ]);

        $resetToken = PasswordResetToken::where('email', $request->username)
            ->where('token', $request->token)
            ->where('user_type', $request->user_type)
            ->first();

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'message' => 'รหัสรีเซ็ตไม่ถูกต้องหรือหมดอายุ',
            ], 400);
        }

        if ($request->user_type === 'admin') {
            $user = AdminUser::where('username', $request->username)->first();
        } else {
            $user = Employee::where('employee_code', $request->username)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบผู้ใช้งาน',
            ], 404);
        }

        $user->password = $request->new_password;
        $user->save();

        $resetToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่',
        ]);
    }
}
