<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emp_id');
            $table->string('type'); // leave_request, ot_request, wfh_request, leave_approved, leave_rejected, ot_approved, ot_rejected, wfh_approved, wfh_rejected
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('related_id')->nullable(); // ID of the related request
            $table->string('related_type')->nullable(); // LeaveRecord, OtRequest, WfhRecord
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('emp_id');
            $table->index('is_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_notifications');
    }
};
