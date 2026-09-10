<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">แดชบอร์ดวิเคราะห์ข้อมูล</h1>
          <p class="text-gray-500">ภาพรวมแนวโน้มการเข้างาน โครงสร้างพนักงาน และ OT</p>
        </div>
        <div class="flex items-center gap-3">
          <select v-model="selectedCompany" class="input-field w-auto" @change="fetchData">
            <option value="">ทุกบริษัท</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
          <select v-model.number="months" class="input-field w-auto" @change="fetchData">
            <option :value="3">3 เดือนล่าสุด</option>
            <option :value="6">6 เดือนล่าสุด</option>
            <option :value="12">12 เดือนล่าสุด</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-12"><LoadingSpinner /></div>

      <template v-else>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="card text-center py-4">
            <p class="text-xs text-gray-500 mb-1">พนักงานทั้งหมด</p>
            <p class="text-2xl font-bold text-navy">{{ totalEmployees }}</p>
          </div>
          <div class="card text-center py-4">
            <p class="text-xs text-gray-500 mb-1">ตรงเวลา ({{ months }} ด.)</p>
            <p class="text-2xl font-bold text-green-600">{{ sumOnTime }}</p>
          </div>
          <div class="card text-center py-4">
            <p class="text-xs text-gray-500 mb-1">มาสาย ({{ months }} ด.)</p>
            <p class="text-2xl font-bold text-amber-500">{{ sumLate }}</p>
          </div>
          <div class="card text-center py-4">
            <p class="text-xs text-gray-500 mb-1">ชั่วโมง OT รวม</p>
            <p class="text-2xl font-bold text-blue-600">{{ sumOtHours }}</p>
          </div>
        </div>

        <div class="card">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">แนวโน้มการเข้างานรายเดือน</h2>
          <div class="h-64 sm:h-72">
            <canvas ref="attendanceCanvas"></canvas>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="card">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">ชั่วโมง OT รายเดือน</h2>
            <div class="h-56 sm:h-64">
              <canvas ref="otCanvas"></canvas>
            </div>
          </div>

          <div class="card">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">จำนวนพนักงานตามฝ่าย</h2>
            <div v-if="divisionBreakdown.length === 0" class="h-56 sm:h-64 flex items-center justify-center text-sm text-gray-400">
              ไม่มีข้อมูล
            </div>
            <div v-else class="h-56 sm:h-64">
              <canvas ref="divisionCanvas"></canvas>
            </div>
          </div>

          <div class="card">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">ช่วงอายุพนักงาน</h2>
            <div class="h-56 sm:h-64">
              <canvas ref="ageCanvas"></canvas>
            </div>
          </div>

          <div class="card">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">อายุงาน</h2>
            <div class="h-56 sm:h-64">
              <canvas ref="tenureCanvas"></canvas>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Chart, registerables } from 'chart.js'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

Chart.register(...registerables)

// โทนสีสบายตา อิงจากธีมหลักของแอป (navy/blue/gold)
const COLORS = {
  onTime: '#3b82f6',
  late: '#eecd57',
  ot: '#1e3a8a',
  palette: ['#3b82f6', '#d4af37', '#10b981', '#f97316', '#a855f7', '#64748b'],
}

const loading = ref(true)
const selectedCompany = ref('')
const months = ref(6)
const companies = ref([])

const attendanceTrend = ref([])
const otTrend = ref([])
const divisionBreakdown = ref([])
const ageDistribution = ref([])
const tenureDistribution = ref([])
const totalEmployees = ref(0)

const sumOnTime = computed(() => attendanceTrend.value.reduce((s, m) => s + m.on_time, 0))
const sumLate = computed(() => attendanceTrend.value.reduce((s, m) => s + m.late, 0))
const sumOtHours = computed(() => Math.round(otTrend.value.reduce((s, m) => s + m.hours, 0) * 10) / 10)

