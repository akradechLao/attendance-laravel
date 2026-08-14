<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_ot_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id')->constrained('employees');
            $table->foreignId('attendance_log_id')->constrained('attendance_logs');
            $table->date('date');
            $table->enum('ot_type', ['before_shift', 'after_shift'])->comment('before_shift = มาเร็ว, after_shift = กลับช้า');
            $table->time('actual_time')->comment('เวลาเข้า/ออกจริง');
            $table->time('shift_time')->comment('เวลาเริ่มงาน/เลิกงานตามกะ');
            $table->integer('ot_minutes')->comment('จำนวนนาที OT');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'date']);
            $table->index(['status', 'date']);
            $table->index(['ot_type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_ot_records');
    }
};
