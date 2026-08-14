<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoOtRecord extends Model
{
    protected $table = 'auto_ot_records';

    protected $fillable = [
        'emp_id',
        'attendance_log_id',
        'date',
        'ot_type',
        'actual_time',
        'shift_time',
        'ot_minutes',
        'status',
        'approved_by',
        'approved_at',
        'reason',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'ot_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
