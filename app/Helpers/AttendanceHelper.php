<?php

namespace App\Helpers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\ShiftResolver;
use Carbon\Carbon;

class AttendanceHelper
{
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
        } elseif ($lastOut->lt($firstIn)) {
            // กะข้ามคืน (เช่น เข้า 20:00 ออก 05:00) - ทั้งสองค่าถูก anchor เข้ากับ $date
            // เดียวกันไว้ด้านบน ถ้าออกงานเร็วกว่าเข้างานตามเวลานาฬิกา แปลว่าจริงๆ ออกในวันถัดไป
            $lastOut->addDay();
        }

        $totalMinutes = $firstIn->diffInMinutes($lastOut);
        $totalMinutes -= self::calculateBreakMinutes($empId, $date, $firstIn, $lastOut);
        $totalMinutes = max(0, $totalMinutes);

        return round($totalMinutes / 60, 1);
    }

    /**
     * คำนวณจำนวนนาทีพักที่ควรหักออกจากช่วงเวลาทำงานจริง ($workStart-$workEnd) โดยอิงเวลาพัก
     * ของกะที่ resolve ได้สำหรับพนักงาน+วันที่นั้น (ตั้งค่าต่อกะผ่านหน้าจัดการกะ) แทนค่าคงที่
     * เดิมที่ใช้ 60 นาที ช่วง 11:45-12:45 ตายตัวทุกกะ (ซึ่งไม่เคยหักให้กะกลางคืนเลย เพราะเวลา
     * เข้า-ออกของกะกลางคืนไม่เคยตรงกับช่วงกลางวันนั้น)
     *
     * ใช้ร่วมกันทั้งจาก calculateWorkedHours() และ AttendanceController::editEstimatedCheckout()
     * เพื่อไม่ให้มีตรรกะคำนวณพักซ้ำกันหลายจุดเหมือนเดิม
     */
    public static function calculateBreakMinutes(int $empId, string $date, Carbon $workStart, Carbon $workEnd): int
    {
        $employee = Employee::find($empId);
        if (!$employee) return 0;

        $shiftInfo = ShiftResolver::resolve($employee, $date);
        if (empty($shiftInfo['break_start']) || empty($shiftInfo['break_end'])) {
            return 0;
        }

        $breakStart = Carbon::parse($date . ' ' . $shiftInfo['break_start']);
        $breakEnd = Carbon::parse($date . ' ' . $shiftInfo['break_end']);

        // เวลาสิ้นสุดพักน้อยกว่าเวลาเริ่มพัก = ช่วงพักข้ามเที่ยงคืน (เช่น 23:00-00:00)
        if ($breakEnd->lte($breakStart)) {
            $breakEnd->addDay();
        }

        // กะข้ามคืนที่ช่วงพักอยู่หลังเที่ยงคืน (เช่น กะเริ่ม 20:00 พัก 00:00-01:00) - เวลาพักที่
        // แท้จริงอยู่ในวันถัดจาก $date ต้องเลื่อนไปให้ตรงกับ $workStart/$workEnd จริง
        if (($shiftInfo['is_overnight'] ?? false) && $breakStart->lt($workStart)) {
            $breakStart->addDay();
            $breakEnd->addDay();
        }

        if ($workStart->lt($breakEnd) && $workEnd->gt($breakStart)) {
            $effectiveStart = $workStart->gt($breakStart) ? $workStart : $breakStart;
            $effectiveEnd = $workEnd->lt($breakEnd) ? $workEnd : $breakEnd;
            return (int) $effectiveStart->diffInMinutes($effectiveEnd);
        }

        return 0;
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
