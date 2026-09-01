<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin - keep existing password
        DB::table('admin_users')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'company_id' => null,
                'password' => Hash::make('YourStr0ngP@ss!'),
                'role' => 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Company admins - password: 1234
        $admins = [
            ['username' => 'admin', 'company_id' => 2],
            ['username' => 'admin_ntc', 'company_id' => 1],
            ['username' => 'admin_etc1992', 'company_id' => 2],
            ['username' => 'admin_etech', 'company_id' => 3],
            ['username' => 'admin_stc', 'company_id' => 4],
        ];

        foreach ($admins as $admin) {
            DB::table('admin_users')->updateOrInsert(
                ['username' => $admin['username']],
                [
                    'company_id' => $admin['company_id'],
                    'password' => Hash::make('1234'),
                    'role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
