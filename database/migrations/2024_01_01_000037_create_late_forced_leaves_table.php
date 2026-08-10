<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_forced_leaves', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id')->constrained('employees');
            $table->foreignId('attendance_log_id')->constrained('attendance_logs');
            $table->date('date');
            $table->integer('late_minutes')->comment('นาทีที่สาย');
            $table->integer('leave_minutes')->default(60)->comment('จำนวนนาทีลา (1 ชม. = 60)');
            $table->string('leave_type')->default('personal')->comment('ลากิจ/ลาป่วย/etc');
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable()->comment('เหตุผล');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_forced_leaves');
    }
};
