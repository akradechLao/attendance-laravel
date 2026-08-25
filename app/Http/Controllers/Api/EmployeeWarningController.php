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

        $month = $request->get('month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

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
                'date' => $fl->date,
                'late_minutes' => $fl->late_minutes,
                'status' => $fl->status,
                'note' => $fl->note,
            ]);

        $totalLateMinutes = AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->where('check_in_status', 'late')
            ->sum('late_minutes');

        $earlyCheckoutCount = AttendanceLog::where('emp_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->where('check_out_status', 'early')
            ->count();

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

        if ($lateCount >= 3) {
            $warnings[] = [
                'type' => 'late',
                'severity' => $lateCount >= 5 ? 'high' : 'medium',
                'message' => "สาย {$lateCount} ครั้งในเดือนนี้ กรุณารักษาวินัย",
            ];
        }

        if ($absentCount > 0) {
            $warnings[] = [
                'type' => 'absent',
                'severity' => 'high',
                'message' => "ขาดงาน {$absentCount} ครั้งในเดือนนี้",
            ];
        }

        if ($totalLateMinutes > 180) {
            $warnings[] = [
                'type' => 'total_late',
                'severity' => 'high',
                'message' => "สายรวม {$totalLateMinutes} นาที อาจถูกหักค่าจ้าง",
            ];
        }

        return $warnings;
    }
}
