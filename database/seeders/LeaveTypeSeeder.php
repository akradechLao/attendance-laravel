<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['name' => 'ลาป่วย', 'code' => 'sick', 'max_days' => 30, 'max_days_per_year' => 30, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลากิจ', 'code' => 'personal', 'max_days' => 6, 'max_days_per_year' => 6, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลาพักร้อน', 'code' => 'annual', 'max_days' => 6, 'max_days_per_year' => 6, 'accrual' => 1, 'carry_forward' => 1],
            ['name' => 'ลาคลอด', 'code' => 'maternity', 'max_days' => 120, 'max_days_per_year' => 120, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลากิจไม่รับค่าจ้าง', 'code' => 'unpaid', 'max_days' => 0, 'max_days_per_year' => 0, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลาบวช', 'code' => 'ordination', 'max_days' => 15, 'max_days_per_year' => 15, 'accrual' => 0, 'carry_forward' => 0],
        ];

        foreach ($leaveTypes as $type) {
            DB::table('leave_types')->updateOrInsert(
                ['company_id' => null, 'code' => $type['code']],
                array_merge($type, [
                    'company_id' => null,
                    'quota_monthly' => 0,
                    'advance_days' => 0,
                    'quota_daily' => 0,
                    'quota_contract' => 0,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Global leave types seeded (' . count($leaveTypes) . ' types for all companies)!');
    }
}
