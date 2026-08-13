<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveService;

class AccumulateVacation extends Command
{
    protected $signature = "leave:accumulate-vacation";
    protected $description = "Accumulate vacation days (half of previous year annual leave)";

    public function handle(LeaveService $leaveService): int
    {
        $count = $leaveService->accumulateVacation();
        $this->info("Vacation accumulated for {$count} balances.");
        return Command::SUCCESS;
    }
}
