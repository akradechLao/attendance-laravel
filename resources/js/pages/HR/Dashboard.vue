<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">แดชบอร์ด</h1>
          <p class="text-gray-500">ภาพรวมการเข้างานวันนี้</p>
        </div>
        <div class="flex items-center gap-4">
          <select v-model="selectedCompany" class="input-field w-auto" @change="fetchData">
            <option value="">ทุกบริษัท</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
          <button @click="fetchData" class="btn-secondary flex items-center gap-2">
            <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            รีเฟรช
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-12"><LoadingSpinner /></div>

      <template v-else>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-8 gap-3">
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">พนักงานทั้งหมด</p>
            <p class="text-3xl font-bold text-gray-800">{{ stats.total }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">เข้างานวันนี้</p>
            <p class="text-3xl font-bold text-green-600">{{ stats.present }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">ตรงเวลา</p>
            <p class="text-3xl font-bold text-blue-600">{{ stats.on_time }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">สาย</p>
            <p class="text-3xl font-bold text-yellow-600">{{ stats.late }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">ยังไม่เช็คเอาท์</p>
            <p class="text-3xl font-bold text-orange-600">{{ stats.present - stats.checked_out }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">ไม่เข้างาน</p>
            <p class="text-3xl font-bold text-red-600">{{ stats.absent }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">บังคับลารออนุมัติ</p>
            <p class="text-3xl font-bold text-orange-600">{{ stats.forced_leaves_pending }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">บังคับลาอนุมัติแล้ว</p>
            <p class="text-3xl font-bold text-green-600">{{ stats.forced_leaves_approved }}</p>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-800">อัตราเข้างานวันนี้</h3>
            <span class="text-sm font-bold text-gray-800">{{ attendancePercent }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
            <div class="flex h-full">
              <div class="bg-green-500 h-full transition-all duration-500" :style="{ width: onTimePercent + '%' }"></div>
              <div class="bg-yellow-500 h-full transition-all duration-500" :style="{ width: latePercent + '%' }"></div>
            </div>
          </div>
          <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> ตรงเวลา {{ stats.on_time }}</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500 inline-block"></span> สาย {{ stats.late }}</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span> ไม่เข้างาน {{ stats.absent }}</span>
            <span v-if="stats.ot_hours > 0" class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500 inline-block"></span> OT {{ stats.ot_hours }} ชม.</span>
          </div>
        </div>

        <div v-if="companyStats.length > 0">
          <h2 class="text-lg font-semibold text-gray-800 mb-3">แยกตามบริษัท</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="cs in companyStats" :key="cs.company_id" class="card hover:shadow-lg transition-shadow">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <div class="w-10 h-10 rounded-lg flex items-center justify-center text-gray-800 font-bold text-sm" :style="companyColorStyle(cs.company_name)">
                    {{ cs.company_name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-bold text-gray-800 text-sm">{{ cs.company_name }}</p>
                    <p class="text-xs text-gray-500">{{ cs.total }} คน</p>
                  </div>
                </div>
                <span class="text-xl font-bold" :class="cs.percent >= 80 ? 'text-green-600' : cs.percent >= 50 ? 'text-yellow-600' : 'text-red-600'">{{ cs.percent }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden mb-2">
                <div class="bg-blue-500 h-full transition-all duration-500" :style="{ width: cs.percent + '%' }"></div>
              </div>
              <div class="flex justify-between text-xs text-gray-500">
                <span class="text-green-600">เข้างาน {{ cs.present }}</span>
                <span class="text-yellow-600">สาย {{ cs.late }}</span>
                <span class="text-red-600">ขาด {{ cs.absent }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">รายการเข้างานวันนี้ ({{ records.length }} รายการ)</h2>
          </div>
          <div v-if="records.length === 0" class="text-center py-8 text-gray-500">ยังไม่มีรายการเข้างานวันนี้</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">กะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รอบ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">วันที่/เวลาเข้า</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">วันที่/เวลาออก</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชั่วโมงทำงาน</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ประเภท</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <span class="font-medium text-gray-800 text-sm">{{ record.employee_name }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                   <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded text-xs font-medium text-gray-800" :style="companyColorStyle(record.company_name)">{{ record.company_code }}</span>
                  </td>
                  <td class="px-4 py-3 text-xs text-gray-500">{{ record.shift_time || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.round_no || 1 }}</td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.is_late ? 'text-yellow-600' : 'text-green-600'">
                    <div>{{ record.check_in || '-' }}</div>
                    <div v-if="record.date" class="text-[10px] text-gray-400">{{ formatDateThai(record.date) }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <div>{{ record.check_out || '-' }}</div>
                    <div v-if="record.check_out && record.date" class="text-[10px] text-gray-400">{{ formatDateThai(record.date) }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.work_minutes > 0 ? 'text-blue-600' : 'text-gray-400'">{{ record.work_hours_display }}</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-0.5">
                      <span :class="['px-2 py-1 rounded-full text-xs font-medium inline-block w-fit', record.is_late ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700']">
                        {{ record.is_late ? 'สาย' : 'ปกติ' }}
                      </span>
                      <span v-if="record.late_minutes > 0" class="text-xs text-red-500">{{ record.late_minutes }} นาที</span>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-0.5">
                      <span v-if="record.has_forced_leave" class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 inline-block w-fit">บังคับลากิจ</span>
                      <span v-if="record.scan_type === 'remote_scan'" class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 inline-block w-fit">นอกสถานที่</span>
                      <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 inline-block w-fit">ออฟฟิศ</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(true)
const selectedCompany = ref('')
const companies = ref([])
const records = ref([])
const companyStats = ref([])
const stats = reactive({
  total: 0, present: 0, late: 0, on_time: 0, checked_out: 0, absent: 0,
  forced_leaves_pending: 0, forced_leaves_approved: 0, ot_hours: 0
})

let refreshInterval = null

const attendancePercent = computed(() => stats.total > 0 ? Math.round((stats.present / stats.total) * 100) : 0)
const onTimePercent = computed(() => stats.total > 0 ? Math.round((stats.on_time / stats.total) * 100) : 0)
const latePercent = computed(() => stats.total > 0 ? Math.round((stats.late / stats.total) * 100) : 0)

const companyColors = {
  ETC: 'background: linear-gradient(135deg, #10b981, #047857)',
  STC: 'background: linear-gradient(135deg, #a855f7, #7e22ce)',
  ETECH: 'background: linear-gradient(135deg, #f97316, #c2410c)',
  NTC: 'background: linear-gradient(135deg, #3b82f6, #1d4ed8)',
}

function companyColorStyle(name) {
  return companyColors[name] || 'background: linear-gradient(135deg, #64748b, #334155)'
}

function formatDateTime(val) {
  if (!val) return '-'
  try {
    const d = new Date(val)
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const h = String(d.getHours()).padStart(2, '0')
    const min = String(d.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${day} ${h}:${min}`
  } catch {
    return val
  }
}

const thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']

function formatDateThai(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const day = d.getDate()
  const month = thMonths[d.getMonth()]
  const year = d.getFullYear() + 543
  return `${day} ${month} ${year}`
}

async function fetchData() {
  loading.value = true
  try {
    const params = selectedCompany.value ? { company_id: selectedCompany.value } : {}
    const [statsRes, todayRes, companiesRes] = await Promise.all([
      api.get('/api/dashboard/stats', { params }),
      api.get('/api/dashboard/today', { params }),
      api.get('/api/companies')
    ])

    const sd = statsRes.data?.data || {}
    Object.assign(stats, {
      total: sd.total_employees || 0,
      present: sd.today?.present || 0,
      late: sd.today?.late || 0,
      on_time: sd.today?.on_time || 0,
      checked_out: sd.today?.checked_out || 0,
      absent: sd.today?.absent || 0,
      forced_leaves_pending: sd.today?.forced_leaves_pending || 0,
      forced_leaves_approved: sd.today?.forced_leaves_approved || 0,
      ot_hours: sd.monthly?.ot_hours || 0
    })
    companyStats.value = sd.companies || []
    records.value = todayRes.data?.data?.records || []
    companies.value = companiesRes.data?.data || []
  } catch (error) {
    console.error('Dashboard fetch error:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
  refreshInterval = setInterval(fetchData, 30000)
})
onUnmounted(() => { if (refreshInterval) clearInterval(refreshInterval) })
</script>
