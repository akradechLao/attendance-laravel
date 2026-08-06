<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('emp_id')->constrained('employees');
            $table->date('work_date');
            $table->string('shift_code')->default('WC0002');
            $table->enum('day_type', ['working', 'holiday', 'day_off'])->default('working');
            $table->timestamps();

            $table->unique(['emp_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
