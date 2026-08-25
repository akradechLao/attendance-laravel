<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable()->comment('admin or employee');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID of the user who performed the action');
            $table->string('user_name')->nullable()->comment('Name of the user');
            $table->string('action')->comment('create, update, delete, approve, reject');
            $table->string('auditable_type')->comment('Model class name');
            $table->unsignedBigInteger('auditable_id')->comment('Model ID');
            $table->json('old_values')->nullable()->comment('Previous values');
            $table->json('new_values')->nullable()->comment('New values');
            $table->string('description')->nullable()->comment('Human readable description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_type');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
