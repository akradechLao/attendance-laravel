<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Company;
use App\Models\WorkShift;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportEmployeesCsv extends Command
{
    protected $signature = 'import:employees {file} {--company=}';
    protected $description = 'Import employees from CSV file';

    private $companyMap = [
        'NTC' => 1,
        'ETC1992' => 2,
        'ETECH' => 3,
        'STC' => 4,
    ];

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("Cannot open file: {$file}");
            return 1;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->error("Cannot read CSV header");
            fclose($handle);
            return 1;
        }

        $header = array_map('trim', $header);
        $this->info("CSV Header: " . implode(' | ', array_slice($header, 0, 10)));
        $this->newLine();

        $total = 0;
        $imported = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $total++;
                $record = array_combine($header, $row);

                $empCode = trim($record['emp_code'] ?? '');
                $employeeCode = trim($record['employee_code'] ?? '');

                if (empty($employeeCode) || empty($empCode)) {
                    $skipped++;
                    continue;
                }

                $companyName = strtoupper($empCode);
                $companyId = $this->companyMap[$companyName] ?? null;

                if (!$companyId) {
                    $this->warn("Row {$total}: Unknown company '{$empCode}', skipping");
                    $skipped++;
                    continue;
                }

                $title = trim($record['employee_title_lv'] ?? '');
                $firstName = trim($record['employee_name'] ?? '');
                $lastName = trim($record['employee_last_name'] ?? '');
                $name = $this->buildName($title, $firstName, $lastName);

                $level = intval($record['Lavel'] ?? 1);
                $hasOt = (trim($record['มี OT'] ?? '') === 'มี');
                $department = trim($record['department_name'] ?? '');
                $division = trim($record['division_name'] ?? '');
                $position = trim($record['position_name'] ?? '');
                $gender = trim($record['employee_gender'] ?? '');
                $nickname = trim($record['employee_nickname'] ?? '');
                $phone = trim($record['mobilephone'] ?? '');
                $email = trim($record['emailaddress'] ?? '');

                $shiftRaw = trim($record['กะการทำงาน'] ?? '1');
                $shiftGroups = array_filter(array_map('intval', preg_split('/[\s,]+/', $shiftRaw)));
                $primaryShift = !empty($shiftGroups) ? min($shiftGroups) : 1;

                $employee = Employee::updateOrCreate(
                    ['employee_code' => $employeeCode, 'company_id' => $companyId],
                    [
                        'name' => $name,
                        'group_type' => $primaryShift,
                        'position' => $position,
                        'level' => $level,
                        'has_ot' => $hasOt,
                        'department' => $department,
                        'division' => $division,
                        'is_active' => true,
                        'password' => Hash::make('password'),
                    ]
                );

                $workShiftIds = WorkShift::whereIn('group_number', $shiftGroups)->pluck('id')->toArray();
                if (!empty($workShiftIds)) {
                    $employee->workShifts()->syncWithoutDetaching($workShiftIds);
                }

                $imported++;
                $shiftStr = implode(',', $shiftGroups);
                $this->line("  [{$imported}] {$employeeCode} - {$name} (Shift: {$shiftStr})");
            }

            fclose($handle);
            DB::commit();
            $this->newLine();
            $this->info("Import completed: {$imported} imported, {$skipped} skipped (total rows: {$total})");
            return 0;

        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    private function buildName(string $title, string $firstName, string $lastName): string
    {
        $cleanTitle = preg_replace('/^\d+-/', '', $title);
        $name = trim($cleanTitle . ' ' . $firstName . ' ' . $lastName);
        return $name;
    }
}
