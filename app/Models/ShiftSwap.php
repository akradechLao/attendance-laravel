<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwap extends Model
{
    protected $fillable = [
        'requester_id',
        'target_id',
        'swap_date',
        'requester_shift',
        'target_shift',
        'reason',
        'status',
        'supervisor_id',
        'supervisor_note',
    ];

    protected $casts = [
        'swap_date' => 'date',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }
}
