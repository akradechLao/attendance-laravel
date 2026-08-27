<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role ?? 'admin',
                'company_id' => $user->company_id,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'sometimes|string|max:50',
            'current_password' => 'required_with:password|string',
            'password' => 'nullable|string|min:4|confirmed',
        ]);

        $user = $request->user();

        if ($request->has('password') && $request->password) {
            if (!$user->password || !str_starts_with((string) $user->password, '$2y$') || !Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านเดิมไม่ถูกต้อง',
                ], 400);
            }
            $user->password = $request->password;
        }

        if ($request->has('username') && $request->username !== $user->username) {
            $exists = \App\Models\AdminUser::where('username', $request->username)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'ชื่อผู้ใช้นี้ถูกใช้แล้ว',
                ], 400);
            }
            $user->username = $request->username;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role ?? 'admin',
                'company_id' => $user->company_id,
            ],
            'message' => 'อัพเดทโปรไฟล์สำเร็จ',
        ]);
    }
}
