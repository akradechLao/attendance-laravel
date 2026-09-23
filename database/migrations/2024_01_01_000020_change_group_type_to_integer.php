<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'group_type')) {
            Schema::table('employees', fn(Blueprint $t) => $t->integer('group_type')->default(1));
        }
    }

    public function down(): void
    {
        // No-op for local dev
    }
};
