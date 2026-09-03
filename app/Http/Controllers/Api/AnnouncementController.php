<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementDismissal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
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
                ->where(function ($q) use ($user) {
                    if ($user) {
                        $q->whereDoesntHave('dismissals', function ($dq) use ($user) {
                            $dq->where('employee_id', $user->id);
                        });
                    }
                })
                ->orderByDesc('priority')
                ->orderByDesc('created_at');

            $announcements = $query->limit(20)->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'priority' => $a->priority,
                    'is_active' => $a->is_active,
                    'company_id' => $a->company_id,
                    'published_at' => $a->published_at ? Carbon::parse($a->published_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                    'expires_at' => $a->expires_at ? Carbon::parse($a->expires_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                    'created_at' => $a->created_at ? Carbon::parse($a->created_at)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
                ]);

            return response()->json([
                'success' => true,
                'data' => $announcements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Announcement::query();

            // Try loading creator relation, skip if table issue
            try {
                $query->with('creator:id,username');
            } catch (\Exception $e) {
                // creator relation may fail if admin_users table has issues
            }

            if ($user->role !== 'super_admin' && $user->company_id) {
                $query->where('company_id', $user->company_id);
            }

            if ($request->company_id) {
                $query->where('company_id', $request->company_id);
            }

            $announcements = $query->orderByDesc('created_at')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $announcements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'in:normal,important,urgent',
            'company_id' => 'nullable|exists:companies,id',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:published_at',
        ]);

        $user = $request->user();

        if (empty($validated['company_id']) && $user->company_id) {
            $validated['company_id'] = $user->company_id;
        }

        $announcement = Announcement::create([
            'company_id' => $validated['company_id'] ?? null,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'priority' => $validated['priority'] ?? 'normal',
            'published_at' => $validated['published_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $announcement->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขประกาศนี้'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'in:normal,important,urgent',
            'company_id' => 'nullable|exists:companies,id',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:published_at',
            'is_active' => 'boolean',
        ]);

        $announcement->update($validated);

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $announcement->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบประกาศนี้'], 403);
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบประกาศเรียบร้อย',
        ]);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $announcement = Announcement::findOrFail($id);

            AnnouncementDismissal::updateOrCreate([
                'employee_id' => $user->id,
                'announcement_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ปิดประกาศแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }
}
