<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;

class LeaveService
{
    public function getEntitledDays(Employee $employee, LeaveType $leaveType, int $year): float
    {
        if (!$leaveType->accrual) {
            return (float) $leaveType->max_days_per_year;
        }

        $yearsOfService = $this->getYearsOfService($employee, $year);

        return match($leaveType->code) {
            'annual' => $this->getAnnualLeaveEntitlement($yearsOfService),
            default => (float) $leaveType->max_days_per_year,
        };
    }

    private function getYearsOfService(Employee $employee, int $year): int
    {
        if (!$employee->start_date) {
            return 0;
        }
        $start = Carbon::parse($employee->start_date);
        $end = Carbon::create($year, 12, 31);
        return (int) $start->diffInYears($end);
    }

    private function getAnnualLeaveEntitlement(int $yearsOfService): float
    {
        return match(true) {
            $yearsOfService < 1 => 0,
            $yearsOfService < 5 => 6,
            $yearsOfService < 10 => 8,
            default => 10,
        };
    }

    public function getLeaveBalance(Employee $employee, LeaveType $leaveType, int $year): array
    {
        $balance = LeaveBalance::where('emp_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            $entitled = $this->getEntitledDays($employee, $leaveType, $year);
            $balance = LeaveBalance::create([
                'emp_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
                'entitled_days' => $entitled,
                'used_days' => 0,
                'carried_forward' => 0,
            ]);
        }

        return [
            'entitled' => (float) $balance->entitled_days,
            'used' => (float) $balance->used_days,
            'remaining' => (float) ($balance->entitled_days + $balance->carried_forward - $balance->used_days),
        ];
    }

    public function getAllBalances(Employee $employee, int $year): array
    {
        $leaveTypes = LeaveType::where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->get();

        $balances = [];
        foreach ($leaveTypes as $type) {
            $balance = $this->getLeaveBalance($employee, $type, $year);
            $balances[] = [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'entitled' => $balance['entitled'],
                'used' => $balance['used'],
                'remaining' => $balance['remaining'],
            ];
        }
        return $balances;
    }

    public function deductLeave(Employee $employee, LeaveType $leaveType, float $days, int $year): void
    {
        $balance = LeaveBalance::where('emp_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)->first();
        if ($balance) {
            $balance->increment('used_days', $days);
        }
    }

    public function restoreLeave(Employee $employee, LeaveType $leaveType, float $days, int $year): void
    {
        $balance = LeaveBalance::where('emp_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)->first();
        if ($balance) {
            $balance->decrement('used_days', $days);
        }
    }
}
