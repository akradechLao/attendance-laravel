<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Company;
use App\Models\WorkShift;
use App\Models\EmployeeApprover;
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

        $knownColumns = ['no','emp_code','employee_code','employee_title_lv','employee_name',
            'employee_last_name','fing_code','employee_gender','Lavel','กะการทำงาน','มี OT',
            'employee_type_code','employee_type_group_code','employee_nickname','mobilephone',
            'emailaddress','birth_dt','effective_dt','department_name','division_name','position_name'];

        $approverColumns = array_filter($header, function($col) use ($knownColumns) {
            return !in_array($col, $knownColumns) && !empty($col) && !preg_match('/^\d+$/', $col);
        });
        $approverColumns = array_values($approverColumns);
        $this->info("Approver columns found: " . implode(', ', $approverColumns));
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
                $birthDate = $this->parseBuddhistDate($record['birth_dt'] ?? '');
                $startDate = $this->parseBuddhistDate($record['effective_dt'] ?? '');

                $shiftRaw = trim($record['กะการทำงาน'] ?? '1');
                $shiftGroups = array_filter(array_map('intval', preg_split('/[\s,]+/', $shiftRaw)));
                $primaryShift = !empty($shiftGroups) ? min($shiftGroups) : 1;

                $employee = Employee::updateOrCreate(
                    ['employee_code' => $employeeCode, 'company_id' => $companyId],
                    [
                        'name' => $name,
                        'nickname' => $nickname,
                        'phone' => $phone,
                        'email' => $email,
                        'birth_date' => $birthDate,
                        'start_date' => $startDate,
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

                foreach ($approverColumns as $approverCol) {
                    $canApprove = strtoupper(trim($record[$approverCol] ?? '')) === 'TRUE';
                    EmployeeApprover::updateOrCreate(
                        ['employee_id' => $employee->id, 'approver_name' => $approverCol],
                        ['can_approve' => $canApprove]
                    );
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

    private function parseBuddhistDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) return null;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $matches)) {
            $year = intval($matches[1]);
            if ($year > 2400) {
                $year -= 543;
            }
            return sprintf('%04d-%02d-%02d', $year, intval($matches[2]), intval($matches[3]));
        }

        return null;
    }
}
