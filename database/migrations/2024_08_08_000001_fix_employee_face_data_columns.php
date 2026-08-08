<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_face_data MODIFY face_encoding TEXT NOT NULL");
        DB::statement("ALTER TABLE employee_face_data MODIFY angle VARCHAR(20) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_face_data MODIFY face_encoding BINARY NOT NULL");
        DB::statement("ALTER TABLE employee_face_data MODIFY angle ENUM('front','left_45','right_45','up','down') NOT NULL");
    }
};
