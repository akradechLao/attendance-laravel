<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestShiftAssignCleanup extends Command
{
    protected $signature = 'test:shift-assign-cleanup';
    protected $description = 'ลบ dummy data ทดสอบระบบมอบหมายกะ工作';

    public function handle()
    {
        $now = Carbon::now('Asia/Bangkok');

        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->addMonth()->startOfMonth()->addDays(17);
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17);
        }

        $this->info("ลบ dummy data ทดสอบระบบมอบหมายกะ work");
        $this->info("รอบกะ: {$cycleStart->format('d/m/Y')} - {$cycleEnd->format('d/m/Y')}");
        $this->newLine();

        $deleted = DB::table('shift_schedules')
            ->where('work_date', '>=', $cycleStart->toDateString())
            ->where('work_date', '<=', $cycleEnd->toDateString())
            ->delete();

        $this->info("ลบสำเร็จ: {$deleted} records");
        $this->warn("※ ลบข้อมูลทดสอบทั้งหมดของรอบกะนี้");

        return 0;
    }
}
