<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetEmployeePassword extends Command
{
    protected $signature = 'employee:set-password {--employee_code= : Employee code} {--company= : Company code} {--password=password : Password to set} {--all : Set default password for all employees without one}';
    protected $description = 'Set password for employee(s)';

    public function handle(): int
    {
        $password = $this->option('password');

        if ($this->option('all')) {
            $employees = Employee::whereNull('password')->get();
            $count = 0;
            foreach ($employees as $employee) {
                $employee->password = $password;
                $employee->save();
                $count++;
            }
            $this->info("Set default password for {$count} employees.");
            return Command::SUCCESS;
        }

        $code = $this->option('employee_code');
        $companyCode = $this->option('company');

        if (!$code) {
            $this->error('Please provide --employee_code or use --all');
            return Command::FAILURE;
        }

        $query = Employee::where('employee_code', $code);
        if ($companyCode) {
            $company = \App\Models\Company::where('code_prefix', $companyCode)->first();
            if ($company) {
                $query->where('company_id', $company->id);
            }
        }

        $employee = $query->first();
        if (!$employee) {
            $this->error("Employee not found: {$code}");
            return Command::FAILURE;
        }

        $employee->password = $password;
        $employee->save();

        $this->info("Password set for {$employee->name} ({$employee->employee_code})");
        return Command::SUCCESS;
    }
}
