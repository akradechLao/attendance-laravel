<?php

namespace App\Helpers;

use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * RULE: ครบ 1 ชม.แรกถึงนับ หลังจากนั้นนับตามจริง
     * 50 นาที → 0 ชม.
     * 1 ชม. 5 นาที → 1 ชม. 5 นาที
     *
     * รองรับกะข้ามคืน (check_in 03:00, check_out 11:00 = 8 ชม.)
     */
    public static function calculateWorkMinutes(Carbon $checkIn, Carbon $checkOut): int
    {
        // ถ้า check_out < check_in = ข้ามวัน (overnight)
        if ($checkOut->lt($checkIn)) {
            $totalMinutes = (int) $checkIn->diffInSeconds($checkOut) / 60;
            // เพิ่ม 24 ชม. เพราะข้ามวัน
            $totalMinutes += 24 * 60;
        } else {
            $totalMinutes = (int) $checkIn->diffInMinutes($checkOut);
        }

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

    /**
     * คำนวณนาทีที่สาย (รองรับกะข้ามคืน)
     * กะ 20:00-04:00, เข้างาน 20:30 → สาย 30 นาที
     * กะ 20:00-04:00, เข้างาน 03:30 → สาย 0 นาที (เร็ว 30 นาที)
     */
    public static function calculateLateMinutes(Carbon $workStartTime, Carbon $checkInTime): int
    {
        if ($checkInTime->lte($workStartTime)) {
            return 0; // ถึงก่อนหรือตรงเวลา
        }
        return (int) $workStartTime->diffInMinutes($checkInTime);
    }

    /**
     * หาว่าวันที่สแกนเป็นวันเริ่มกะหรือไม่
     * กะข้ามคืน (20:00-04:00): สแกน 03:00 → เริ่มกะเมื่อวาน 20:00
     * กะปกติ (08:00-17:00): สแกน 08:30 → เริ่มกะวันนี้ 08:00
     */
    public static function getShiftStartDate(Carbon $now, string $shiftStartTime, string $shiftEndTime, bool $isOvernight): Carbon
    {
        if (!$isOvernight) {
            return Carbon::today();
        }

        $start = Carbon::parse($shiftStartTime);
        $end = Carbon::parse($shiftEndTime);

        // ถ้าเลยเวลาสิ้นสุดกะแล้ว (เช่น สแกน 05:00 กะสิ้นสุด 04:00)
        // แสดงว่ากะเริ่มเมื่อวาน
        if ($now->format('H:i') >= $end->format('H:i')) {
            return Carbon::yesterday();
        }

        // ถ้ายังไม่เลยเวลาสิ้นสุดกะ (เช่น สแกน 03:00 กะสิ้นสุด 04:00)
        // แสดงว่ากะเริ่มเมื่อวาน
        if ($now->format('H:i') < $end->format('H:i')) {
            return Carbon::yesterday();
        }

        return Carbon::today();
    }

    /**
     * ได้เวลาเริ่มงานจริงของกะ (ใช้สำหรับคำนวณสาย)
     * กะข้ามคืน 20:00-04:00, สแกน 03:00 → เวลาเริ่ม = 20:00 เมื่อวาน
     */
    public static function getActualWorkStartTime(Carbon $shiftStartDate, string $shiftStartTime): Carbon
    {
        return Carbon::parse($shiftStartDate->toDateString() . ' ' . $shiftStartTime);
    }
}
