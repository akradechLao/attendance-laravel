<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = AdminUser::where('username', $request->username)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->username,
                        'username' => $user->username,
                        'role' => $user->role ?? 'admin',
                        'company_id' => $user->company_id,
                    ],
                    'token' => $token,
                ],
                'message' => 'Login successful.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Logged out successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Logout failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = [
                'id' => $user->id,
                'name' => $user->username ?? $user->name,
                'username' => $user->username ?? null,
                'role' => $user->role ?? 'admin',
                'company_id' => $user->company_id ?? null,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'User retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve user: ' . $e->getMessage(),
            ], 500);
        }
    }
}
