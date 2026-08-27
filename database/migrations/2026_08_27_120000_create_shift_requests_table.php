<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('emp_id')->constrained('employees');
            $table->foreignId('work_shift_id')->constrained('work_shifts');
            $table->enum('request_type', ['assign', 'modify', 'remove'])->default('assign');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('new_start_time')->nullable();
            $table->time('new_end_time')->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('supervisor_id')->nullable()->constrained('employees');
            $table->text('supervisor_note')->nullable();
            $table->timestamps();
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->time('override_start_time')->nullable()->after('end_date');
            $table->time('override_end_time')->nullable()->after('override_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn(['override_start_time', 'override_end_time']);
        });
        Schema::dropIfExists('shift_requests');
    }
};
