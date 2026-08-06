<?php
/**
 * Data Migration Script
 * Run: php artisan db:seed --class=SupabaseMigrationSeeder
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SupabaseMigrationSeeder extends Seeder
{
    private string $supabaseUrl = 'https://fuqckantlgtrxywahdxx.supabase.co';
    private string $supabaseKey = 'YOUR_ANON_KEY';

    public function run(): void
    {
        $this->command->info('Starting data migration from Supabase...');

        // Migrate companies
        $this->migrateCompanies();

        // Migrate employees
        $this->migrateEmployees();

        // Migrate attendance logs
        $this->migrateAttendanceLogs();

        // Migrate leave types
        $this->migrateLeaveTypes();

        $this->command->info('Migration complete!');
    }

    private function supabaseQuery(string $table): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->supabaseKey,
            'Authorization' => 'Bearer ' . $this->supabaseKey,
        ])->get("{$this->supabaseUrl}/rest/v1/{$table}", [
            'select' => '*',
        ]);

        return $response->json() ?? [];
    }

    private function migrateCompanies(): void
    {
        $this->command->info('Migrating companies...');
        $companies = $this->supabaseQuery('Company');

        foreach ($companies as $company) {
            DB::table('companies')->updateOrInsert(
                ['id' => $company['id']],
                [
                    'name' => $company['name'],
                    'created_at' => $company['created_at'] ?? now(),
                ]
            );
        }

        $this->command->info("  Migrated " . count($companies) . " companies");
    }

    private function migrateEmployees(): void
    {
        $this->command->info('Migrating employees...');
        
        // Paginate through employees
        $offset = 0;
        $limit = 1000;
        
        do {
            $response = Http::withHeaders([
                'apikey' => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->get("{$this->supabaseUrl}/rest/v1/Employee", [
                'select' => '*',
                'offset' => $offset,
                'limit' => $limit,
            ]);

            $employees = $response->json() ?? [];
            
            foreach ($employees as $emp) {
                DB::table('employees')->updateOrInsert(
                    ['id' => $emp['id']],
                    [
                        'company_id' => $emp['companyId'],
                        'name' => $emp['name'],
                        'employee_code' => $emp['code'] ?? $emp['employeeCode'] ?? null,
                        'group_type' => $emp['group'] ?? 'B',
                        'position' => $emp['position'] ?? 'employee',
                        'level' => $emp['level'] ?? null,
                        'has_ot' => $emp['hasOT'] ?? false,
                        'department' => $emp['department'] ?? null,
                        'division' => $emp['division'] ?? null,
                        'reports_to' => $emp['reportsTo'] ?? null,
                        'pin' => $emp['pin'] ?? null,
                        'supervisor_name' => $emp['supervisorName'] ?? null,
                        'supervisor_line' => $emp['supervisorLine'] ?? null,
                        'supervisor_phone' => $emp['supervisorPhone'] ?? null,
                        'created_at' => $emp['createdAt'] ?? now(),
                    ]
                );
            }

            $offset += $limit;
            $this->command->info("  Processed " . ($offset) . " employees...");
            
        } while (count($employees) === $limit);

        $total = DB::table('employees')->count();
        $this->command->info("  Total employees migrated: {$total}");
    }

    private function migrateAttendanceLogs(): void
    {
        $this->command->info('Migrating attendance logs...');
        
        $offset = 0;
        $limit = 1000;
        
        do {
            $response = Http::withHeaders([
                'apikey' => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->get("{$this->supabaseUrl}/rest/v1/AttendanceLog", [
                'select' => '*',
                'offset' => $offset,
                'limit' => $limit,
            ]);

            $logs = $response->json() ?? [];
            
            foreach ($logs as $log) {
                DB::table('attendance_logs')->updateOrInsert(
                    ['emp_id' => $log['employeeId'], 'date' => $log['date']],
                    [
                        'check_in' => $log['checkIn'] ?? null,
                        'check_in_status' => $log['status'] ?? null,
                        'check_out' => $log['checkOut'] ?? null,
                        'lat_long' => $log['latLong'] ?? null,
                        'scan_type' => 'office_scan',
                        'created_at' => $log['createdAt'] ?? now(),
                    ]
                );
            }

            $offset += $limit;
            
        } while (count($logs) === $limit);

        $total = DB::table('attendance_logs')->count();
        $this->command->info("  Total attendance logs migrated: {$total}");
    }

    private function migrateLeaveTypes(): void
    {
        $this->command->info('Migrating leave types...');
        $types = $this->supabaseQuery('LeaveType');

        foreach ($types as $type) {
            DB::table('leave_types')->updateOrInsert(
                ['id' => $type['id']],
                [
                    'company_id' => $type['companyId'],
                    'name' => $type['name'],
                    'quota_monthly' => $type['quota'] ?? 0,
                    'created_at' => $type['createdAt'] ?? now(),
                ]
            );
        }

        $this->command->info("  Migrated " . count($types) . " leave types");
    }
}
