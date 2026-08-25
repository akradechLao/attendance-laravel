<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Announcement::where('is_active', true)
            ->where(function ($q) use ($user) {
                if ($user && property_exists($user, 'company_id') && $user->company_id) {
                    $q->where('company_id', $user->company_id);
                }
            })
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('priority')
            ->orderByDesc('created_at');

        $announcements = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'in:normal,important,urgent',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        $employee = $request->user();

        $announcement = Announcement::create([
            'company_id' => $employee->company_id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'priority' => $validated['priority'] ?? 'normal',
            'published_at' => $validated['published_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by' => $employee->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ], 201);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบประกาศเรียบร้อย',
        ]);
    }
}
