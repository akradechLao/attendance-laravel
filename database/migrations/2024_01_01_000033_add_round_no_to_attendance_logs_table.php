<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->integer('round_no')->default(1)->after('emp_id')->comment('รอบที่เช็คอินในวันเดียวกัน');
        });

        // ลบ unique เดิม (emp_id, date) แล้วสร้างใหม่เป็น (emp_id, date, round_no)
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['emp_id', 'date']);
            $table->unique(['emp_id', 'date', 'round_no']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['emp_id', 'date', 'round_no']);
            $table->unique(['emp_id', 'date']);
            $table->dropColumn('round_no');
        });
    }
};
