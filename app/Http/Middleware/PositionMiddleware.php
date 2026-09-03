<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Constants\PositionConstants;

class PositionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$positions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // HR admin / super_admin bypass position check
        $userRole = $user->role ?? 'employee';
        if (in_array($userRole, ['admin', 'super_admin'])) {
            return $next($request);
        }

        $employee = $user instanceof \App\Models\Employee ? $user : null;

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $employeePosition = $employee->position ?? 'employee';

        if (!empty($positions) && !in_array($employeePosition, $positions)) {
            return response()->json(['message' => 'Forbidden: insufficient position level'], 403);
        }

        return $next($request);
    }
}
