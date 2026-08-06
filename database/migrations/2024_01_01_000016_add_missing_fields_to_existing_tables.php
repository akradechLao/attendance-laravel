<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1.1 companies - เพิ่ม telegram fields
        Schema::table('companies', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('name');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_token');
        });

        // 1.2 employees - เพิ่ม wfh_quota, preferred_off_day
        Schema::table('employees', function (Blueprint $table) {
            $table->integer('wfh_quota')->default(1)->after('has_ot');
            $table->string('preferred_off_day')->nullable()->after('wfh_quota');
        });

        // 1.3 attendance_logs - เพิ่ม photo fields
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->text('check_in_photo')->nullable()->after('check_out');
            $table->text('check_out_photo')->nullable()->after('check_in_photo');
        });

        // 1.4 leave_types - เพิ่ม quota fields
        Schema::table('leave_types', function (Blueprint $table) {
            $table->integer('advance_days')->default(0)->after('name');
            $table->integer('quota_daily')->default(0)->after('advance_days');
            $table->integer('quota_contract')->default(0)->after('quota_daily');
            $table->boolean('is_active')->default(true)->after('quota_contract');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_chat_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['wfh_quota', 'preferred_off_day']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['check_in_photo', 'check_out_photo']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['advance_days', 'quota_daily', 'quota_contract', 'is_active']);
        });
    }
};
