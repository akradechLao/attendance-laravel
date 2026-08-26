<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveAttendanceLogs extends Command
{
    protected $signature = 'attendance:archive {--years=2 : Archive records older than this many years} {--dry-run : Show what would be archived without actually moving data}';
    protected $description = 'Archive attendance logs older than specified years to attendance_logs_archive table';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subYears($years)->startOfDay();

        $this->info("Archiving records older than {$cutoffDate->format('Y-m-d')} ({$years} years)...");

        $count = DB::table('attendance_logs')
            ->where('date', '<', $cutoffDate)
            ->count();

        $this->info("Found {$count} records to archive.");

        if ($count === 0) {
            $this->info("Nothing to archive.");
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $sample = DB::table('attendance_logs')
                ->where('date', '<', $cutoffDate)
                ->orderBy('date', 'asc')
                ->limit(5)
                ->get(['emp_id', 'date', 'check_in_status']);

            $this->info("\nSample records that would be archived:");
            $this->table(['emp_id', 'date', 'status'], $sample);
            $this->info("\nRun without --dry-run to execute.");
            return Command::SUCCESS;
        }

        $batchSize = 1000;
        $totalArchived = 0;

        while (true) {
            $batch = DB::table('attendance_logs')
                ->where('date', '<', $cutoffDate)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $records = $batch->map(fn($row) => (array) $row)->toArray();

            DB::table('attendance_logs_archive')->insert($records);

            $ids = $batch->pluck('id')->toArray();
            DB::table('attendance_logs')->whereIn('id', $ids)->delete();

            $totalArchived += count($ids);
            $this->line("  Archived batch: {$totalArchived} / {$count}");
        }

        $this->info("Done! Archived {$totalArchived} records to attendance_logs_archive.");
        return Command::SUCCESS;
    }
}
