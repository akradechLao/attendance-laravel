<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementAttachment extends Model
{
    protected $table = 'announcement_attachments';

    protected $fillable = [
        'announcement_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** e.g. 'pdf' or 'image' - drives how the frontend renders it */
    public function getKindAttribute(): string
    {
        return $this->mime_type === 'application/pdf' ? 'pdf' : 'image';
    }

    public function getUrlAttribute(): string
    {
        return \Storage::disk('public')->url($this->file_path);
    }
}
