<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\LateForcedLeave;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeWarningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $month = $request->get('month', now()->setTimezone('Asia/Bangkok')->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->setTimezone('Asia/Bangkok')->startOfMonth();
        $endOfMonth = Carbon::parse($month)->setTimezone('Asia/Bangkok')->endOfMonth();

        $lateCount = AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->where('check_in_status', 'late')
            ->count();

        $absentCount = AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->where('check_in_status', 'absent')
            ->count();

        $forcedLeaves = LateForcedLeave::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->map(fn($fl) => [
                'date' => Carbon::parse($fl->date)->setTimezone('Asia/Bangkok')->format('Y-m-d'),
                'late_minutes' => (int) ($fl->late_minutes ?? 0),
                'status' => $fl->status,
                'note' => $fl->note,
            ]);

        $totalLateMinutes = (int) AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->where('check_in_status', 'late')
            ->sum('late_minutes');

        // Early checkout: check_out before scheduled end (if we have schedule data)
        $earlyCheckoutCount = 0;

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'total_late_minutes' => $totalLateMinutes,
                'early_checkout_count' => $earlyCheckoutCount,
                'forced_leaves' => $forcedLeaves,
                'summary' => [
                    'late' => $lateCount,
                    'absent' => $absentCount,
                    'early' => $earlyCheckoutCount,
                ],
                'warnings' => $this->buildWarnings($lateCount, $absentCount, $totalLateMinutes),
            ],
        ]);
    }

    private function buildWarnings(int $lateCount, int $absentCount, int $totalLateMinutes): array
    {
        $warnings = [];
        $now = now()->setTimezone('Asia/Bangkok')->format('Y-m-d H:i:s');

        if ($lateCount >= 3) {
            $warnings[] = [
                'id' => 'late',
                'type' => 'late',
                'severity' => $lateCount >= 5 ? 'high' : 'medium',
                'message' => "สาย {$lateCount} ครั้งในเดือนนี้ กรุณารักษาวินัย",
                'created_at' => $now,
            ];
        }

        if ($absentCount > 0) {
            $warnings[] = [
                'id' => 'absent',
                'type' => 'absent',
                'severity' => 'high',
                'message' => "ขาดงาน {$absentCount} ครั้งในเดือนนี้",
                'created_at' => $now,
            ];
        }

        if ($totalLateMinutes > 180) {
            $warnings[] = [
                'id' => 'total_late',
                'type' => 'total_late',
                'severity' => 'high',
                'message' => "สายรวม {$totalLateMinutes} นาที อาจถูกหักค่าจ้าง",
                'created_at' => $now,
            ];
        }

        return $warnings;
    }
}
