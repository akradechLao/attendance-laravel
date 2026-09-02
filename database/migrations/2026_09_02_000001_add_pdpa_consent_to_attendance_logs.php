<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'pdpa_consent')) {
                $table->boolean('pdpa_consent')->default(false)->after('check_out_face_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('pdpa_consent');
        });
    }
};
