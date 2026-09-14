<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_location_shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_location_id')->constrained('office_locations')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained('work_shifts');
            $table->json('days_of_week'); // 0=อาทิตย์ ... 6=เสาร์
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_location_shift_patterns');
    }
};
