<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->string('employee_code')->nullable()->unique();
            $table->enum('group_type', ['A', 'B'])->default('B');
            $table->string('position')->default('employee');
            $table->integer('level')->nullable();
            $table->boolean('has_ot')->default(false);
            $table->string('department')->nullable();
            $table->string('division')->nullable();
            $table->foreignId('reports_to')->nullable()->constrained('employees');
            $table->string('pin')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_line')->nullable();
            $table->string('supervisor_phone')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('employee_code');
            $table->index('reports_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
