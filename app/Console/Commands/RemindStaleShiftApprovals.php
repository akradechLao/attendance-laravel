<?php

namespace App\Console\Commands;

use App\Models\EmployeeNotification;
use App\Models\ShiftRequest;
use App\Models\ShiftSwap;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindStaleShiftApprovals extends Command
{
    protected $signature = 'approval:remind-stale {--hours=24 : Hours before a pending item is considered stale}';
    protected $description = 'Re-notify supervisors about pending shift_swap / shift_request older than N hours';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = Carbon::now('Asia/Bangkok')->subHours($hours);

        $this->info("Reminding stale shift approvals (pending > {$hours}h, cutoff {$cutoff})...");

        $count = 0;
        $count += $this->remindSwaps($cutoff, $hours);
        $count += $this->remindRequests($cutoff, $hours);

        $this->info("Sent {$count} reminder notification(s).");
        return Command::SUCCESS;
    }

    private function remindSwaps(Carbon $cutoff, int $hours): int
    {
        $swaps = ShiftSwap::with('requester:id,employee_code,name')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $sent = 0;
        foreach ($swaps as $swap) {
            $requester = $swap->requester;
            if (!$requester) {
                continue;
            }
            $approverIds = method_exists($requester, 'getApproverIdsToNotify')
                ? $requester->getApproverIdsToNotify('shift_swap')
                : $requester->getSupervisorIds();
            if (empty($approverIds)) {
                continue;
            }

            // Skip if we already reminded for this swap in the last N hours
            $already = EmployeeNotification::where('type', 'shift_swap_reminder')
                ->where('related_id', $swap->id)
                ->where('related_type', 'ShiftSwap')
                ->where('created_at', '>=', $cutoff)
                ->exists();
            if ($already) {
                continue;
            }

            EmployeeNotification::notifyMultiple(
                $approverIds,
                'shift_swap_reminder',
                '⚠️ คำขอสลับเวรค้างนานเกิน ' . $hours . ' ชม.',
                "{$requester->name} ({$requester->employee_code}) ขอสลับเวรวันที่ {$swap->swap_date} ยังรออนุมัติ",
                $swap->id,
                'ShiftSwap'
            );
            $sent++;
        }

        return $sent;
    }

    private function remindRequests(Carbon $cutoff, int $hours): int
    {
        $requests = ShiftRequest::with('employee:id,employee_code,name')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $sent = 0;
        foreach ($requests as $req) {
            $employee = $req->employee;
            if (!$employee) {
                continue;
            }
            $approverIds = method_exists($employee, 'getApproverIdsToNotify')
                ? $employee->getApproverIdsToNotify('shift_request')
                : $employee->getSupervisorIds();
            if (empty($approverIds)) {
                continue;
            }

            $already = EmployeeNotification::where('type', 'shift_request_reminder')
                ->where('related_id', $req->id)
                ->where('related_type', 'ShiftRequest')
                ->where('created_at', '>=', $cutoff)
                ->exists();
            if ($already) {
                continue;
            }

            EmployeeNotification::notifyMultiple(
                $approverIds,
                'shift_request_reminder',
                '⚠️ คำขอเข้ากะค้างนานเกิน ' . $hours . ' ชม.',
                "{$employee->name} ({$employee->employee_code}) ขอ{$req->request_type} กะวันที่ {$req->start_date} ยังรออนุมัติ",
                $req->id,
                'ShiftRequest'
            );
            $sent++;
        }

        return $sent;
    }
}
