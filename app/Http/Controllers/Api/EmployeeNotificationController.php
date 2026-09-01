<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();
        $query = EmployeeNotification::where('emp_id', $employee->id);

        if ($request->has('unreadOnly') && $request->unreadOnly) {
            $query->where('is_read', false);
        }

        $notifications = $query->orderBy('created_at', 'desc')->take(50)->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'related_id' => $n->related_id,
                'related_type' => $n->related_type,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at ? Carbon::parse($n->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            ]);

        $unreadCount = EmployeeNotification::where('emp_id', $employee->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $employee = $request->user();
        $count = EmployeeNotification::where('emp_id', $employee->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $employee = $request->user();
        $notification = EmployeeNotification::where('emp_id', $employee->id)->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Marked as read',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $employee = $request->user();
        EmployeeNotification::where('emp_id', $employee->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All marked as read',
        ]);
    }
}
