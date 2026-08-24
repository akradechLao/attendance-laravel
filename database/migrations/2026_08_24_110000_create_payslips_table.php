<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('emp_id')->constrained('employees');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('ot_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('meal_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('deduction_late', 12, 2)->default(0);
            $table->decimal('deduction_absent', 12, 2)->default(0);
            $table->decimal('deduction_social_security', 12, 2)->default(0);
            $table->decimal('deduction_tax', 12, 2)->default(0);
            $table->decimal('deduction_other', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['emp_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
