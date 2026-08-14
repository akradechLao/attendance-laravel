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
        'reason',
        'supervisor_id',
        'supervisor_note',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }
}
