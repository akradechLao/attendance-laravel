<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeApprover extends Model
{
    protected $table = 'employee_approvers';

    protected $fillable = [
        'employee_id',
        'approver_name',
        'can_approve',
    ];

    protected $casts = [
        'can_approve' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
