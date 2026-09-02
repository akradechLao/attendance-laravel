<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = ShiftSchedule::with('employee');

        if ($request->month) {
            $query->whereMonth('work_date', substr($request->month, 5, 2));
            $query->whereYear('work_date', substr($request->month, 0, 4));
        }

        $shifts = $query->orderBy('work_date')->get();

        return response()->json(['data' => $shifts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'work_date' => 'required|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'shift_code' => 'nullable|string',
        ]);

        $validated['company_id'] = $this->resolveCompanyId($request);

        $shift = ShiftSchedule::create($validated);

        return response()->json(['data' => $shift]);
    }

    public function destroy($id)
    {
        ShiftSchedule::destroy($id);

        return response()->json(['message' => 'ลบสำเร็จ']);
    }
}