const attendanceCanvas = ref(null)
const otCanvas = ref(null)
const divisionCanvas = ref(null)
const ageCanvas = ref(null)
const tenureCanvas = ref(null)
const charts = {}

function destroyCharts() {
  Object.values(charts).forEach(c => c?.destroy())
}

function baseOptions(extra = {}) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
    ...extra,
  }
}

function renderCharts() {
  destroyCharts()

  if (attendanceCanvas.value) {
    charts.attendance = new Chart(attendanceCanvas.value, {
      type: 'line',
      data: {
        labels: attendanceTrend.value.map(m => m.month),
        datasets: [
          { label: 'ตรงเวลา', data: attendanceTrend.value.map(m => m.on_time), borderColor: COLORS.onTime, backgroundColor: COLORS.onTime + '22', fill: true, tension: 0.35 },
          { label: 'สาย', data: attendanceTrend.value.map(m => m.late), borderColor: COLORS.late, backgroundColor: COLORS.late + '33', fill: true, tension: 0.35 },
        ],
      },
      options: baseOptions({ scales: { y: { beginAtZero: true } } }),
    })
  }

  if (otCanvas.value) {
    charts.ot = new Chart(otCanvas.value, {
      type: 'bar',
      data: {
        labels: otTrend.value.map(m => m.month),
        datasets: [{ label: 'ชั่วโมง OT', data: otTrend.value.map(m => m.hours), backgroundColor: COLORS.ot, borderRadius: 6, maxBarThickness: 36 }],
      },
      options: baseOptions({ plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }),
    })
  }

  if (divisionCanvas.value && divisionBreakdown.value.length > 0) {
    charts.division = new Chart(divisionCanvas.value, {
      type: 'doughnut',
      data: {
        labels: divisionBreakdown.value.map(d => d.label),
        datasets: [{ data: divisionBreakdown.value.map(d => d.total), backgroundColor: COLORS.palette, borderWidth: 2, borderColor: '#fff' }],
      },
      options: baseOptions({ cutout: '65%' }),
    })
  }

  if (ageCanvas.value) {
    charts.age = new Chart(ageCanvas.value, {
      type: 'bar',
      data: {
        labels: ageDistribution.value.map(a => a.label),
        datasets: [{ label: 'จำนวนคน', data: ageDistribution.value.map(a => a.count), backgroundColor: COLORS.palette[0], borderRadius: 6, maxBarThickness: 40 }],
      },
      options: baseOptions({ plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }),
    })
  }

  if (tenureCanvas.value) {
    charts.tenure = new Chart(tenureCanvas.value, {
      type: 'bar',
      data: {
        labels: tenureDistribution.value.map(t => t.label),
        datasets: [{ label: 'จำนวนคน', data: tenureDistribution.value.map(t => t.count), backgroundColor: COLORS.palette[1], borderRadius: 6, maxBarThickness: 40 }],
      },
      options: baseOptions({ plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }),
    })
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { months: months.value }
    if (selectedCompany.value) params.company_id = selectedCompany.value

    const [analyticsRes, companiesRes] = await Promise.all([
      api.get('/api/dashboard/analytics', { params }),
      companies.value.length ? Promise.resolve(null) : api.get('/api/companies'),
    ])

    const d = analyticsRes.data?.data || {}
    attendanceTrend.value = d.attendance_trend || []
    otTrend.value = d.ot_trend || []
    divisionBreakdown.value = d.division_breakdown || []
    ageDistribution.value = d.age_distribution || []
    tenureDistribution.value = d.tenure_distribution || []
    totalEmployees.value = d.total_employees || 0

    if (companiesRes) companies.value = companiesRes.data?.data || []
  } catch (error) {
    console.error('Dashboard analytics fetch error:', error)
  } finally {
    loading.value = false
  }

  // loading ต้องเป็น false ก่อน ให้ v-else mount canvas ใน DOM แล้วค่อยวาดกราฟ
  await nextTick()
  renderCharts()
}

onMounted(fetchData)
onBeforeUnmount(destroyCharts)
</script>
