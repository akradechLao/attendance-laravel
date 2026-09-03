<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestShiftAssignDummy extends Command
{
    protected $signature = 'test:shift-assign-dummy';
    protected $description = 'สร้าง dummy data ทดสอบระบบมอบหมายกะ工作';

    public function handle()
    {
        $now = Carbon::now('Asia/Bangkok');

        // Calculate cycle dates
        if ($now->day >= 19) {
            $cycleStart = $now->copy()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->addMonth()->startOfMonth()->addDays(17);
        } else {
            $cycleStart = $now->copy()->subMonth()->startOfMonth()->addDays(18);
            $cycleEnd = $now->copy()->startOfMonth()->addDays(17);
        }

        $this->info("สร้าง dummy data ทดสอบระบบมอบหมายกะ work");
        $this->info("รอบกะ: {$cycleStart->format('d/m/Y')} - {$cycleEnd->format('d/m/Y')}");
        $this->newLine();

        // Get employees with multiple available shifts from each company
        $employees = DB::table('employee_shifts as es')
            ->join('employees as e', 'es.employee_id', '=', 'e.id')
            ->join('work_shifts as ws', 'es.work_shift_id', '=', 'ws.id')
            ->whereNull('es.start_date')
            ->whereIn('e.company_id', [1, 2, 3, 4])
            ->select('e.id as employee_id', 'e.employee_code', 'e.name', 'e.company_id', 'ws.id as work_shift_id', 'ws.group_number')
            ->get()
            ->groupBy('employee_id')
            ->filter(fn($group) => $group->count() > 1)
            ->values();

        $totalCreated = 0;

        foreach ($employees as $empShifts) {
            $empId = $empShifts->first()->employee_id;
            $empCode = $empShifts->first()->employee_code;
            $empName = $empShifts->first()->name;
            $companyId = $empShifts->first()->company_id;

            // Pick a random shift from available shifts
            $randomShift = $empShifts->random();
            $shiftCode = 'WC' . str_pad($randomShift->group_number + 1, 4, '0', STR_PAD_LEFT);

            // Delete existing assignments for this cycle
            DB::table('shift_schedules')
                ->where('emp_id', $empId)
                ->where('work_date', '>=', $cycleStart->toDateString())
                ->where('work_date', '<=', $cycleEnd->toDateString())
                ->delete();

            // Create new assignments for each working day (skip Sunday)
            $records = [];
            $current = $cycleStart->copy();
            while ($current->lte($cycleEnd)) {
                if ($current->dayOfWeek !== Carbon::SUNDAY) {
                    $records[] = [
                        'company_id' => $companyId,
                        'emp_id' => $empId,
                        'work_date' => $current->toDateString(),
                        'shift_code' => $shiftCode,
                        'day_type' => 'work',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $current->addDay();
            }

            if (!empty($records)) {
                DB::table('shift_schedules')->insert($records);
                $totalCreated += count($records);
                $this->line("  ✓ {$empCode} {$empName} → {$shiftCode} (" . count($records) . " วัน)", 'green');
            }
        }

        $this->newLine();
        $this->info("สร้าง dummy data สำเร็จ!");
        $this->info("  - พนักงาน: {$employees->count()} คน");
        $this->info("  - บันทึกทั้งหมด: {$totalCreated} records");
        $this->info("  - รอบกะ: {$cycleStart->format('d/m')} - {$cycleEnd->format('d/m')}");
        $this->newLine();
        $this->warn("※ ข้อมูลนี้เป็นข้อมูลทดสอบ (dummy data) สำหรับทดสอบระบบมอบหมายกะ工作");

        return 0;
    }
}
