<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Setting up employee positions and reporting structure...');

        // Position hierarchy (lower = higher rank):
        // chairman=0, md=1, executive_director=1, assistant_md=2,
        // division_manager=3, sub_division_manager=4, team_lead=5, employee=6

        // NTC employees (company_id=1)
        // Hierarchy: 003(md) → 001(div_manager) → 004/005(team_lead) → 007/008/009(employee)
        $ntcUpdates = [
            ['code' => '003',  'position' => 'md',                'reports_to' => null],
            ['code' => '001',  'position' => 'division_manager',  'reports_to' => null], // reports_to set after
            ['code' => '004',  'position' => 'team_lead',         'reports_to' => null],
            ['code' => '005',  'position' => 'team_lead',         'reports_to' => null],
            ['code' => '007',  'position' => 'employee',          'reports_to' => null],
            ['code' => '008',  'position' => 'employee',          'reports_to' => null],
            ['code' => '009',  'position' => 'employee',          'reports_to' => null],
        ];

        // ETECH employees (company_id=3)
        // Hierarchy: H0003(division_manager) → H0004(sub_div_manager) → 0006/0016/0021/0029(employee)
        $etechUpdates = [
            ['code' => 'H0003', 'position' => 'division_manager',   'reports_to' => null],
            ['code' => 'H0004', 'position' => 'sub_division_manager', 'reports_to' => null],
            ['code' => '0006',  'position' => 'employee',           'reports_to' => null],
            ['code' => '0016',  'position' => 'employee',           'reports_to' => null],
            ['code' => '0021',  'position' => 'employee',           'reports_to' => null],
            ['code' => '0029',  'position' => 'employee',           'reports_to' => null],
        ];

        // Step 1: Set positions only (reports_to stays null for now)
        $allUpdates = array_merge($ntcUpdates, $etechUpdates);
        foreach ($allUpdates as $emp) {
            DB::table('employees')
                ->where('employee_code', $emp['code'])
                ->update(['position' => $emp['position'], 'updated_at' => now()]);
        }

        // Step 2: Set reports_to using subqueries (find by employee_code)
        // NTC: 001 → reports to 003 (md)
        $this->setReportsTo('001', '003');
        // NTC: 004, 005 → reports to 001 (division_manager)
        $this->setReportsTo('004', '001');
        $this->setReportsTo('005', '001');
        // NTC: 007, 008 → reports to 004 (team_lead)
        $this->setReportsTo('007', '004');
        $this->setReportsTo('008', '004');
        // NTC: 009 → reports to 005 (team_lead)
        $this->setReportsTo('009', '005');

        // ETECH: H0004 → reports to H0003 (division_manager)
        $this->setReportsTo('H0004', 'H0003');
        // ETECH: 0006, 0016 → reports to H0004 (sub_div_manager)
        $this->setReportsTo('0006', 'H0004');
        $this->setReportsTo('0016', 'H0004');
        // ETECH: 0021, 0029 → reports to H0004
        $this->setReportsTo('0021', 'H0004');
        $this->setReportsTo('0029', 'H0004');

        // Set PIN for all employees (simple 4-digit PIN based on employee_code)
        $pins = [
            '001' => '1234', '003' => '1234', '004' => '1234', '005' => '1234',
            '007' => '1234', '008' => '1234', '009' => '1234',
            'H0003' => '1234', 'H0004' => '1234', '0006' => '1234',
            '0016' => '1234', '0021' => '1234', '0029' => '1234',
        ];
        foreach ($pins as $code => $pin) {
            DB::table('employees')
                ->where('employee_code', $code)
                ->update(['pin' => $pin, 'updated_at' => now()]);
        }

        $this->command->info('Employee positions, reports_to, and PINs updated.');
    }

    private function setReportsTo(string $childCode, string $parentCode): void
    {
        DB::table('employees')
            ->where('employee_code', $childCode)
            ->update([
                'reports_to' => DB::raw("(SELECT id FROM employees WHERE employee_code = '{$parentCode}' LIMIT 1)"),
                'updated_at' => now(),
            ]);
    }
}
