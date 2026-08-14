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
            $query->whereMonth('date', substr($request->month, 5, 2));
            $query->whereYear('date', substr($request->month, 0, 4));
        }

        $shifts = $query->orderBy('date')->get();

        return response()->json(['data' => $shifts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'shift_code' => 'required|string',
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
