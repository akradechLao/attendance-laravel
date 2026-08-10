<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandatory_ot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('emp_id')->constrained('employees');
            $table->date('ot_date')->comment('วันที่มอบหมาย OT');
            $table->time('start_time')->comment('เวลาเริ่ม OT');
            $table->time('end_time')->comment('เวลาสิ้นสุด OT');
            $table->string('reason')->nullable()->comment('เหตุผลที่มอบหมาย');
            $table->string('assigned_by')->comment('ผู้มอบหมาย');
            $table->enum('status', ['assigned', 'completed', 'cancelled'])->default('assigned');
            $table->timestamps();

            $table->unique(['emp_id', 'ot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandatory_ot_assignments');
    }
};
