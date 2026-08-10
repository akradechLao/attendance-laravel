<?php

namespace App\Helpers;

use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * RULE: ครบ 1 ชม.แรกถึงนับ หลังจากนั้นนับตามจริง
     * 50 นาที → 0 ชม.
     * 1 ชม. 5 นาที → 1 ชม. 5 นาที
     * 7 ชม. 45 นาที → 7 ชม. 45 นาที
     */
    public static function calculateWorkMinutes(Carbon $checkIn, Carbon $checkOut): int
    {
        $totalMinutes = (int) $checkIn->diffInMinutes($checkOut);

        if ($totalMinutes < 60) {
            return 0;
        }

        return $totalMinutes;
    }

    public static function formatMinutes($minutes): string
    {
        $minutes = (int) $minutes;
        if ($minutes <= 0) return '0 ชม.';

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return $hours . ' ชม.';
        }
        return $hours . ' ชม. ' . $mins . ' น.';
    }

    public static function calculateWorkHours(Carbon $checkIn, Carbon $checkOut): float
    {
        $minutes = self::calculateWorkMinutes($checkIn, $checkOut);
        return round($minutes / 60, 2);
    }
}
