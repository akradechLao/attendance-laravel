<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id')->constrained('employees');
            $table->time('check_in')->nullable();
            $table->enum('check_in_status', ['late', 'on_time'])->nullable();
            $table->time('check_out')->nullable();
            $table->string('lat_long')->nullable();
            $table->date('date');
            
            // Remote check-in fields
            $table->enum('scan_type', ['office_scan', 'remote_scan'])->default('office_scan');
            $table->decimal('remote_latitude', 10, 8)->nullable();
            $table->decimal('remote_longitude', 11, 8)->nullable();
            $table->integer('remote_accuracy')->nullable()->comment('GPS accuracy in meters');
            $table->string('remote_location_name')->nullable()->comment('From Nominatim geocoding');
            $table->string('remote_custom_name')->nullable()->comment('User-entered location name');
            
            $table->timestamps();

            $table->unique(['emp_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
