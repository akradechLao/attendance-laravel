<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'late_minutes')) {
                $table->integer('late_minutes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_logs', 'late_minutes')) {
            Schema::table('attendance_logs', fn(Blueprint $t) => $t->dropColumn('late_minutes'));
        }
    }
};
