<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_scan_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('employee_id')->constrained('employees');
            $table->enum('scan_type', ['check_in', 'check_out']);
            $table->float('match_score')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('scan_time')->useCurrent();
            $table->timestamps(false);

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_scan_logs');
    }
};
