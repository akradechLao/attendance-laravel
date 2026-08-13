<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = [
        'emp_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'used_days',
        'carried_forward',
    ];

    protected $casts = [
        'entitled_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'carried_forward' => 'decimal:1',
    ];

    public function employee() { return $this->belongsTo(Employee::class, 'emp_id'); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
}
