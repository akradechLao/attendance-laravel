<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    protected $table = 'work_shifts';

    protected $fillable = [
        'group_number',
        'start_time',
        'end_time',
        'work_hours',
        'is_overnight',
    ];

    protected $casts = [
        'group_number' => 'integer',
        'work_hours' => 'integer',
        'is_overnight' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot('start_date', 'end_date');
    }

    public function getStartTimeFormattedAttribute(): string
    {
        return $this->start_time ? $this->start_time->format('H:i') : '';
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return $this->end_time ? $this->end_time->format('H:i') : '';
    }
}
