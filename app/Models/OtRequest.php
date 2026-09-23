<?php
namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OtRequest extends Model
{
    use HasCompanyScope;

    protected $table = 'ot_requests';

    protected $fillable = [
        'company_id',
        'emp_id',
        'date',
        'end_date',
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
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OtRequest $ot) {
            if (!$ot->total_hours && $ot->start_time && $ot->end_time && $ot->date) {
                $ot->total_hours = $ot->calculateOtHours();
            }
        });

        static::updating(function (OtRequest $ot) {
            if ($ot->isDirty(['start_time', 'end_time', 'date', 'end_date']) && $ot->start_time && $ot->end_time && $ot->date) {
                $ot->total_hours = $ot->calculateOtHours();
            }
        });
    }

    public function calculateOtHours(): float
    {
        $start = Carbon::parse($this->date)->setTimezone('Asia/Bangkok')
            ->setTime(Carbon::parse($this->start_time)->hour, Carbon::parse($this->start_time)->minute);

        $endDate = $this->end_date
            ? Carbon::parse($this->end_date)->setTimezone('Asia/Bangkok')
            : Carbon::parse($this->date)->setTimezone('Asia/Bangkok');
        $end = $endDate->copy()->setTime(Carbon::parse($this->end_time)->hour, Carbon::parse($this->end_time)->minute);

        if ($start >= $end) {
            return 0;
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }
}
