<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhRecord extends Model
{
    protected $fillable = [
        'emp_id',
        'date',
        'approved_date',
        'requested_date',
        'requested_reason',
        'cancel_reason',
        'requested_at',
        'reason',
        'supervisor_id',
        'supervisor_note',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_date' => 'date',
        'requested_date' => 'date',
        'requested_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'supervisor_id');
    }
}
