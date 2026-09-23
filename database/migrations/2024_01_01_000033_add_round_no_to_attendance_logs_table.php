<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_logs', 'round_no')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->integer('round_no')->default(1));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_logs', 'round_no')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropColumn('round_no'));
        }
    }
};
