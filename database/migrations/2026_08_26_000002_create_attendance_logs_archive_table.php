<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs_archive', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id');
            $table->integer('round_no')->default(1);
            $table->time('check_in')->nullable();
            $table->enum('check_in_status', ['late', 'on_time'])->nullable();
            $table->time('check_out')->nullable();
            $table->string('lat_long')->nullable();
            $table->date('date');
            $table->enum('scan_type', ['office_scan', 'remote_scan'])->default('office_scan');
            $table->decimal('remote_latitude', 10, 8)->nullable();
            $table->decimal('remote_longitude', 11, 8)->nullable();
            $table->integer('remote_accuracy')->nullable();
            $table->string('remote_location_name')->nullable();
            $table->string('remote_custom_name')->nullable();
            $table->string('original_status', 20)->nullable();
            $table->string('final_status', 20)->nullable();
            $table->integer('late_minutes')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->text('adjustment_note')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('check_in_photo')->nullable();
            $table->string('check_out_photo')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('emp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs_archive');
    }
};
