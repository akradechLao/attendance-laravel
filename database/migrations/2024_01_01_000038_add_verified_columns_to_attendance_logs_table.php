<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasIsVerified = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'is_verified'");
        if (empty($hasIsVerified)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER adjustment_note");
        }

        $hasVerifiedBy = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'verified_by'");
        if (empty($hasVerifiedBy)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN verified_by BIGINT UNSIGNED NULL AFTER is_verified");
        }

        $hasVerifiedAt = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'verified_at'");
        if (empty($hasVerifiedAt)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN verified_at TIMESTAMP NULL AFTER verified_by");
        }

        // Index สำหรับค้นหา is_verified
        $hasIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_is_verified_index'");
        if (empty($hasIndex)) {
            DB::statement('ALTER TABLE attendance_logs ADD INDEX attendance_logs_is_verified_index (is_verified)');
        }
    }

    public function down(): void
    {
        $hasIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_is_verified_index'");
        if (!empty($hasIndex)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_is_verified_index');
        }

        $columns = ['verified_at', 'verified_by', 'is_verified'];
        foreach ($columns as $col) {
            $has = DB::select("SHOW COLUMNS FROM attendance_logs LIKE '$col'");
            if (!empty($has)) {
                DB::statement("ALTER TABLE attendance_logs DROP COLUMN $col");
            }
        }
    }
};
