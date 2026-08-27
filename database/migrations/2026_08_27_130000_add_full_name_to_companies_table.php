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
            $table->string('full_name_en')->nullable()->after('full_name');
        });

        DB::table('companies')->where('id', 1)->update([
            'full_name' => 'บริษัท นอร์ทเทิร์นไทยคอนซัลติ้ง จำกัด',
            'full_name_en' => 'Northern Thai Consulting Co.,Ltd.',
        ]);
        DB::table('companies')->where('id', 2)->update([
            'full_name' => 'บริษัท อีสเทิร์นไทยคอนซัลติ้ง 1992 จำกัด',
            'full_name_en' => 'Eastern Thai Consulting 1992 Co.,Ltd.',
        ]);
        DB::table('companies')->where('id', 3)->update([
            'full_name' => 'บริษัท เอ็นไวรอนเมนทอลเทคโนโลยีคอนซัลแตนท์ จำกัด',
            'full_name_en' => 'Environmental Technology Consultant Co.,Ltd.',
        ]);
        DB::table('companies')->where('id', 4)->update([
            'full_name' => 'บริษัท เซ้าเทิร์นไทยคอนซัลติ้ง จำกัด',
            'full_name_en' => 'Southern Thai Consulting Co.,Ltd.',
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('full_name_en');
        });
    }
};
