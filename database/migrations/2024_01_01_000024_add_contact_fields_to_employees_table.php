<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('name');
            $table->string('phone')->nullable()->after('nickname');
            $table->string('email')->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('email');
            $table->date('start_date')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['nickname', 'phone', 'email', 'birth_date', 'start_date']);
        });
    }
};
