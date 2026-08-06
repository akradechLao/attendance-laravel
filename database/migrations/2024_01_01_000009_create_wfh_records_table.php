<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id')->constrained('employees');
            $table->date('date');
            $table->string('reason')->nullable();
            $table->enum('status', ['approved', 'rejected', 'pending'])->default('pending');
            $table->timestamps();

            $table->unique(['emp_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_records');
    }
};
