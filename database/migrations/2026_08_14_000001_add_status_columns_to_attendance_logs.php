<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'original_status')) {
                $table->string('original_status')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'final_status')) {
                $table->string('final_status')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'original_status')) {
                $table->dropColumn('original_status');
            }
            if (Schema::hasColumn('attendance_logs', 'final_status')) {
                $table->dropColumn('final_status');
            }
        });
    }
};
