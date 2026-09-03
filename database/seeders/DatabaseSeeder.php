<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            AdminUserSeeder::class,
            WorkShiftSeeder::class,
            LeaveTypeSeeder::class,
            OfficeLocationSeeder::class,
            CompanyHolidaySeeder::class,
        ]);
    }
}
