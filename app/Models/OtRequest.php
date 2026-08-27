<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OtRequest extends Model
{
    protected $table = 'ot_requests';

    protected $fillable = [
        'company_id',
        'emp_id',
        'date',
        'start_time',
        'end_time',
        'total_hours',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OtRequest $ot) {
            if (!$ot->total_hours && $ot->start_time && $ot->end_time) {
                $start = Carbon::parse($ot->start_time);
                $end = Carbon::parse($ot->end_time);
                $ot->total_hours = round($start->diffInMinutes($end) / 60, 2);
            }
        });

        static::updating(function (OtRequest $ot) {
            if ($ot->isDirty(['start_time', 'end_time']) && $ot->start_time && $ot->end_time) {
                $start = Carbon::parse($ot->start_time);
                $end = Carbon::parse($ot->end_time);
                $ot->total_hours = round($start->diffInMinutes($end) / 60, 2);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }
}
