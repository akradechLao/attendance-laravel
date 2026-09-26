<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wfh_records', function (Blueprint $table) {
            $table->enum('status', ['approved', 'rejected', 'pending', 'cancel_requested', 'change_requested'])->default('pending')->change();
            $table->date('requested_date')->nullable()->after('approved_date');
            $table->string('requested_reason', 500)->nullable()->after('requested_date');
            $table->string('cancel_reason', 500)->nullable()->after('requested_reason');
            $table->timestamp('requested_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('wfh_records', function (Blueprint $table) {
            $table->dropColumn(['requested_date', 'requested_reason', 'cancel_reason', 'requested_at']);
            $table->enum('status', ['approved', 'rejected', 'pending'])->default('pending')->change();
        });
    }
};
