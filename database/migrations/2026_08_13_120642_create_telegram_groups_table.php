<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("telegram_groups", function (Blueprint $table) {
            $table->id();
            $table->foreignId("company_id")->constrained("companies");
            $table->string("group_name");
            $table->enum("group_type", ["company", "branch", "department", "supervisor"])->default("company");
            $table->string("telegram_chat_id");
            $table->foreignId("office_location_id")->nullable()->constrained("office_locations");
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("telegram_groups");
    }
};
