<?php

namespace App\Services;

use App\Helpers\ShiftCodeHelper;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\WorkShift;
use Carbon\Carbon;

/**
 * Centralized shift resolution for any employee on any date.
 *
 * Priority:
 * 1. shift_schedules (daily override — if entry exists for the date)
 * 2. employee_shifts → work_shifts (roster assignment — if active for the date)
 * 3. office_locations.work_start_time (company default)
 * 4. null (no shift info)
 */
class ShiftResolver
{
    /**
     * Resolve shift info for an employee on a given date.
     *
     * @return array{
     *     shift_code: ?string,
     *     start_time: ?string,
     *     end_time: ?string,
     *     work_hours: ?int,
     *     is_overnight: ?bool,
     *     source: string,
     *     work_shift_id: ?int,
     * }
     */
    public static function resolve(Employee $employee, string $date): array
    {
        // 1. Check shift_schedules (daily override)
        $schedule = ShiftSchedule::where('emp_id', $employee->id)
            ->where('work_date', $date)
            ->first();

        if ($schedule) {
            $times = ShiftCodeHelper::getTimes($schedule->shift_code);
            $shift = ShiftCodeHelper::get($schedule->shift_code);
            return [
                'shift_code' => $schedule->shift_code,
                'start_time' => $times['start'],
                'end_time' => $times['end'],
                'work_hours' => $shift['hours'] ?? null,
                'is_overnight' => $shift['overnight'] ?? null,
                'source' => 'shift_schedules',
                'work_shift_id' => null,
            ];
        }

        // 2. Check employee_shifts → work_shifts (roster)
        $workShift = self::findWorkShiftForDate($employee, $date);
        if ($workShift) {
            $code = ShiftCodeHelper::codeFromGroup($workShift->group_number)
                ?? 'WC' . str_pad($workShift->group_number + 1, 4, '0', STR_PAD_LEFT);

            // Check for override times on the pivot
            $pivot = $workShift->pivot;
            $startTime = $pivot->override_start_time
                ? ($pivot->override_start_time instanceof Carbon ? $pivot->override_start_time->format('H:i') : substr($pivot->override_start_time, 0, 5))
                : ($workShift->start_time instanceof Carbon ? $workShift->start_time->format('H:i') : substr($workShift->start_time, 0, 5));
            $endTime = $pivot->override_end_time
                ? ($pivot->override_end_time instanceof Carbon ? $pivot->override_end_time->format('H:i') : substr($pivot->override_end_time, 0, 5))
                : ($workShift->end_time instanceof Carbon ? $workShift->end_time->format('H:i') : substr($workShift->end_time, 0, 5));

            return [
                'shift_code' => $code,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'work_hours' => $workShift->work_hours,
                'is_overnight' => $workShift->is_overnight,
                'source' => 'employee_shifts',
                'work_shift_id' => $workShift->id,
            ];
        }

        // 3. Fallback to office location default
        $officeLocation = $employee->getAssignedOfficeLocation();
        if ($officeLocation && $officeLocation->work_start_time) {
            $start = $officeLocation->work_start_time instanceof Carbon
                ? $officeLocation->work_start_time->format('H:i')
                : substr($officeLocation->work_start_time, 0, 5);

            // Assume 8-hour work day for office default
            $endCarbon = Carbon::parse($date . ' ' . $start)->addHours(8);
            return [
                'shift_code' => null,
                'start_time' => $start,
                'end_time' => $endCarbon->format('H:i'),
                'work_hours' => 8,
                'is_overnight' => false,
                'source' => 'office_default',
                'work_shift_id' => null,
            ];
        }

        // 4. No shift info
        return [
            'shift_code' => null,
            'start_time' => null,
            'end_time' => null,
            'work_hours' => null,
            'is_overnight' => null,
            'source' => 'none',
            'work_shift_id' => null,
        ];
    }

    /**
     * Get the work start time as a Carbon instance for late calculation.
     */
    public static function getWorkStartTime(Employee $employee, string $date): ?Carbon
    {
        $info = self::resolve($employee, $date);
        if (!$info['start_time']) return null;
        return Carbon::parse($date . ' ' . $info['start_time']);
    }

    /**
     * Get the work end time as a Carbon instance.
     */
    public static function getWorkEndTime(Employee $employee, string $date): ?Carbon
    {
        $info = self::resolve($employee, $date);
        if (!$info['end_time']) return null;
        return Carbon::parse($date . ' ' . $info['end_time']);
    }

    /**
     * Find the active work shift for an employee on a given date.
     */
    private static function findWorkShiftForDate(Employee $employee, string $date): ?WorkShift
    {
        return $employee->workShifts()
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->first();
    }

    /**
     * Get all assigned shifts for an employee with active status.
     *
     * @return array
     */
    public static function getAllAssignedShifts(Employee $employee, ?string $date = null): array
    {
        $date = $date ?? Carbon::now('Asia/Bangkok')->format('Y-m-d');
        $employee->load('workShifts');

        $result = [];
        foreach ($employee->workShifts as $ws) {
            $pivot = $ws->pivot;
            $isActive = true;
            if ($pivot->start_date && $date < $pivot->start_date) $isActive = false;
            if ($pivot->end_date && $date > $pivot->end_date) $isActive = false;

            $code = ShiftCodeHelper::codeFromGroup($ws->group_number)
                ?? 'WC' . str_pad($ws->group_number + 1, 4, '0', STR_PAD_LEFT);

            $sTime = $ws->start_time instanceof Carbon ? $ws->start_time->format('H:i') : substr($ws->start_time, 0, 5);
            $eTime = $ws->end_time instanceof Carbon ? $ws->end_time->format('H:i') : substr($ws->end_time, 0, 5);

            $result[] = [
                'group_number' => $ws->group_number,
                'shift_code' => $code,
                'start_time' => $sTime,
                'end_time' => $eTime,
                'work_hours' => $ws->work_hours,
                'is_overnight' => $ws->is_overnight,
                'start_date' => $pivot->start_date instanceof Carbon ? $pivot->start_date->format('Y-m-d') : $pivot->start_date,
                'end_date' => $pivot->end_date instanceof Carbon ? $pivot->end_date->format('Y-m-d') : $pivot->end_date,
                'is_active' => $isActive,
            ];
        }

        return $result;
    }
}
