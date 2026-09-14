<?php

namespace App\Console\Commands;

use App\Helpers\AttendanceCalculator;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\ShiftResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * แก้ไขค่า late_minutes ที่ผิดเพี้ยนจากบัคเก่า (ShiftResolver คำนวณกะข้ามคืนผิดสำหรับ
 * กะที่เริ่ม/จบวันเดียวกันแต่ตั้ง flag is_overnight ไว้ เช่น WC0014) - หาแถวที่ late_minutes
 * สูงผิดปกติ (เกิน threshold) แล้วคำนวณใหม่โดยใช้กะที่ resolve ถูกต้องแล้ว ณ ตอนนี้
 *
 * ค่าเริ่มต้นเป็น dry-run ต้องใส่ --apply ถึงจะเขียนจริง
 */
class RecalculateLateMinutes extends Command
{
    protected $signature = 'attendance:recalc-late-minutes
        {--employee= : จำกัดเฉพาะพนักงาน (รหัส หรือ ชื่อบางส่วน) - ไม่ใส่ = ทุกคน}
        {--from= : วันที่เริ่ม (Y-m-d)}
        {--to= : วันที่สิ้นสุด (Y-m-d) ค่าเริ่มต้น = วันนี้}
        {--threshold=600 : ค่า late_minutes เดิมที่ถือว่าผิดปกติ (นาที) ค่าเริ่มต้น 600 = 10 ชม.}
        {--apply : เขียนข้อมูลจริง (ไม่ใส่ = dry-run แสดงตัวอย่างอย่างเดียว)}';

    protected $description = 'คำนวณ late_minutes ใหม่สำหรับแถวที่มีค่าผิดปกติสูงเกินจริง (เช่นจากบัคกะข้ามคืน)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $threshold = (int) $this->option('threshold');
        $to = $this->option('to') ?: now()->toDateString();
        $from = $this->option('from') ?: '2020-01-01';

        $query = AttendanceLog::whereNotNull('late_minutes')
            ->where('late_minutes', '>', $threshold)
            ->whereBetween('date', [$from, $to])
            ->with('employee');

        if ($employeeQuery = $this->option('employee')) {
            $ids = Employee::where('employee_code', $employeeQuery)
                ->orWhere('name', 'like', "%{$employeeQuery}%")
                ->pluck('id');
            $query->whereIn('emp_id', $ids);
        }

        $logs = $query->orderBy('date')->get();

        if ($logs->isEmpty()) {
            $this->info('ไม่พบแถวที่ late_minutes เกิน ' . $threshold . ' นาที ในช่วงที่กำหนด');
            return Command::SUCCESS;
        }

        $this->info('พบ ' . $logs->count() . ' แถวที่ late_minutes ผิดปกติ (> ' . $threshold . ' นาที):');
        $this->newLine();

        $changes = [];
        foreach ($logs as $log) {
            $date = $log->date instanceof Carbon ? $log->date->toDateString() : (string) $log->date;
            $resolved = ShiftResolver::resolve($log->employee, $date);

            if (!$resolved['start_time'] || !$log->check_in) {
                $this->warn("  ข้าม log #{$log->id} ({$log->employee->name} {$date}) - ไม่มีเวลาเริ่มกะหรือ check_in");
                continue;
            }

            $checkInTimeOnly = $log->check_in instanceof Carbon ? $log->check_in->format('H:i:s') : substr((string) $log->check_in, 0, 8);
            $workStart = Carbon::parse($date . ' ' . $resolved['start_time']);
            $checkIn = Carbon::parse($date . ' ' . $checkInTimeOnly);

            $newLate = AttendanceCalculator::calculateLateMinutes($workStart, $checkIn);
            $newStatus = $newLate > 0 ? 'late' : 'on_time';

            $this->line(sprintf(
                '  #%d %s %s | เข้า %s | กะ %s (%s) | สาย: %d -> %d นาที | สถานะเดิม: %s -> %s',
                $log->id,
                $log->employee->name,
                $date,
                $checkInTimeOnly,
                $resolved['shift_code'] ?? '-',
                $resolved['start_time'],
                $log->late_minutes,
                $newLate,
                $log->original_status,
                $newStatus
            ));

            $changes[] = [
                'log' => $log,
                'late_minutes' => $newLate > 0 ? $newLate : null,
                'status' => $newStatus,
            ];
        }

        $this->newLine();

        if (!$apply) {
            $this->warn('*** DRY RUN *** ไม่มีการเขียนข้อมูลจริง - ใส่ --apply เพื่อดำเนินการจริง');
            return Command::SUCCESS;
        }

        foreach ($changes as $c) {
            $log = $c['log'];
            $update = [
                'late_minutes' => $c['late_minutes'],
            ];
            // อัปเดต original_status เสมอ (เป็นค่าที่ระบบคำนวณเอง) แต่แก้ check_in_status/
            // final_status เฉพาะตอนที่ยังตรงกับ original_status เดิม (ไม่เคยถูกปรับแก้มือ
            // ผ่านหน้า "ปรับเวลาการเข้างาน" มาก่อน) เพื่อไม่ให้ไปทับการปรับแก้ที่ตั้งใจไว้
            if ($log->check_in_status === $log->original_status) {
                $update['check_in_status'] = $c['status'];
            }
            if ($log->final_status === $log->original_status) {
                $update['final_status'] = $c['status'];
            }
            $update['original_status'] = $c['status'];

            $log->update($update);
        }

        $this->info('แก้ไข ' . count($changes) . ' แถวเรียบร้อย');

        return Command::SUCCESS;
    }
}
