<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\ShiftResolver;
use App\Services\SystemConfigService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCheckout extends Command
{
    protected $signature = 'attendance:auto-checkout {--date= : Date to process (YYYY-MM-DD, default: today)}';
    protected $description = 'Auto-fill missing check_out with estimated time based on employee shift schedule, flagged for supervisor approval';

    public function handle(): int
    {
        $date = $this->option('date') ?? Carbon::now('Asia/Bangkok')->format('Y-m-d');

        $this->info("Processing auto-checkout for: {$date}");
        $this->newLine();

        $logs = AttendanceLog::where('date', $date)
            ->whereNull('check_out')
            ->where('is_estimated', false)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No missing check_out records found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$logs->count()} records with missing check_out.");
        $this->newLine();

        $processed = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            $employee = Employee::find($log->emp_id);
            if (!$employee) {
                $this->warn("  Skip log #{$log->id}: employee not found");
                $skipped++;
                continue;
            }

            $shiftInfo = ShiftResolver::resolve($employee, $date);

            // ─── Overnight shift: ข้าม — ยังไม่ควรเติม checkout ───
            if ($shiftInfo['is_overnight'] ?? false) {
                $this->warn("  Skip #{$log->id} ({$employee->employee_code}): overnight shift ({$shiftInfo['start_time']}-{$shiftInfo['end_time']}), still working");
                $skipped++;
                continue;
            }

            $endTime = $shiftInfo['end_time'];

            if (!$endTime) {
                $this->warn("  Skip #{$log->id} ({$employee->employee_code}): no shift end_time");
                $skipped++;
                continue;
            }

            // ─── ตรวจสอบว่า check_in + work_hours ยังไม่สิ้นสุด ───
            $checkInTime = $log->check_in instanceof Carbon
                ? $log->check_in->format('H:i:s')
                : substr($log->check_in, 0, 8);

            $now = Carbon::now('Asia/Bangkok');
            $shiftEnd = Carbon::parse($date . ' ' . $endTime);
            $autoCheckoutBuffer = SystemConfigService::get('auto_checkout_buffer_minutes', 30);

            // ถ้ายังไม่ถึงเวลาสิ้นสุดกะ (เกิน buffer นาทีหลัง end_time) → ยังไม่เติม
            if ($now->lt($shiftEnd->copy()->addMinutes($autoCheckoutBuffer))) {
                $this->warn("  Skip #{$log->id} ({$employee->employee_code}): shift not ended yet (ends {$endTime})");
                $skipped++;
                continue;
            }

            $log->update([
                'check_out' => $endTime,
                'is_estimated' => true,
            ]);

            $shiftLabel = $shiftInfo['shift_code'] ?? 'office default';
            $this->line("  <info>✓</info> {$employee->employee_code} ({$employee->name}) → checkout {$endTime} [{$shiftLabel}] (estimated)");
            $processed++;
        }

        $this->newLine();
        $this->info("Done: {$processed} processed, {$skipped} skipped");
        return Command::SUCCESS;
    }
}
