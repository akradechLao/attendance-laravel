<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->string('category', 50);        // attendance, ot, leave, wfh, approval, notification
            $table->string('key', 100);              // late_grace_minutes, max_ot_hours_per_day, etc.
            $table->text('value')->nullable();       // stored as text, cast in service
            $table->string('value_type', 20)->default('string'); // integer, boolean, string, json
            $table->text('description')->nullable(); // human-readable explanation
            $table->boolean('is_system')->default(false); // protected from deletion
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_config');
    }
};
