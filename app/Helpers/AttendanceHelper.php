<?php

namespace App\Helpers;

use App\Models\AttendanceLog;
use Carbon\Carbon;

class AttendanceHelper
{
    const BREAK_MINUTES = 60;

    public static function calculateWorkedHours(int $empId, string $date, ?string $untilTime = null): ?float
    {
        $logs = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_in')
            ->orderBy('check_in', 'asc')
            ->get();

        if ($logs->isEmpty()) return null;

        // check_in/check_out เป็นคอลัมน์ TIME ล้วน (ไม่มีวันที่ในตัวเอง) - Carbon::parse() บน
        // เวลาเปล่าๆ จะเติมวันที่ปัจจุบัน (วันนี้จริง) ให้อัตโนมัติเสมอ ไม่ใช่วันที่ $date ที่กำลัง
        // คำนวณ ทำให้ breakWindow ด้านล่าง (ซึ่งอิง $date ที่ถูกต้อง) เทียบกันคนละวันและไม่ตัดพัก
        // เที่ยงให้เลยสำหรับทุกวันที่ไม่ใช่วันนี้ - ต้อง anchor เวลาที่อ่านได้เข้ากับ $date เสมอ
        $firstIn = Carbon::parse($date . ' ' . self::timeOnly($logs->first()->check_in));

        $lastOut = null;
        foreach ($logs as $log) {
            if ($log->check_out) {
                $out = Carbon::parse($date . ' ' . self::timeOnly($log->check_out));
                if (!$lastOut || $out->gt($lastOut)) {
                    $lastOut = $out;
                }
            }
        }

        if (!$lastOut) {
            if ($untilTime) {
                $lastOut = Carbon::parse($untilTime);
            } elseif (Carbon::parse($date)->isToday()) {
                // วันนี้ยังไม่เช็คเอาท์ = งานยังไม่จบ แสดงชั่วโมงที่ทำมาแล้ว ณ ตอนนี้
                $lastOut = Carbon::now();
            } else {
                // วันในอดีตที่ไม่มี check_out เลย (ลืมสแกนออก และไม่มีใครมาปรับแก้ให้) -
                // เดิมใช้ Carbon::now() แทน ทำให้ตัวเลขชั่วโมงพองจนไม่มีความหมายถ้าดูรายงาน
                // ย้อนหลังหลายวัน จึงคืน null แทนเพื่อบอกว่าคำนวณไม่ได้ ไม่ใช่ทำงาน 0 ชั่วโมง
                return null;
            }
        }

        $totalMinutes = $firstIn->diffInMinutes($lastOut);

        $breakWindowStart = Carbon::parse($date . ' 11:45:00');
        $breakWindowEnd = Carbon::parse($date . ' 12:45:00');

        if ($firstIn->lt($breakWindowEnd) && $lastOut->gt($breakWindowStart)) {
            $totalMinutes -= self::BREAK_MINUTES;
        }

        $totalMinutes = max(0, $totalMinutes);

        return round($totalMinutes / 60, 1);
    }

    /**
     * ดึงเฉพาะส่วนเวลา (H:i:s) จากค่า check_in/check_out - รองรับทั้งกรณีเป็น Carbon
     * instance (จาก cast ของ model) และ string ธรรมดา
     */
    private static function timeOnly($value): string
    {
        return $value instanceof Carbon ? $value->format('H:i:s') : (string) $value;
    }

    public static function getFirstCheckIn(int $empId, string $date): ?string
    {
        $log = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_in')
            ->orderBy('check_in', 'asc')
            ->first();

        return $log?->check_in;
    }

    public static function getLastCheckOut(int $empId, string $date): ?string
    {
        $log = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_out')
            ->orderBy('check_out', 'desc')
            ->first();

        return $log?->check_out;
    }
}
