<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'face_image')) {
                DB::statement('ALTER TABLE attendance_logs MODIFY face_image VARCHAR(64) NULL COMMENT \'SHA-256 hash of scanned face image\'');
            }
            if (Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
                DB::statement('ALTER TABLE attendance_logs MODIFY check_out_face_image VARCHAR(64) NULL COMMENT \'SHA-256 hash of check-out face image\'');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'face_image')) {
                DB::statement('ALTER TABLE attendance_logs MODIFY face_image TEXT NULL');
            }
            if (Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
                DB::statement('ALTER TABLE attendance_logs MODIFY check_out_face_image TEXT NULL');
            }
        });
    }
};
