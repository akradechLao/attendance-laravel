<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MandatoryOtAssignment extends Model
{
    protected $table = 'mandatory_ot_assignments';

    protected $fillable = [
        'company_id',
        'emp_id',
        'ot_date',
        'start_time',
        'end_time',
        'reason',
        'assigned_by',
        'status',
    ];

    protected $casts = [
        'ot_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
