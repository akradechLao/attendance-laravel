<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceAuthController extends Controller
{
    /**
     * Register device after successful login.
     * Returns a token that can be used for device-based login.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'device_name' => 'nullable|string|max:255',
            'device_fingerprint' => 'nullable|string|max:255',
        ]);

        $employee = Employee::find($request->employee_id);

        // Create device token
        $deviceToken = DeviceToken::generateToken(
            $request->device_name,
            $request->device_fingerprint
        );
        $deviceToken->employee()->associate($employee);
        $deviceToken->save();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $deviceToken->token,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'company_id' => $employee->company_id,
                'expires_at' => $deviceToken->expires_at->toISOString(),
            ],
            'message' => 'Device registered successfully',
        ]);
    }

    /**
     * Login using device token (no password needed).
     */
    public function deviceLogin(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'device_fingerprint' => 'nullable|string|max:255',
        ]);

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device token',
            ], 401);
        }

        if ($deviceToken->isExpired()) {
            $deviceToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Device token expired. Please login again.',
            ], 401);
        }

        $employee = Employee::find($deviceToken->employee_id);
        if (!$employee || !$employee->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or inactive',
            ], 401);
        }

        // Verify fingerprint if provided
        if ($request->device_fingerprint && $deviceToken->device_fingerprint) {
            if ($request->device_fingerprint !== $deviceToken->device_fingerprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device mismatch. Please login with password.',
                ], 401);
            }
        }

        // Update last used
        $deviceToken->touchLastUsed();

        // Generate employee auth token (same as normal login)
        $authToken = $employee->createToken('employee-auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $authToken,
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->name,
                    'company_id' => $employee->company_id,
                    'has_ot' => $employee->has_ot,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'division' => $employee->division,
                ],
                'device_token' => $deviceToken->token,
            ],
            'message' => 'Device login successful',
        ]);
    }

    /**
     * Remove a device token (logout device).
     */
    public function removeDevice(Request $request, int $tokenId): JsonResponse
    {
        $employee = $request->user();
        $deviceToken = DeviceToken::where('id', $tokenId)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        $deviceToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device removed',
        ]);
    }

    /**
     * List all registered devices for an employee.
     */
    public function listDevices(Request $request): JsonResponse
    {
        $employee = $request->user();
        $devices = DeviceToken::where('employee_id', $employee->id)
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'last_used_at' => $d->last_used_at?->toISOString(),
                'expires_at' => $d->expires_at?->toISOString(),
                'is_expired' => $d->isExpired(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }
}
