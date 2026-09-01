<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            ['username' => 'superadmin', 'company_id' => null, 'role' => 'super_admin'],
            ['username' => 'admin', 'company_id' => 2, 'role' => 'admin'],
            ['username' => 'admin_ntc', 'company_id' => 1, 'role' => 'admin'],
            ['username' => 'admin_etc1992', 'company_id' => 2, 'role' => 'admin'],
            ['username' => 'admin_etech', 'company_id' => 3, 'role' => 'admin'],
            ['username' => 'admin_stc', 'company_id' => 4, 'role' => 'admin'],
        ];

        foreach ($admins as $admin) {
            DB::table('admin_users')->updateOrInsert(
                ['username' => $admin['username']],
                [
                    'company_id' => $admin['company_id'],
                    'password' => Hash::make('1234'),
                    'role' => $admin['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
