<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">รายงาน</h1>
          <p class="text-gray-500">รายงานสรุปการเข้างาน</p>
        </div>
        <button
          @click="exportCSV"
          :disabled="attendances.length === 0"
          class="btn-success flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          ส่งออก CSV
        </button>
      </div>

      <!-- Filters -->
      <div class="card">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่มต้น</label>
            <input v-model="filters.startDate" type="date" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่สิ้นสุด</label>
            <input v-model="filters.endDate" type="date" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="filters.companyId" class="input-field">
              <option value="">ทุกบริษัท</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button @click="fetchReport" class="btn-primary">ค้นหา</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Summary Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="card text-center">
            <p class="text-sm text-gray-500">วันทำงานทั้งหมด</p>
            <p class="text-2xl font-bold text-navy">{{ summary.totalDays }}</p>
          </div>
          <div class="card text-center">
            <p class="text-sm text-gray-500">เข้างานปกติ</p>
            <p class="text-2xl font-bold text-green-600">{{ summary.onTime }}</p>
          </div>
          <div class="card text-center">
            <p class="text-sm text-gray-500">เข้างานสาย</p>
            <p class="text-2xl font-bold text-yellow-600">{{ summary.late }}</p>
          </div>
          <div class="card text-center">
            <p class="text-sm text-gray-500">ไม่เข้างาน</p>
            <p class="text-2xl font-bold text-red-600">{{ summary.absent }}</p>
          </div>
        </div>

        <!-- Report Table -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">เวลาเข้า</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">เวลาออก</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(record, index) in attendances" :key="index" class="hover:bg-gray-50">
                  <td class="px-6 py-4 text-gray-600">{{ formatDate(record.date) }}</td>
                  <td class="px-6 py-4 font-medium text-navy">{{ record.employee?.name }}</td>
                  <td class="px-6 py-4 text-gray-600">{{ record.employee?.code }}</td>
                  <td class="px-6 py-4 text-gray-600">{{ record.employee?.company?.name }}</td>
                  <td class="px-6 py-4 text-center">
                    <span :class="record.is_late ? 'text-yellow-600' : 'text-green-600'">
                      {{ record.check_in ? formatTime(record.check_in) : '-' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center text-gray-600">
                    {{ record.check_out ? formatTime(record.check_out) : '-' }}
                  </td>
                  <td class="px-6 py-4 text-center">
                    <span
                      :class="[
                        'px-2 py-1 rounded-full text-xs font-medium',
                        record.is_late ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'
                      ]"
                    >
                      {{ record.is_late ? 'สาย' : 'ปกติ' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="attendances.length === 0" class="text-center py-8 text-gray-500">
            ไม่พบข้อมูลในช่วงวันที่ที่เลือก
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t">
            <p class="text-sm text-gray-500">
              แสดง {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }} จาก {{ totalItems }} รายการ
            </p>
            <div class="flex items-center gap-2">
              <button
                @click="currentPage--"
                :disabled="currentPage === 1"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50"
              >
                ก่อนหน้า
              </button>
              <button
                @click="currentPage++"
                :disabled="currentPage === totalPages"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50"
              >
                ถัดไป
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(false)
const attendances = ref([])
const companies = ref([])
const currentPage = ref(1)
const perPage = ref(20)
const totalItems = ref(0)
const totalPages = ref(0)

const summary = reactive({
  totalDays: 0,
  onTime: 0,
  late: 0,
  absent: 0
})

const today = new Date().toISOString().split('T')[0]
const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]

const filters = reactive({
  startDate: weekAgo,
  endDate: today,
  companyId: ''
})

async function fetchCompanies() {
  try {
    const response = await axios.get('/api/companies')
    companies.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching companies:', error)
  }
}

async function fetchReport() {
  loading.value = true
  try {
    const params = {
      start_date: filters.startDate,
      end_date: filters.endDate,
      company_id: filters.companyId || undefined,
      page: currentPage.value,
      per_page: perPage.value
    }
    const response = await axios.get('/api/reports/attendance', { params })
    attendances.value = response.data.data || response.data
    totalItems.value = response.data.total || attendances.value.length
    totalPages.value = Math.ceil(totalItems.value / perPage.value)

    summary.totalDays = response.data.summary?.totalDays || 0
    summary.onTime = response.data.summary?.onTime || 0
    summary.late = response.data.summary?.late || 0
    summary.absent = response.data.summary?.absent || 0
  } catch (error) {
    console.error('Error fetching report:', error)
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('th-TH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function formatTime(timeStr) {
  if (!timeStr) return '-'
  return new Date(timeStr).toLocaleTimeString('th-TH', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

function exportCSV() {
  if (attendances.value.length === 0) return

  const headers = ['วันที่', 'ชื่อ', 'รหัส', 'บริษัท', 'เวลาเข้า', 'เวลาออก', 'สถานะ']
  const rows = attendances.value.map(r => [
    formatDate(r.date),
    r.employee?.name,
    r.employee?.code,
    r.employee?.company?.name,
    r.check_in ? formatTime(r.check_in) : '-',
    r.check_out ? formatTime(r.check_out) : '-',
    r.is_late ? 'สาย' : 'ปกติ'
  ])

  const csvContent = [headers, ...rows].map(row => row.join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `attendance_report_${filters.startDate}_${filters.endDate}.csv`
  link.click()
}

onMounted(() => {
  fetchCompanies()
  fetchReport()
})
</script>
