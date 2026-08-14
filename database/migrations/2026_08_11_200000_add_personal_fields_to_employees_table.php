<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('id_card')->nullable()->after('birth_date');
            $table->string('social_security')->nullable()->after('id_card');
            $table->string('education')->nullable()->after('social_security');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['id_card', 'social_security', 'education']);
        });
    }
};
