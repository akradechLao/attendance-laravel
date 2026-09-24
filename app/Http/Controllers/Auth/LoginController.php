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
                'message' => 'เข้าสู่ระบบล้มเหลว กรุณาลองใหม่อีกครั้ง',
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

    /**
     * Which approval types this user may act on (for menu / button visibility).
     * Must stay in sync with approve/reject permission checks:
     * - shift_swap/shift_request: super_admin or Employee chain/delegated rights only
     * - other types: admin/super_admin role, or Employee with chain/delegated rights
     */
    public function approvalCapabilities(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $types = ['leave', 'ot', 'wfh', 'shift_swap', 'shift_request'];
            $caps = [];

            $isAdminUser = $user instanceof AdminUser;
            $userRole = $user->role ?? 'employee';

            foreach ($types as $type) {
                if ($isAdminUser) {
                    if ($userRole === 'super_admin') {
                        $caps[$type] = true;
                    } elseif ($userRole === 'admin') {
                        $isShiftType = in_array($type, ['shift_swap', 'shift_request'], true);
                        $caps[$type] = !$isShiftType;
                    } else {
                        $caps[$type] = false;
                    }
                } elseif (method_exists($user, 'canApproveTypeGlobally')) {
                    $caps[$type] = $user->canApproveTypeGlobally($type);
                } else {
                    $caps[$type] = false;
                }
            }

            // estimated_checkout is admin-role only today (PendingApprovals scope)
            $caps['estimated_checkout'] = $isAdminUser
                ? in_array($userRole, ['admin', 'super_admin'], true)
                : false;

            return response()->json([
                'success' => true,
                'data' => $caps,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to load approval capabilities: ' . $e->getMessage(),
            ], 500);
        }
    }
}
