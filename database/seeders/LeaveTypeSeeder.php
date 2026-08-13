<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->pluck('id')->toArray();

        $leaveTypes = [
            ['name' => 'ลาป่วย', 'code' => 'sick', 'max_days_per_year' => 60, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลากิจ', 'code' => 'personal', 'max_days_per_year' => 6, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลาพักร้อน', 'code' => 'annual', 'max_days_per_year' => 6, 'accrual' => 1, 'carry_forward' => 1],
            ['name' => 'ลาคลอด', 'code' => 'maternity', 'max_days_per_year' => 90, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลากิจไม่รับค่าจ้าง', 'code' => 'unpaid', 'max_days_per_year' => 0, 'accrual' => 0, 'carry_forward' => 0],
            ['name' => 'ลาบวช', 'code' => 'ordination', 'max_days_per_year' => 15, 'accrual' => 0, 'carry_forward' => 0],
        ];

        foreach ($companies as $companyId) {
            foreach ($leaveTypes as $type) {
                DB::table('leave_types')->updateOrInsert(
                    ['company_id' => $companyId, 'code' => $type['code']],
                    array_merge($type, ['company_id' => $companyId, 'quota_monthly' => 0, 'created_at' => now(), 'updated_at' => now()])
                );
            }
        }

        $this->command->info('Leave types seeded for ' . count($companies) . ' companies!');
    }
}
