<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('approver_name');
            $table->boolean('can_approve')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'approver_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_approvers');
    }
};
