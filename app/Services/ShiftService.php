<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use App\Models\Employee;

class ShiftService
{
    public function getShiftSchedule(int $empId, string $date): ?ShiftSchedule
    {
        return ShiftSchedule::where('emp_id', $empId)
            ->where('work_date', $date)
            ->first();
    }

    public function assignShift(array $data): ShiftSchedule
    {
        return ShiftSchedule::updateOrCreate(
            [
                'emp_id' => $data['emp_id'],
                'work_date' => $data['work_date'],
            ],
            [
                'company_id' => $data['company_id'],
                'shift_code' => $data['shift_code'],
                'day_type' => $data['day_type'] ?? 'working',
            ]
        );
    }

    public function getShiftCode(string $startTime, string $endTime): string
    {
        $start = (int) substr($startTime, 0, 2);

        if ($start == 7) return 'วันหยุด';
        if ($start == 8) return 'Full Day';
        if ($start == 12) return 'Half Day';
        if ($start == 13) return 'Half Day';
        if ($start == 17) return 'Night';

        return 'Full Day';
    }
}
