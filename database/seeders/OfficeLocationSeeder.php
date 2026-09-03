<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficeLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // NTC (company_id=1)
            [
                'company_id' => 1,
                'name' => 'NTC สำนักงานใหญ่',
                'address' => 'สำนักงาน NTC',
                'latitude' => 13.09545487,
                'longitude' => 100.96382315,
                'radius_meters' => 200,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'is_active' => true,
            ],
            // ETC1992 (company_id=2)
            [
                'company_id' => 2,
                'name' => 'ETC1992 สำนักงานใหญ่',
                'address' => 'สำนักงาน ETC1992',
                'latitude' => 13.09545,
                'longitude' => 100.96382,
                'radius_meters' => 200,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'is_active' => true,
            ],
            // ETECH (company_id=3)
            [
                'company_id' => 3,
                'name' => 'ETECH สำนักงานใหญ่',
                'address' => 'สำนักงาน ETECH',
                'latitude' => 13.09553011,
                'longitude' => 100.96392030,
                'radius_meters' => 200,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'is_active' => true,
            ],
            // STC (company_id=4)
            [
                'company_id' => 4,
                'name' => 'STC สำนักงานใหญ่',
                'address' => 'สำนักงาน STC',
                'latitude' => 13.0955,
                'longitude' => 100.9639,
                'radius_meters' => 200,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'is_active' => true,
            ],
        ];

        foreach ($locations as $loc) {
            DB::table('office_locations')->updateOrInsert(
                ['company_id' => $loc['company_id'], 'name' => $loc['name']],
                array_merge($loc, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('Office locations seeded for 4 companies.');
    }
}
