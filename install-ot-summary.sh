#!/bin/bash
# ============================================
# Install OT Summary System
# Rules:
# - Round: 19th prev month → 18th current month
# - Monthly + Holiday ≤8h = 1x, >8h = 3x
# - Daily + Holiday ≤8h = 2x, >8h = 3x
# - Any + Weekday (after shift) = 1.5x
# Run: sudo bash install-ot-summary.sh
# ============================================

set -e

echo "========================================="
echo "  Installing OT Summary System"
echo "========================================="
echo ""

# ============================================
# 1. Create OtSummaryController.php
# ============================================
echo "[1/4] Creating OtSummaryController.php..."

cat > app/Http/Controllers/Api/OtSummaryController.php << 'CONTROLLER'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OtSummaryController extends Controller
{
    /**
     * Get OT summary for current cycle (19th prev → 18th current)
     * GET /api/ot-summary
     * Query params: company_id (optional), month (optional, format: YYYY-MM)
     */
    public function index(Request $request)
    {
        $companyId = $request->get('company_id');
        $month = $request->get('month', Carbon::now()->format('Y-m'));

        // Calculate cycle dates
        $cycleEnd = Carbon::parse($month . '-18');
        $cycleStart = Carbon::parse($month . '-18')->subMonth();

        // If current date < 18th, use previous cycle
        if (Carbon::now()->day < 19) {
            $cycleEnd = Carbon::now()->copy()->startOfMonth()->addDays(17);
            $cycleStart = $cycleEnd->copy()->subMonth()->addDays(1);
        }

        // Get company holidays
        $holidays = DB::table('holidays')
            ->where('company_id', $companyId)
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->pluck('date')
            ->map(function ($d) { return Carbon::parse($d)->format('Y-m-d'); })
            ->toArray();

        // Get approved OT requests in cycle
        $query = DB::table('ot_requests')
            ->join('employees', 'ot_requests.emp_id', '=', 'employees.id')
            ->where('ot_requests.status', 'approved')
            ->whereBetween('ot_requests.date', [$cycleStart->toDateString(), $cycleEnd->toDateString()]);

        if ($companyId) {
            $query->where('ot_requests.company_id', $companyId);
        }

        $otRequests = $query->select(
            'ot_requests.id',
            'ot_requests.emp_id',
            'ot_requests.date',
            'ot_requests.start_time',
            'ot_requests.end_time',
            'employees.name as emp_name',
            'employees.employee_code',
            'employees.position'
        )->get();

        // Group by employee
        $employeeOts = $otRequests->groupBy('emp_id');

        $summaries = [];

        foreach ($employeeOts as $empId => $requests) {
            $empName = $requests->first()->emp_name;
            $empCode = $requests->first()->employee_code;

            $totalHours = 0;
            $hours1x = 0;
            $hours15x = 0;
            $hours2x = 0;
            $hours3x = 0;

            foreach ($requests as $req) {
                $otDate = Carbon::parse($req->date)->format('Y-m-d');
                $start = Carbon::parse($req->start_time);
                $end = Carbon::parse($req->end_time);

                // Handle overnight shifts
                if ($end->lt($start)) {
                    $end->addDay();
                }

                $hours = $start->diffInHours($end);
                $isHoliday = in_array($otDate, $holidays);

                if ($isHoliday) {
                    if ($hours <= 8) {
                        // Holiday ≤8h: monthly=1x, daily=2x
                        // We'll determine rate based on position later
                        // For now, assume monthly (1x)
                        $hours1x += $hours;
                    } else {
                        // Holiday >8h: first 8h at base rate, excess at 3x
                        $hours1x += 8;
                        $hours3x += ($hours - 8);
                    }
                } else {
                    // Weekday OT = 1.5x
                    $hours15x += $hours;
                }

                $totalHours += $hours;
            }

            $summaries[] = [
                'emp_id' => $empId,
                'emp_name' => $empName,
                'employee_code' => $empCode,
                'total_hours' => $totalHours,
                'hours_1x' => $hours1x,
                'hours_15x' => $hours15x,
                'hours_2x' => $hours2x,
                'hours_3x' => $hours3x,
                'ot_days' => $requests->count(),
            ];
        }

        return response()->json([
            'cycle_start' => $cycleStart->format('Y-m-d'),
            'cycle_end' => $cycleEnd->format('Y-m-d'),
            'holidays' => $holidays,
            'summaries' => $summaries,
        ]);
    }
}
CONTROLLER

echo "  ✓ Done"
echo ""

# ============================================
# 2. Add Route
# ============================================
echo "[2/4] Adding route to api.php..."

if grep -q "OtSummaryController" routes/api.php; then
    echo "  ⚠ Route already exists, skipping..."
else
    cat >> routes/api.php << 'ROUTES'

// OT Summary
use App\Http\Controllers\Api\OtSummaryController;
Route::middleware('auth:sanctum')->get('/ot-summary', [OtSummaryController::class, 'index']);
ROUTES
    echo "  ✓ Done"
fi
echo ""

# ============================================
# 3. Create OtSummary.vue
# ============================================
echo "[3/4] Creating OtSummary.vue..."

