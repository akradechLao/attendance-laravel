<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            ['group_number' => 0, 'start_time' => '07:30', 'end_time' => '16:30', 'work_hours' => 8, 'is_overnight' => false],
            ['group_number' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'work_hours' => 8, 'is_overnight' => false],
            ['group_number' => 2, 'start_time' => '16:00', 'end_time' => '01:00', 'work_hours' => 8, 'is_overnight' => true],
            ['group_number' => 3, 'start_time' => '00:00', 'end_time' => '09:00', 'work_hours' => 8, 'is_overnight' => true],
            ['group_number' => 4, 'start_time' => '09:00', 'end_time' => '18:00', 'work_hours' => 8, 'is_overnight' => false],
            ['group_number' => 5, 'start_time' => '20:00', 'end_time' => '05:00', 'work_hours' => 8, 'is_overnight' => true],
            ['group_number' => 6, 'start_time' => '21:00', 'end_time' => '06:00', 'work_hours' => 8, 'is_overnight' => true],
            ['group_number' => 7, 'start_time' => '08:00', 'end_time' => '16:00', 'work_hours' => 7, 'is_overnight' => false],
            ['group_number' => 8, 'start_time' => '16:00', 'end_time' => '00:00', 'work_hours' => 7, 'is_overnight' => true],
            ['group_number' => 9, 'start_time' => '00:00', 'end_time' => '08:00', 'work_hours' => 7, 'is_overnight' => true],
        ];

        foreach ($shifts as $shift) {
            DB::table('work_shifts')->updateOrInsert(
                ['group_number' => $shift['group_number']],
                array_merge($shift, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
