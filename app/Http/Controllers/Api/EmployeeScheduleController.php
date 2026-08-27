<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShiftResolver;
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

        // Generate date range and resolve shifts using ShiftResolver
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $days = (int) $start->diffInDays($end) + 1;

        $schedules = collect();
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $resolved = ShiftResolver::resolve($employee, $date);

            $schedules->push([
                'date' => $date,
                'shift_code' => $resolved['shift_code'],
                'day_type' => $resolved['day_type'],
                'start_time' => $resolved['start_time'],
                'end_time' => $resolved['end_time'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }
}
