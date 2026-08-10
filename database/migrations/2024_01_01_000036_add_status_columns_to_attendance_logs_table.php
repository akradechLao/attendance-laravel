<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // เพิ่ม original_status (สถานะดั้งเดิมตอนสแกน)
        $hasOriginalStatus = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'original_status'");
        if (empty($hasOriginalStatus)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN original_status VARCHAR(20) DEFAULT NULL AFTER check_in_status");
        }

        // เพิ่ม final_status (สถานะหลังหัวหน้าปรับแก้)
        $hasFinalStatus = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'final_status'");
        if (empty($hasFinalStatus)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN final_status VARCHAR(20) DEFAULT NULL AFTER original_status");
        }

        // เพิ่ม late_minutes
        $hasLateMinutes = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'");
        if (empty($hasLateMinutes)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN late_minutes INT DEFAULT NULL AFTER final_status");
        }

        // เพิ่ม adjusted_by
        $hasAdjustedBy = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'adjusted_by'");
        if (empty($hasAdjustedBy)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN adjusted_by BIGINT UNSIGNED NULL AFTER late_minutes");
        }

        // เพิ่ม adjusted_at
        $hasAdjustedAt = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'adjusted_at'");
        if (empty($hasAdjustedAt)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN adjusted_at TIMESTAMP NULL AFTER adjusted_by");
        }

        // เพิ่ม adjustment_note
        $hasAdjustmentNote = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'adjustment_note'");
        if (empty($hasAdjustmentNote)) {
            DB::statement("ALTER TABLE attendance_logs ADD COLUMN adjustment_note TEXT NULL AFTER adjusted_at");
        }
    }

    public function down(): void
    {
        $columns = ['adjustment_note', 'adjusted_at', 'adjusted_by', 'late_minutes', 'final_status', 'original_status'];
        foreach ($columns as $col) {
            $has = DB::select("SHOW COLUMNS FROM attendance_logs LIKE '$col'");
            if (!empty($has)) {
                DB::statement("ALTER TABLE attendance_logs DROP COLUMN $col");
            }
        }
    }
};
