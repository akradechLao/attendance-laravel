<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['id' => 1, 'name' => 'NTC', 'code_prefix' => 'NTC', 'telegram_bot_token' => null, 'telegram_chat_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'ETC1992', 'code_prefix' => 'ETC', 'telegram_bot_token' => null, 'telegram_chat_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'ETECH', 'code_prefix' => 'ETE', 'telegram_bot_token' => null, 'telegram_chat_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'STC', 'code_prefix' => 'STC', 'telegram_bot_token' => null, 'telegram_chat_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($companies as $company) {
            DB::table('companies')->updateOrInsert(
                ['id' => $company['id']],
                $company
            );
        }
    }
}
