<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set default work_start_time = 08:00 and work_end_time = 17:00
     * for all office_locations where work_start_time is null.
     * User will manually adjust field operations (06:30-16:30) later.
     */
    public function up(): void
    {
        DB::table('office_locations')
            ->whereNull('work_start_time')
            ->update([
                'work_start_time' => '08:00:00',
                'work_end_time' => '17:00:00',
            ]);
    }

    /**
     * Reverse the migration - set back to null.
     */
    public function down(): void
    {
        DB::table('office_locations')
            ->where('work_start_time', '08:00:00')
            ->where('work_end_time', '17:00:00')
            ->update([
                'work_start_time' => null,
                'work_end_time' => null,
            ]);
    }
};
