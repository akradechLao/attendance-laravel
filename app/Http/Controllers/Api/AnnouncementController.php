<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementDismissal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    private const MAX_ATTACHMENTS = 5;
    private const MAX_ATTACHMENT_KB = 5120; // 5 MB

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Announcement::with('attachments')
                ->where('is_active', true)
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
                    'attachments' => $this->mapAttachments($a),
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
            $query = Announcement::with('attachments');

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

            $announcements = $query->orderByDesc('created_at')->paginate(20)
                ->through(fn($a) => array_merge($a->toArray(), [
                    'attachments' => $this->mapAttachments($a),
                ]));

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
            'attachments' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:' . self::MAX_ATTACHMENT_KB,
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

        $this->storeAttachments($announcement, $request->file('attachments', []));

        return response()->json([
            'success' => true,
            'data' => array_merge($announcement->toArray(), [
                'attachments' => $this->mapAttachments($announcement->fresh()),
            ]),
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
            'attachments' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:' . self::MAX_ATTACHMENT_KB,
        ]);

        $announcement->update(collect($validated)->except('attachments')->all());

        if ($request->hasFile('attachments')) {
            $existingCount = $announcement->attachments()->count();
            $newFiles = $request->file('attachments', []);
            if ($existingCount + count($newFiles) > self::MAX_ATTACHMENTS) {
                return response()->json([
                    'success' => false,
                    'message' => 'แนบไฟล์ได้สูงสุด ' . self::MAX_ATTACHMENTS . ' ไฟล์ต่อประกาศ (ตอนนี้มี ' . $existingCount . ' ไฟล์)',
                ], 422);
            }
            $this->storeAttachments($announcement, $newFiles);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($announcement->fresh()->toArray(), [
                'attachments' => $this->mapAttachments($announcement->fresh()),
            ]),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $announcement->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบประกาศนี้'], 403);
        }

        foreach ($announcement->attachments as $attachment) {
            \Storage::disk('public')->delete($attachment->file_path);
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบประกาศเรียบร้อย',
        ]);
    }

    public function deleteAttachment(Request $request, Announcement $announcement, AnnouncementAttachment $attachment): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $announcement->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขประกาศนี้'], 403);
        }

        if ($attachment->announcement_id !== $announcement->id) {
            return response()->json(['success' => false, 'message' => 'ไม่พบไฟล์แนบนี้ในประกาศดังกล่าว'], 404);
        }

        \Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบไฟล์แนบเรียบร้อย',
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

    /** @param UploadedFile[] $files */
    private function storeAttachments(Announcement $announcement, array $files): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('announcements', $filename, 'public');

            AnnouncementAttachment::create([
                'announcement_id' => $announcement->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'file_size' => $file->getSize() ?: 0,
            ]);
        }
    }

    private function mapAttachments(?Announcement $announcement): array
    {
        if (!$announcement) {
            return [];
        }

        return $announcement->attachments->map(fn($att) => [
            'id' => $att->id,
            'file_name' => $att->file_name,
            'kind' => $att->kind,
            'url' => $att->url,
            'file_size' => $att->file_size,
        ])->values()->all();
    }
}
