<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_logs', 'face_image')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->string('face_image', 64)->nullable());
        }
        if (!Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->string('check_out_face_image', 64)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_logs', 'face_image')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropColumn('face_image'));
        }
        if (Schema::hasColumn('attendance_logs', 'check_out_face_image')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropColumn('check_out_face_image'));
        }
    }
};
