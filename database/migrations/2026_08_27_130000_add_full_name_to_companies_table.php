<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('name');
        });

        DB::table('companies')->where('id', 1)->update(['full_name' => 'บริษัท เอ็นทีซี จำกัด']);
        DB::table('companies')->where('id', 2)->update(['full_name' => 'บริษัท เอ็นทีซี (เอ็มทีซี) จำกัด']);
        DB::table('companies')->where('id', 3)->update(['full_name' => 'บริษัท อีเทค จำกัด']);
        DB::table('companies')->where('id', 4)->update(['full_name' => 'บริษัท เอสทีซี จำกัด']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
