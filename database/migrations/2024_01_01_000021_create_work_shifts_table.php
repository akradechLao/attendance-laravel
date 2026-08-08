<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->integer('group_number')->unique()->comment('0-9');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('work_hours')->comment('ชั่วโมงทำงานต่อวัน');
            $table->boolean('is_overnight')->default(false)->comment('ข้ามวันหรือไม่');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
