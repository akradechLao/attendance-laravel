<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $table = 'leave_types';

    protected $fillable = [
        'company_id',
        'name',
        'advance_days',
        'quota_monthly',
        'quota_daily',
        'quota_contract',
        'is_active',
    ];

    protected $casts = [
        'advance_days' => 'integer',
        'quota_monthly' => 'integer',
        'quota_daily' => 'integer',
        'quota_contract' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
