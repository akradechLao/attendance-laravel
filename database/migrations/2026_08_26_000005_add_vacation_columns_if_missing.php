<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_balances', 'vacation_accumulated')) {
                $table->decimal('vacation_accumulated', 5, 1)->default(0)->after('carried_forward');
            }
            if (!Schema::hasColumn('leave_balances', 'vacation_expiry_date')) {
                $table->date('vacation_expiry_date')->nullable()->after('vacation_accumulated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn(['vacation_accumulated', 'vacation_expiry_date']);
        });
    }
};
