<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ot_requests MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ot_requests MODIFY COLUMN status ENUM('pending','manager_approved','approved','rejected') DEFAULT 'pending'");
    }
};
