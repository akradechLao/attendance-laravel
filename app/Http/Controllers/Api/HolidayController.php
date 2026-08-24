<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $holidays = CompanyHoliday::where('company_id', $this->resolveCompanyId($request))
            ->whereYear('date', $request->year ?? date('Y'))
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'date' => $h->date,
                'name' => $h->name,
                'type' => $h->type ?? 'company',
                'year' => $h->year,
            ]);

        return response()->json(['data' => $holidays]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'type' => 'nullable|string|in:government,company,special',
        ]);

        $validated['company_id'] = $this->resolveCompanyId($request);
        $validated['year'] = date('Y', strtotime($validated['date']));
        $validated['type'] = $validated['type'] ?? 'company';

        $holiday = CompanyHoliday::create($validated);

        return response()->json(['data' => $holiday]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'type' => 'nullable|string|in:government,company,special',
        ]);

        $holiday = CompanyHoliday::where('company_id', $this->resolveCompanyId($request))
            ->findOrFail($id);

        $holiday->update([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'type' => $validated['type'] ?? $holiday->type,
            'year' => date('Y', strtotime($validated['date'])),
        ]);

        return response()->json(['data' => $holiday]);
    }

    public function importOfficial(Request $request): JsonResponse
    {
        $year = (int) ($request->year ?? date('Y'));

        try {
            $response = Http::timeout(15)
                ->get('https://thailandformats.com/api/v1/holidays/' . $year);
        } catch (\Exception $e) {
            return response()->json(['message' => 'ไม่สามารถติดต่อแหล่งข้อมูลวันหยุดราชการได้: ' . $e->getMessage()], 502);
        }

        if ($response->failed()) {
            return response()->json(['message' => 'แหล่งข้อมูลวันหยุดราชการตอบกลับสถานะ ' . $response->status()], 502);
        }

        $payload = $response->json();
        $holidays = $payload['holidays'] ?? [];

        if (empty($holidays)) {
            return response()->json(['message' => 'ไม่พบข้อมูลวันหยุดราชการปี ' . $year]);
        }

        $companyId = $this->resolveCompanyId($request);
        $imported = 0;

        foreach ($holidays as $item) {
            $date = $item['start_date'] ?? null;
            if (!$date || substr($date, 0, 4) !== (string) $year) {
                continue;
            }

            CompanyHoliday::updateOrCreate(
                ['company_id' => $companyId, 'date' => $date],
                [
                    'name' => $item['title'] ?? 'วันหยุดราชการ',
                    'type' => 'government',
                    'year' => $year,
                ]
            );
            $imported++;
        }

        return response()->json([
            'message' => 'นำเข้าวันหยุดราชการปี ' . $year . ' เรียบร้อย (' . $imported . ' รายการ)',
            'imported' => $imported,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        CompanyHoliday::destroy($id);

        return response()->json(['message' => 'ลบสำเร็จ']);
    }
}
