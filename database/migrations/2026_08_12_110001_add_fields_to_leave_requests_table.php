<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('total_days', 5, 1)->default(1)->after('reason');
            $table->foreignId('supervisor_id')->nullable()->after('status');
            $table->text('supervisor_note')->nullable()->after('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['total_days', 'supervisor_id', 'supervisor_note']);
        });
    }
};
