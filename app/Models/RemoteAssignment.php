<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteAssignment extends Model
{
    protected $fillable = [
        'emp_id',
        'company_id',
        'start_date',
        'end_date',
        'destination',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'approved_by');
    }

    public function isActive(): bool
    {
        $today = now()->toDateString();
        return $this->status === 'approved' 
            && $this->start_date <= $today 
            && $this->end_date >= $today;
    }
}
