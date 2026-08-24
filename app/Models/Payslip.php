<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'company_id',
        'emp_id',
        'month',
        'year',
        'base_salary',
        'ot_pay',
        'bonus',
        'transport_allowance',
        'meal_allowance',
        'other_allowance',
        'deduction_late',
        'deduction_absent',
        'deduction_social_security',
        'deduction_tax',
        'deduction_other',
        'note',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'ot_pay' => 'decimal:2',
        'bonus' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'deduction_late' => 'decimal:2',
        'deduction_absent' => 'decimal:2',
        'deduction_social_security' => 'decimal:2',
        'deduction_tax' => 'decimal:2',
        'deduction_other' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public function getTotalAllowancesAttribute(): float
    {
        return $this->ot_pay + $this->bonus + $this->transport_allowance
            + $this->meal_allowance + $this->other_allowance;
    }

    public function getTotalDeductionsAttribute(): float
    {
        return $this->deduction_late + $this->deduction_absent
            + $this->deduction_social_security + $this->deduction_tax
            + $this->deduction_other;
    }

    public function getNetSalaryAttribute(): float
    {
        return $this->base_salary + $this->total_allowances - $this->total_deductions;
    }
}
