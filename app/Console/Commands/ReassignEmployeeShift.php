<?php

namespace App\Console\Commands;

use App\Helpers\ShiftCodeHelper;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * แก้ไขกรณีพนักงานถูกมอบหมายกะผิด (roster ใน employee_shifts และ/หรือ override
 * รายวันใน shift_schedules) - ย้ายให้ไปกะที่ถูกต้องแทน ตั้งแต่วันที่กำหนดเป็นต้นไป
 *
 * ค่าเริ่มต้นเป็น dry-run (แสดงสิ่งที่จะเปลี่ยนแปลง) ต้องใส่ --apply ถึงจะเขียนจริง
 */
class ReassignEmployeeShift extends Command
{
    protected $signature = 'attendance:reassign-shift
        {employee : รหัสพนักงาน หรือ ชื่อ (ค้นหาบางส่วนได้)}
        {shift_code : รหัสกะที่ถูกต้อง เช่น WC0015}
        {--from= : วันที่เริ่มมีผล (Y-m-d) ค่าเริ่มต้น = วันที่มีบันทึกเข้างานแรกสุด}
        {--to= : วันที่สิ้นสุดของการล้าง shift_schedules เดิมที่ผิด (Y-m-d) ค่าเริ่มต้น = วันนี้}
        {--apply : เขียนข้อมูลจริง (ไม่ใส่ = dry-run แสดงตัวอย่างอย่างเดียว)}';

    protected $description = 'ย้ายพนักงานไปกะที่ถูกต้อง (แก้ roster + ล้าง daily override เดิมที่ผิดในช่วงวันที่กำหนด)';

    public function handle(): int
    {
        $employeeQuery = trim($this->argument('employee'));
        $shiftCode = strtoupper(trim($this->argument('shift_code')));
        $apply = (bool) $this->option('apply');

        $shiftDef = ShiftCodeHelper::get($shiftCode);
        if (!$shiftDef) {
            $this->error("ไม่รู้จักรหัสกะ: {$shiftCode}");
            return Command::FAILURE;
        }

        $workShift = WorkShift::where('group_number', $shiftDef['group'])->first();
        if (!$workShift) {
            $this->error("ไม่พบ work_shifts row สำหรับ group_number={$shiftDef['group']} ({$shiftCode})");
            return Command::FAILURE;
        }

        $employees = Employee::where('employee_code', $employeeQuery)
            ->orWhere('name', 'like', "%{$employeeQuery}%")
            ->get();

        if ($employees->isEmpty()) {
            $this->error("ไม่พบพนักงานที่ตรงกับ: {$employeeQuery}");
            return Command::FAILURE;
        }
        if ($employees->count() > 1) {
            $this->error('พบมากกว่า 1 คน กรุณาระบุให้ชัดเจนขึ้น (ใช้รหัสพนักงานแทน):');
            foreach ($employees as $e) {
                $this->line("  - {$e->employee_code}: {$e->name}");
            }
            return Command::FAILURE;
        }

        $employee = $employees->first();

        $from = $this->option('from')
            ?: (DB::table('attendance_logs')->where('emp_id', $employee->id)->min('date'))
            ?: now()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();

        $this->info("พนักงาน: {$employee->name} ({$employee->employee_code})");
        $this->info("กะเป้าหมาย: {$shiftCode} ({$shiftDef['start']}-{$shiftDef['end']})");
        $this->info("ช่วงวันที่มีผล: {$from} ถึง {$to}");
        $this->newLine();

        $currentRoster = DB::table('employee_shifts')->where('employee_id', $employee->id)->get();
        $this->line('Roster (employee_shifts) ปัจจุบัน: ' . $currentRoster->count() . ' แถว');
        foreach ($currentRoster as $r) {
            $this->line("  - work_shift_id={$r->work_shift_id} start_date={$r->start_date} end_date={$r->end_date}");
        }

        $wrongSchedules = DB::table('shift_schedules')
            ->where('emp_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->where('shift_code', '!=', $shiftCode)
            ->where('day_type', 'working')
            ->get();
        $this->line('shift_schedules ที่จะถูกล้าง (day_type=working, shift_code ไม่ตรงเป้าหมาย): ' . $wrongSchedules->count() . ' วัน');
        if ($wrongSchedules->isNotEmpty()) {
            $this->line('  วันที่: ' . $wrongSchedules->pluck('work_date')->implode(', '));
        }
        $this->newLine();

        if (!$apply) {
            $this->warn('*** DRY RUN *** ไม่มีการเขียนข้อมูลจริง - ใส่ --apply เพื่อดำเนินการจริง');
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($employee, $workShift, $from, $shiftCode, $to) {
            DB::table('employee_shifts')->where('employee_id', $employee->id)->delete();
            DB::table('employee_shifts')->insert([
                'employee_id' => $employee->id,
                'work_shift_id' => $workShift->id,
                'start_date' => $from,
                'end_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shift_schedules')
                ->where('emp_id', $employee->id)
                ->whereBetween('work_date', [$from, $to])
                ->where('shift_code', '!=', $shiftCode)
                ->where('day_type', 'working')
                ->delete();
        });

        $this->info('เรียบร้อย: ตั้ง roster ใหม่เป็น ' . $shiftCode . ' ตั้งแต่ ' . $from . ' เป็นต้นไป และล้าง daily override เดิมที่ผิดแล้ว');

        return Command::SUCCESS;
    }
}
