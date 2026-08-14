<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->integer('max_days_per_year')->default(0)->after('quota_monthly');
            $table->boolean('accrual')->default(false)->after('max_days_per_year');
            $table->boolean('carry_forward')->default(false)->after('accrual');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['code', 'max_days_per_year', 'accrual', 'carry_forward']);
        });
    }
};
