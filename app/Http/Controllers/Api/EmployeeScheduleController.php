<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ShiftCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $startDate = $request->get('start_date', now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfWeek()->format('Y-m-d'));

        $schedules = ShiftSchedule::where('emp_id', $employee->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get()
            ->map(function ($s) {
                $times = ShiftCodeHelper::getTimes($s->shift_code);
                return [
                    'date' => $s->work_date instanceof \Carbon\Carbon ? $s->work_date->format('Y-m-d') : $s->work_date,
                    'shift_code' => $s->shift_code,
                    'day_type' => $s->day_type,
                    'start_time' => $times['start'],
                    'end_time' => $times['end'],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }
}
