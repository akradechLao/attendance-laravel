<?php
namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\CompanySetting;

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
        $startDate = Carbon::parse($this->date)->setTimezone('Asia/Bangkok');
        $startTime = Carbon::parse($this->start_time);
        $start = $startDate->copy()->setTime($startTime->hour, $startTime->minute);

        if ($this->end_date) {
            $endDate = Carbon::parse($this->end_date)->setTimezone('Asia/Bangkok');
        } else {
            $endDate = Carbon::parse($this->date)->setTimezone('Asia/Bangkok');
        }
        $endTime = Carbon::parse($this->end_time);
        $end = $endDate->copy()->setTime($endTime->hour, $endTime->minute);

        if ($start >= $end) {
            return 0;
        }

        return $this->calculateHoursExcludingWorkWindows($start, $end, $this->company_id);
    }

    private function calculateHoursExcludingWorkWindows(Carbon $start, Carbon $end, int $companyId): float
    {
        $workStart = CompanySetting::getValue($companyId, 'work_start_time', '08:00');
        $workEnd = CompanySetting::getValue($companyId, 'work_end_time', '17:00');
        $lunchStart = CompanySetting::getValue($companyId, 'lunch_start_time', '11:45');
        $lunchEnd = CompanySetting::getValue($companyId, 'lunch_end_time', '12:45');

        $workWindows = [
            [$workStart, $lunchStart],
            [$lunchEnd, $workEnd],
        ];

        $totalMinutes = 0;
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dayStartOt = max($currentDate->copy(), $start);
            $dayEndOt = min($end, $currentDate->copy()->endOfDay());

            if ($dayStartOt < $dayEndOt) {
                $dayMinutes = $dayStartOt->diffInMinutes($dayEndOt);
                foreach ($workWindows as [$wStart, $wEnd]) {
                    $wStartDt = $currentDate->copy()->setTimeFromTimeString($wStart);
                    $wEndDt = $currentDate->copy()->setTimeFromTimeString($wEnd);
                    $overlapStart = max($dayStartOt, $wStartDt);
                    $overlapEnd = min($dayEndOt, $wEndDt);
                    if ($overlapStart < $overlapEnd) {
                        $dayMinutes -= $overlapStart->diffInMinutes($overlapEnd);
                    }
                }
                $totalMinutes += max(0, $dayMinutes);
            }
            $currentDate->addDay();
        }

        return round($totalMinutes / 60, 2);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }
}
