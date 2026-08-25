<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateForcedLeave extends Model
{
    protected $table = 'late_forced_leaves';

    protected $fillable = [
        'emp_id',
        'attendance_log_id',
        'date',
        'late_minutes',
        'leave_minutes',
        'leave_type',
        'leave_request_id',
        'status',
        'approved_by',
        'approved_at',
        'reason',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'late_minutes' => 'integer',
        'leave_minutes' => 'integer',
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
        return $this->belongsTo(AdminUser::class, 'approved_by');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
