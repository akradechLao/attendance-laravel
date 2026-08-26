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
        $balance = LeaveBalance::where("emp_id", $employee->id)
            ->where("leave_type_id", $leaveType->id)
            ->where("year", $year)
            ->first();

        if (!$balance) {
            $entitled = $this->getEntitledDays($employee, $leaveType, $year);
            $balance = LeaveBalance::create([
                "emp_id" => $employee->id,
                "leave_type_id" => $leaveType->id,
                "year" => $year,
                "entitled_days" => $entitled,
                "used_days" => 0,
                "carried_forward" => 0,
                "vacation_accumulated" => 0,
                "vacation_expiry_date" => null,
            ]);
        }

        $vacationRemaining = 0;
        if ($leaveType->code === "annual" && isset($balance->vacation_accumulated) && $balance->vacation_accumulated > 0) {
            $now = Carbon::now();
            if (!empty($balance->vacation_expiry_date) && $now->lte($balance->vacation_expiry_date)) {
                $vacationRemaining = (float) $balance->vacation_accumulated;
            }
        }

        return [
            "entitled" => (float) $balance->entitled_days,
            "used" => (float) $balance->used_days,
            "remaining" => (float) ($balance->entitled_days + $balance->carried_forward - $balance->used_days),
            "vacation_accumulated" => $vacationRemaining,
            "vacation_expiry_date" => isset($balance->vacation_expiry_date) ? ($balance->vacation_expiry_date?->format("Y-m-d")) : null,
        ];
    }

    public function getAllBalances(Employee $employee, int $year): array
    {
        $leaveTypes = LeaveType::where("company_id", $employee->company_id)->get();

        $balances = [];
        foreach ($leaveTypes as $type) {
            $balance = $this->getLeaveBalance($employee, $type, $year);
            $balances[] = [
                "leave_type_id" => $type->id,
                "name" => $type->name,
                "code" => $type->code,
                "entitled" => $balance["entitled"],
                "used" => $balance["used"],
                "remaining" => $balance["remaining"],
                "vacation_accumulated" => $balance["vacation_accumulated"],
                "vacation_expiry_date" => $balance["vacation_expiry_date"],
            ];
        }
        return $balances;
    }

    public function deductLeave(Employee $employee, LeaveType $leaveType, float $days, int $year): void
    {
        $balance = LeaveBalance::where("emp_id", $employee->id)
            ->where("leave_type_id", $leaveType->id)
            ->where("year", $year)->first();
        if (!$balance) return;

        $remaining = (float) ($balance->entitled_days + $balance->carried_forward - $balance->used_days);
        $toDeductFromVacation = 0;

        if ($days > $remaining) {
            $toDeductFromVacation = $days - $remaining;
        }

        $balance->increment("used_days", $days - $toDeductFromVacation);

        if ($toDeductFromVacation > 0 && $leaveType->code === "annual" && isset($balance->vacation_accumulated)) {
            $balance->decrement("vacation_accumulated", $toDeductFromVacation);
        }
    }

    public function restoreLeave(Employee $employee, LeaveType $leaveType, float $days, int $year): void
    {
        $balance = LeaveBalance::where("emp_id", $employee->id)
            ->where("leave_type_id", $leaveType->id)
            ->where("year", $year)->first();
        if ($balance) {
            $balance->decrement("used_days", $days);
        }
    }

    public function accumulateVacation(): int
    {
        $previousYear = Carbon::now()->subYear()->year;
        $currentYear = Carbon::now()->year;
        $expiryDate = Carbon::create($currentYear + 1, 5, 31);
        $count = 0;

        $annualLeaveTypes = LeaveType::where("code", "annual")->where("is_active", true)->get();

        foreach ($annualLeaveTypes as $leaveType) {
            $previousBalances = LeaveBalance::where("leave_type_id", $leaveType->id)
                ->where("year", $previousYear)
                ->where("entitled_days", ">", 0)
                ->get();

            foreach ($previousBalances as $prevBalance) {
                $vacationDays = floor($prevBalance->entitled_days / 2);

                if ($vacationDays <= 0) continue;

                $balance = LeaveBalance::firstOrCreate(
                    [
                        "emp_id" => $prevBalance->emp_id,
                        "leave_type_id" => $leaveType->id,
                        "year" => $currentYear,
                    ],
                    [
                        "entitled_days" => 0,
                        "used_days" => 0,
                        "carried_forward" => 0,
                        "vacation_accumulated" => $vacationDays,
                        "vacation_expiry_date" => $expiryDate,
                    ]
                );

                if ($balance->wasRecentlyCreated === false) {
                    $balance->update([
                        "vacation_accumulated" => $balance->vacation_accumulated + $vacationDays,
                        "vacation_expiry_date" => $expiryDate,
                    ]);
                }

                $count++;
            }
        }

        return $count;
    }
}
