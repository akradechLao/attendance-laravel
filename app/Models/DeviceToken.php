<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'employee_id',
        'token',
        'device_name',
        'device_fingerprint',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function generateToken(?string $deviceName = null, ?string $fingerprint = null): self
    {
        return static::create([
            'token' => hash('sha256', Str::random(64) . microtime(true)),
            'device_name' => $deviceName,
            'device_fingerprint' => $fingerprint,
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
