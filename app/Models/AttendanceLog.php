<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $table = 'attendance_logs';

    protected $fillable = [
        'emp_id',
        'company_id',
        'round_no',
        'check_in',
        'check_in_status',
        'check_in_photo',
        'check_out',
        'check_out_photo',
        'lat_long',
        'date',
        'scan_type',
        'remote_latitude',
        'remote_longitude',
        'remote_accuracy',
        'remote_location_name',
        'remote_custom_name',
        'original_status',
        'final_status',
        'late_minutes',
        'adjusted_by',
        'adjusted_at',
        'adjustment_note',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
        'remote_latitude' => 'decimal:8',
        'remote_longitude' => 'decimal:8',
        'remote_accuracy' => 'integer',
        'adjusted_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'adjusted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'verified_by');
    }

    public function lateForcedLeave()
    {
        return $this->hasOne(LateForcedLeave::class);
    }

    public function isRemoteScan(): bool
    {
        return $this->scan_type === 'remote_scan';
    }

    public function getLocationDisplayName(): string
    {
        if ($this->remote_custom_name) {
            return $this->remote_custom_name;
        }
        if ($this->remote_location_name) {
            return $this->remote_location_name;
        }
        if ($this->remote_latitude && $this->remote_longitude) {
            return number_format($this->remote_latitude, 6) . ', ' . number_format($this->remote_longitude, 6);
        }
        return '-';
    }

    public function getDisplayStatus(): string
    {
        if ($this->final_status) {
            return $this->final_status;
        }
        return $this->original_status ?? $this->check_in_status ?? 'unknown';
    }
}
