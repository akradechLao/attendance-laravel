<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->pluck('id')->toArray();

        // Thai public holidays 2026 (from official gazette)
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'วันขึ้นปีใหม่', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-01-02', 'name' => 'วันหยุดพิเศษ (ชดเชยวันขึ้นปีใหม่)', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-03-03', 'name' => 'วันมาฆบูชา', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-04-06', 'name' => 'วันจักรี', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-04-13', 'name' => 'วันสงกรานต์', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-04-14', 'name' => 'วันสงกรานต์', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-04-15', 'name' => 'วันสงกรานต์', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-05-01', 'name' => 'วันแรงงานแห่งชาติ', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-05-04', 'name' => 'วันฉัตรมงคล', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-06-01', 'name' => 'วันวิสาขบูชา (ชดเชย)', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-06-03', 'name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-07-13', 'name' => 'วันอาสาฬหบูชา (ชดเชย)', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-07-28', 'name' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-07-29', 'name' => 'วันอาสาฬหบูชา', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-07-30', 'name' => 'วันเข้าพรรษา', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-08-12', 'name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระบรมราชชนนีพันปีหลวง / วันแม่แห่งชาติ', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-10-13', 'name' => 'วันคล้ายวันสวรรคตพระบาทสมเด็จพระบรมชนกาธิเบศร', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-10-23', 'name' => 'วันปิยมหาราช', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-12-07', 'name' => 'วันคล้ายวันพระบรมราชสมภพพระบาทสมเด็จพระบรมชนกาธิเบศร (ชดเชย)', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-12-10', 'name' => 'วันรัฐธรรมนูญ', 'type' => 'government', 'year' => 2026],
            ['date' => '2026-12-31', 'name' => 'วันสิ้นปี', 'type' => 'government', 'year' => 2026],
        ];

        $count = 0;
        foreach ($companies as $companyId) {
            foreach ($holidays as $holiday) {
                $exists = DB::table('company_holidays')
                    ->where('company_id', $companyId)
                    ->where('date', $holiday['date'])
                    ->exists();

                if (!$exists) {
                    DB::table('company_holidays')->insert(
                        array_merge($holiday, [
                            'company_id' => $companyId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])
                    );
                    $count++;
                }
            }
        }

        $this->command->info("{$count} holidays seeded for " . count($companies) . " companies.");
    }
}
