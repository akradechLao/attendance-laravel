<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $holidays = CompanyHoliday::where('company_id', $request->user()->company_id ?? 1)
            ->whereYear('date', $request->year ?? date('Y'))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $holidays]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
        ]);

        $validated['company_id'] = $request->user()->company_id ?? 1;

        $holiday = CompanyHoliday::create($validated);

        return response()->json(['data' => $holiday]);
    }

    public function destroy($id)
    {
        CompanyHoliday::destroy($id);

        return response()->json(['message' => 'ลบสำเร็จ']);
    }
}
