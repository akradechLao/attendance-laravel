<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('emp_id')->constrained('employees');
            $table->foreignId('company_id')->constrained('companies');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('destination')->nullable()->comment('Destination location');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_assignments');
    }
};
