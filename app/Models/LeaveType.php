<?php
namespace App\Models;

use App\Constants\RoleConstants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
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

    protected static function boot(): void
    {
        parent::boot();

        // Global master (company_id = null) + company-specific overrides (future tailor-made)
        static::addGlobalScope('company', function ($builder) {
            $user = auth()->user();
            if (!$user || ($user->role ?? '') === RoleConstants::SUPER_ADMIN) {
                return;
            }
            $companyId = $user->company_id ?? null;
            if ($companyId === null || $companyId === '') {
                return;
            }
            $builder->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        });
    }

    /**
     * Resolve effective types for a company: company override wins over global master.
     */
    public static function forCompany(int $companyId)
    {
        return static::where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            // Company-specific rows first so they win over global master on same code
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->get()
            ->unique(fn ($t) => $t->code ?: ('id_' . $t->id))
            ->values();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
