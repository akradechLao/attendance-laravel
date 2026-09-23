<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('attendance_logs', 'attendance_logs_date_index')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->index('date', 'attendance_logs_date_index'));
        }
        if (!Schema::hasIndex('attendance_logs', 'attendance_logs_check_in_status_index')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->index('check_in_status', 'attendance_logs_check_in_status_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('attendance_logs', 'attendance_logs_date_index')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropIndex('attendance_logs_date_index'));
        }
        if (Schema::hasIndex('attendance_logs', 'attendance_logs_check_in_status_index')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropIndex('attendance_logs_check_in_status_index'));
        }
    }
};
