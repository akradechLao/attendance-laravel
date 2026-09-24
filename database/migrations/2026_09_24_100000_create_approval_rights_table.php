<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('can_leave')->default(false);
            $table->boolean('can_ot')->default(false);
            $table->boolean('can_wfh')->default(false);
            $table->boolean('can_shift_swap')->default(false);
            $table->boolean('can_shift_request')->default(false);
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['approver_id', 'employee_id']);
            $table->index(['company_id', 'approver_id']);
            $table->index(['approver_id', 'can_leave']);
            $table->index(['approver_id', 'can_ot']);
            $table->index(['approver_id', 'can_wfh']);
            $table->index(['approver_id', 'can_shift_swap']);
            $table->index(['approver_id', 'can_shift_request']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rights');
    }
};
