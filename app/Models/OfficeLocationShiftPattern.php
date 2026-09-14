<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeLocationShiftPattern extends Model
{
    protected $table = 'office_location_shift_patterns';

    protected $fillable = [
        'office_location_id',
        'work_shift_id',
        'days_of_week',
        'effective_start_date',
        'effective_end_date',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * เช็คว่าวันที่ที่ระบุ ตรงกับรูปแบบนี้หรือไม่ (วันในสัปดาห์ + ช่วงวันที่มีผล)
     */
    public function matchesDate(string $date): bool
    {
        if (!$this->is_active) return false;

        $dayOfWeek = (int) date('w', strtotime($date));
        if (!in_array($dayOfWeek, $this->days_of_week ?? [], true)) return false;

        if ($this->effective_start_date && $date < $this->effective_start_date->format('Y-m-d')) return false;
        if ($this->effective_end_date && $date > $this->effective_end_date->format('Y-m-d')) return false;

        return true;
    }
}
