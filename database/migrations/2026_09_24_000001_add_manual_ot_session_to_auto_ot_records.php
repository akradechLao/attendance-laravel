<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_ot_records', function (Blueprint $table) {
            if (!Schema::hasColumn('auto_ot_records', 'session_started_at')) {
                $table->time('session_started_at')->nullable()->after('shift_time')->comment('เวลาเริ่มโอที (manual)');
            }
            if (!Schema::hasColumn('auto_ot_records', 'session_ended_at')) {
                $table->time('session_ended_at')->nullable()->after('session_started_at')->comment('เวลาจบทะโอที (manual)');
            }
        });

        // รองรับ ot_type = manual (เช็คอิน/ออกโอทีด้วยตนเองจากคีออส)
        try {
            // MySQL/MariaDB: ขยาย ENUM; SQLite เก็บเป็น string อยู่แล้ว ข้ามได้
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE auto_ot_records MODIFY ot_type ENUM('before_shift','after_shift','manual') NOT NULL COMMENT 'before_shift = มาเร็ว, after_shift = กลับช้า, manual = โอที manual'");
            }
        } catch (\Throwable $e) {
            // ข้ามถ้าดัดแปลงไม่ได้
        }
    }

    public function down(): void
    {
        Schema::table('auto_ot_records', function (Blueprint $table) {
            if (Schema::hasColumn('auto_ot_records', 'session_started_at')) {
                $table->dropColumn('session_started_at');
            }
            if (Schema::hasColumn('auto_ot_records', 'session_ended_at')) {
                $table->dropColumn('session_ended_at');
            }
        });
    }
};
