<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'face_image')) {
                $table->text('face_image')->nullable()->after('is_verified');
            }
            if (!Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
                $table->text('check_out_face_image')->nullable()->after('face_image');
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
                $table->dropColumn('face_image');
            }
            if (Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
                $table->dropColumn('check_out_face_image');
            }
        });
    }
};
