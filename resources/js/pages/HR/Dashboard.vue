<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">แดชบอร์ด</h1>
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
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">พนักงานทั้งหมด</p>
            <p class="text-3xl font-bold text-navy">{{ stats.total }}</p>
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
        </div>

        <div class="card">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-navy">อัตราเข้างานวันนี้</h3>
            <span class="text-sm font-bold text-navy">{{ attendancePercent }}%</span>
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
          </div>
        </div>

        <div v-if="companyStats.length > 0">
          <h2 class="text-lg font-semibold text-navy mb-3">แยกตามบริษัท</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="cs in companyStats" :key="cs.company_id" class="card hover:shadow-lg transition-shadow">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm" :style="companyColorStyle(cs.company_name)">
                    {{ cs.company_name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-bold text-navy text-sm">{{ cs.company_name }}</p>
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
            <h2 class="text-lg font-semibold text-navy">รายการเข้างานวันนี้ ({{ records.length }} รายการ)</h2>
          </div>
          <div v-if="records.length === 0" class="text-center py-8 text-gray-500">ยังไม่มีรายการเข้างานวันนี้</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาเข้า</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาออก</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ประเภท</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                        <span class="text-blue-600 text-xs font-semibold">{{ record.employee_name?.charAt(0) }}</span>
                      </div>
                      <span class="font-medium text-navy text-sm">{{ record.employee_name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded text-xs font-medium text-white" :style="companyColorStyle(record.company_name)">{{ record.company_code }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.is_late ? 'text-yellow-600' : 'text-green-600'">{{ record.check_in || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.check_out || '-' }}</td>
                  <td class="px-4 py-3">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', record.is_late ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700']">
                      {{ record.is_late ? 'สาย' : 'ปกติ' }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="record.scan_type === 'remote_scan'" class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">นอกสถานที่</span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">ออฟฟิศ</span>
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
  total: 0, present: 0, late: 0, on_time: 0, checked_out: 0, absent: 0
})

let refreshInterval = null

const attendancePercent = computed(() => stats.total > 0 ? Math.round((stats.present / stats.total) * 100) : 0)
const onTimePercent = computed(() => stats.total > 0 ? Math.round((stats.on_time / stats.total) * 100) : 0)
const latePercent = computed(() => stats.total > 0 ? Math.round((stats.late / stats.total) * 100) : 0)

const companyColors = {
  ETC1992: 'background: linear-gradient(135deg, #10b981, #047857)',
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
      absent: sd.today?.absent || 0
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
