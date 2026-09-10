<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `position` stays free-text (the employee's real Thai job title, HR-entered,
     * display only). `position_level` is the coded value (chairman/md/.../employee)
     * that PositionConstants hierarchy logic must key off instead - HR text like
     * "กรรมการผู้จัดการ" never matched the coded strings, so hierarchy logic
     * silently fell back to the lowest level for almost every senior employee.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position_level')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position_level');
        });
    }
};
