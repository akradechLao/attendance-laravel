<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('work_shift_id')->comment('วันเริ่มต้นกะ');
            $table->date('end_date')->nullable()->after('start_date')->comment('วันสิ้นสุดกะ');
            $table->dropUnique(['employee_id', 'work_shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->unique(['employee_id', 'work_shift_id']);
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
