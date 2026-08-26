<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_date_index'");
        if (empty($hasIndex)) {
            DB::statement('ALTER TABLE attendance_logs ADD INDEX attendance_logs_date_index (date)');
        }

        $hasIndex2 = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_check_in_status_index'");
        if (empty($hasIndex2)) {
            DB::statement('ALTER TABLE attendance_logs ADD INDEX attendance_logs_check_in_status_index (check_in_status)');
        }
    }

    public function down(): void
    {
        $hasIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_date_index'");
        if (!empty($hasIndex)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_date_index');
        }

        $hasIndex2 = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_check_in_status_index'");
        if (!empty($hasIndex2)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_check_in_status_index');
        }
    }
};
