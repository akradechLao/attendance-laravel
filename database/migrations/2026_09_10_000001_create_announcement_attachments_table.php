<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_attachments');
    }
};
