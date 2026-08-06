<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use App\Models\Employee;

class ShiftService
{
    public function getShiftSchedule(int $empId, string $date): ?ShiftSchedule
    {
        return ShiftSchedule::where('emp_id', $empId)
            ->where('date', $date)
            ->first();
    }

    public function assignShift(array $data): ShiftSchedule
    {
        return ShiftSchedule::updateOrCreate(
            [
                'emp_id' => $data['emp_id'],
                'date' => $data['date'],
            ],
            [
                'company_id' => $data['company_id'],
                'shift_code' => $data['shift_code'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'ot_approved' => $data['ot_approved'] ?? false,
                'ot_hours' => $data['ot_hours'] ?? 0,
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
