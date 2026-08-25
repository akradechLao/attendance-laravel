<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'action' => 'nullable|string|in:create,update,delete,approve,reject',
                'user_type' => 'nullable|string|in:admin,employee',
                'auditable_type' => 'nullable|string',
                'search' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = AuditLog::query();

            if ($request->filled('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }
            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }
            if ($request->filled('user_type')) {
                $query->where('user_type', $request->user_type);
            }
            if ($request->filled('auditable_type')) {
                $query->where('auditable_type', 'like', '%' . $request->auditable_type . '%');
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 20);
            $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $stats = AuditLog::selectRaw('action, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('action')
                ->pluck('count', 'action');

            return response()->json([
                'success' => true,
                'data' => $logs,
                'stats' => $stats,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function forModel(Request $request, string $type, int $id): JsonResponse
    {
        try {
            $logs = AuditLog::forModel($type, $id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function summary(): JsonResponse
    {
        try {
            $today = AuditLog::whereDate('created_at', today())->count();
            $week = AuditLog::where('created_at', '>=', now()->subWeek())->count();
            $month = AuditLog::where('created_at', '>=', now()->subMonth())->count();

            $recentActions = AuditLog::with([])
                ->selectRaw('action, description, user_name, created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $today,
                    'this_week' => $week,
                    'this_month' => $month,
                    'recent_actions' => $recentActions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit summary: ' . $e->getMessage(),
            ], 500);
        }
    }
}
