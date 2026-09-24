<?php
namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasCompanyScope;

    protected $table = 'leave_types';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'advance_days',
        'quota_monthly',
        'max_days_per_year',
        'accrual',
        'carry_forward',
        'quota_daily',
        'quota_contract',
        'max_days',
        'is_active',
    ];

    protected $casts = [
        'advance_days' => 'integer',
        'quota_monthly' => 'integer',
        'max_days_per_year' => 'integer',
        'accrual' => 'boolean',
        'carry_forward' => 'boolean',
        'quota_daily' => 'integer',
        'quota_contract' => 'integer',
        'max_days' => 'integer',
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
