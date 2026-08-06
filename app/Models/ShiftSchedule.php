<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSchedule extends Model
{
    protected $table = 'shift_schedules';

    protected $fillable = [
        'company_id',
        'emp_id',
        'work_date',
        'shift_code',
        'day_type',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }
}