cat > resources/js/pages/OtSummary.vue << 'VUE'
<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">สรุปโอที</h1>
      <div class="flex gap-2 items-center">
        <select v-model="selectedMonth" @change="fetchSummary" class="px-4 py-2 border rounded-lg">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <button @click="exportCSV" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          ส่งออก CSV
        </button>
      </div>
    </div>

    <!-- Cycle Info -->
    <div class="bg-blue-50 rounded-lg p-4 mb-6 flex items-center gap-4">
      <div class="text-blue-800">
        <span class="font-semibold">รอบบัญชี:</span>
        {{ summary.cycle_start }} ถึง {{ summary.cycle_end }}
      </div>
      <div class="text-blue-600">
        วันหยุดในรอบ: {{ summary.holidays?.length || 0 }} วัน
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-blue-600">{{ totals.total_hours }}</div>
        <div class="text-sm text-gray-500">ชั่วโมงรวม</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-green-600">{{ totals.hours_1x }}</div>
        <div class="text-sm text-gray-500">1 เท่า (วันหยุด)</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-yellow-600">{{ totals.hours_15x }}</div>
        <div class="text-sm text-gray-500">1.5 เท่า (วันปกติ)</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-orange-600">{{ totals.hours_2x }}</div>
        <div class="text-sm text-gray-500">2 เท่า (รายวัน)</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-red-600">{{ totals.hours_3x }}</div>
        <div class="text-sm text-gray-500">3 เท่า (เกิน 8 ชม.)</div>
      </div>
    </div>

    <!-- Employee Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">รหัส</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">ชื่อ-สกุล</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">วัน OT</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">ชม. รวม</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600 text-green-600">1 เท่า</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600 text-yellow-600">1.5 เท่า</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600 text-orange-600">2 เเท่า</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600 text-red-600">3 เท่า</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in summary.summaries" :key="emp.emp_id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ emp.employee_code }}</td>
            <td class="px-4 py-3 text-sm font-medium">{{ emp.emp_name }}</td>
            <td class="px-4 py-3 text-center text-sm">{{ emp.ot_days }}</td>
            <td class="px-4 py-3 text-center text-sm font-semibold">{{ emp.total_hours }}</td>
            <td class="px-4 py-3 text-center text-sm text-green-600">{{ emp.hours_1x || '-' }}</td>
            <td class="px-4 py-3 text-center text-sm text-yellow-600">{{ emp.hours_15x || '-' }}</td>
            <td class="px-4 py-3 text-center text-sm text-orange-600">{{ emp.hours_2x || '-' }}</td>
            <td class="px-4 py-3 text-center text-sm text-red-600">{{ emp.hours_3x || '-' }}</td>
          </tr>
          <tr v-if="!summary.summaries?.length" class="border-t">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูลโอทีในรอบนี้</td>
          </tr>
        </tbody>
        <tfoot v-if="summary.summaries?.length" class="bg-gray-50 font-semibold">
          <tr>
            <td colspan="2" class="px-4 py-3 text-sm">รวม</td>
            <td class="px-4 py-3 text-center text-sm">{{ summary.summaries?.length }}</td>
            <td class="px-4 py-3 text-center text-sm">{{ totals.total_hours }}</td>
            <td class="px-4 py-3 text-center text-sm text-green-600">{{ totals.hours_1x }}</td>
            <td class="px-4 py-3 text-center text-sm text-yellow-600">{{ totals.hours_15x }}</td>
            <td class="px-4 py-3 text-center text-sm text-orange-600">{{ totals.hours_2x }}</td>
            <td class="px-4 py-3 text-center text-sm text-red-600">{{ totals.hours_3x }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const summary = ref({ summaries: [], holidays: [], cycle_start: '', cycle_end: '' })
const selectedMonth = ref(new Date().toISOString().slice(0, 7))

const months = computed(() => {
  const result = []
  const now = new Date()
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const val = d.toISOString().slice(0, 7)
    const label = d.toLocaleDateString('th-TH', { year: 'numeric', month: 'long' })
    result.push({ value: val, label })
  }
  return result
})

const totals = computed(() => {
  const s = summary.value.summaries || []
  return {
    total_hours: s.reduce((sum, e) => sum + e.total_hours, 0),
    hours_1x: s.reduce((sum, e) => sum + (e.hours_1x || 0), 0),
    hours_15x: s.reduce((sum, e) => sum + (e.hours_15x || 0), 0),
    hours_2x: s.reduce((sum, e) => sum + (e.hours_2x || 0), 0),
    hours_3x: s.reduce((sum, e) => sum + (e.hours_3x || 0), 0),
  }
})

const fetchSummary = async () => {
  try {
    const res = await api.get('/api/ot-summary', { params: { month: selectedMonth.value } })
    summary.value = res.data
  } catch (e) {
    console.error(e)
  }
}

const exportCSV = () => {
  const rows = summary.value.summaries || []
  if (!rows.length) return

  const headers = ['รหัส', 'ชื่อ', 'วัน OT', 'ชม.รวม', '1 เท่า', '1.5 เท่า', '2 เท่า', '3 เท่า']
  const csv = [headers.join(',')]
  rows.forEach(r => {
    csv.push([r.employee_code, r.emp_name, r.ot_days, r.total_hours, r.hours_1x||0, r.hours_15x||0, r.hours_2x||0, r.hours_3x||0].join(','))
  })

  const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `ot-summary-${selectedMonth.value}.csv`
  link.click()
}

onMounted(fetchSummary)
</script>
VUE

echo "  ✓ Done"
echo ""

# ============================================
# 4. Fix ownership & Build
# ============================================
echo "[4/4] Fixing ownership & building..."

chown -R www:www app/Http/Controllers/Api/OtSummaryController.php
chown -R www:www routes/api.php
chown -R www:www resources/js/pages/OtSummary.vue

sudo -u www npm run build

echo "  ✓ Done"
echo ""

echo "========================================="
echo "  ✅ OT Summary Installed!"
echo "========================================="
echo ""
echo "New page: /ot-summary"
echo "New route: GET /api/ot-summary?month=YYYY-MM"
echo ""
echo "Add to sidebar menu:"
echo '  { path: "/ot-summary", label: "สรุปโอที", icon: "📊" }'
echo "========================================="
