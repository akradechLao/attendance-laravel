<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">รายงาน</h1>
          <p class="text-gray-500">ส่งออกรายงานเข้างาน ลางาน และ OT</p>
        </div>
        <button
          @click="exportCSV"
          :disabled="records.length === 0 || exporting"
          class="btn-success flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          {{ exporting ? 'กำลังส่งออก...' : 'ส่งออก CSV' }}
        </button>
        <button
          @click="exportPDF"
          :disabled="records.length === 0 || exporting"
          class="btn-primary flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          {{ exporting ? 'กำลังส่งออก...' : 'ส่งออก PDF' }}
        </button>
      </div>

      <div class="border-b border-gray-200">
        <nav class="flex gap-0">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="switchTab(tab.key)"
            :class="[
              'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
              activeTab === tab.key
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <div class="card">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ช่วงวันที่</label>
            <div class="flex gap-2">
              <button
                v-for="preset in datePresets"
                :key="preset.label"
                @click="applyPreset(preset)"
                :class="[
                  'px-3 py-1.5 text-xs rounded-lg border transition-colors',
                  activePreset === preset.label
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                ]"
              >
                {{ preset.label }}
              </button>
            </div>
          </div>
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
          <button @click="fetchReport" :disabled="loading" class="btn-primary">
            {{ loading ? 'กำลังค้นหา...' : 'ค้นหา' }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <template v-if="activeTab === 'attendance'">
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
                  <tr v-for="(record, index) in paginatedRecords" :key="index" class="hover:bg-gray-50">
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
            <div v-if="records.length === 0" class="text-center py-8 text-gray-500">
              ไม่พบข้อมูลในช่วงวันที่ที่เลือก
            </div>
          </div>
        </template>

        <template v-if="activeTab === 'leave'">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card text-center">
              <p class="text-sm text-gray-500">ทั้งหมด</p>
              <p class="text-2xl font-bold text-navy">{{ summary.total }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">อนุมัติแล้ว</p>
              <p class="text-2xl font-bold text-green-600">{{ summary.approved }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">รออนุมัติ</p>
              <p class="text-2xl font-bold text-yellow-600">{{ summary.pending }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">ไม่อนุมัติ</p>
              <p class="text-2xl font-bold text-red-600">{{ summary.rejected }}</p>
            </div>
          </div>
          <div class="card overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่เริ่ม</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่สิ้นสุด</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ประเภท</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">เหตุผล</th>
                    <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(record, index) in paginatedRecords" :key="index" class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-600">{{ formatDate(record.start_date) }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ formatDate(record.end_date) }}</td>
                    <td class="px-6 py-4 font-medium text-navy">{{ record.employee?.name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ record.employee?.code }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ record.employee?.company?.name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ record.leaveType?.name }}</td>
                    <td class="px-6 py-4 text-gray-600 max-w-[200px] truncate">{{ record.reason }}</td>
                    <td class="px-6 py-4 text-center">
                      <span :class="['px-2 py-1 rounded-full text-xs font-medium', statusClass(record.status)]">
                        {{ statusLabel(record.status) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="records.length === 0" class="text-center py-8 text-gray-500">
              ไม่พบข้อมูลในช่วงวันที่ที่เลือก
            </div>
          </div>
        </template>

        <template v-if="activeTab === 'ot'">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card text-center">
              <p class="text-sm text-gray-500">ทั้งหมด</p>
              <p class="text-2xl font-bold text-navy">{{ summary.total }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">อนุมัติแล้ว</p>
              <p class="text-2xl font-bold text-green-600">{{ summary.approved }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">รออนุมัติ</p>
              <p class="text-2xl font-bold text-yellow-600">{{ summary.pending }}</p>
            </div>
            <div class="card text-center">
              <p class="text-sm text-gray-500">ไม่อนุมัติ</p>
              <p class="text-2xl font-bold text-red-600">{{ summary.rejected }}</p>
            </div>
          </div>
          <div class="card overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                    <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">เวลาเริ่ม</th>
                    <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">เวลาสิ้นสุด</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">เหตุผล</th>
                    <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(record, index) in paginatedRecords" :key="index" class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-600">{{ formatDate(record.date) }}</td>
                    <td class="px-6 py-4 font-medium text-navy">{{ record.employee?.name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ record.employee?.code }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ record.employee?.company?.name }}</td>
                    <td class="px-6 py-4 text-center text-gray-600">{{ record.start_time }}</td>
                    <td class="px-6 py-4 text-center text-gray-600">{{ record.end_time }}</td>
                    <td class="px-6 py-4 text-gray-600 max-w-[200px] truncate">{{ record.reason }}</td>
                    <td class="px-6 py-4 text-center">
                      <span :class="['px-2 py-1 rounded-full text-xs font-medium', statusClass(record.status)]">
                        {{ statusLabel(record.status) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="records.length === 0" class="text-center py-8 text-gray-500">
              ไม่พบข้อมูลในช่วงวันที่ที่เลือก
            </div>
          </div>
        </template>

        <div v-if="totalPages > 1" class="flex items-center justify-between">
          <p class="text-sm text-gray-500">
            แสดง {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, records.length) }} จาก {{ records.length }} รายการ
          </p>
          <div class="flex items-center gap-2">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm"
            >
              ก่อนหน้า
            </button>
            <template v-for="p in visiblePages" :key="p">
              <button
                v-if="p !== '...'"
                @click="currentPage = p"
                :class="[
                  'px-3 py-1 rounded border text-sm',
                  p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'
                ]"
              >
                {{ p }}
              </button>
              <span v-else class="text-gray-400">...</span>
            </template>
            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm"
            >
              ถัดไป
            </button>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(false)
const exporting = ref(false)
const records = ref([])
const companies = ref([])
const currentPage = ref(1)
const perPage = 20
const activeTab = ref('attendance')
const activePreset = ref('7 วัน')

const tabs = [
  { key: 'attendance', label: 'เข้างาน' },
  { key: 'leave', label: 'ลางาน' },
  { key: 'ot', label: 'OT' }
]

const summary = reactive({
  totalDays: 0, onTime: 0, late: 0, absent: 0,
  total: 0, approved: 0, pending: 0, rejected: 0
})

function formatDateISO(d) {
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return yyyy + '-' + mm + '-' + dd
}

const now = new Date()
const filters = reactive({
  startDate: formatDateISO(new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)),
  endDate: formatDateISO(now),
  companyId: ''
})

const datePresets = computed(() => {
  const now2 = new Date()
  return [
    { label: '7 วัน', start: formatDateISO(new Date(now2.getTime() - 7 * 24 * 60 * 60 * 1000)), end: formatDateISO(now2) },
    { label: 'เดือนนี้', start: formatDateISO(new Date(now2.getFullYear(), now2.getMonth(), 1)), end: formatDateISO(now2) },
    { label: 'เดือนที่แล้ว', start: formatDateISO(new Date(now2.getFullYear(), now2.getMonth() - 1, 1)), end: formatDateISO(new Date(now2.getFullYear(), now2.getMonth(), 0)) },
    { label: '3 เดือน', start: formatDateISO(new Date(now2.getFullYear(), now2.getMonth() - 3, 1)), end: formatDateISO(now2) }
  ]
})

const totalPages = computed(() => Math.ceil(records.value.length / perPage))

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return records.value.slice(start, start + perPage)
})

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const curr = currentPage.value
  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (curr > 3) pages.push('...')
    for (let i = Math.max(2, curr - 1); i <= Math.min(total - 1, curr + 1); i++) {
      pages.push(i)
    }
    if (curr < total - 2) pages.push('...')
    pages.push(total)
  }
  return pages
})

function formatDateDisplay(dateStr) {
  if (!dateStr) return '-'
  const str = String(dateStr)
  const parts = str.split('T')[0].split('-')
  if (parts.length < 3) return str
  const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']
  const day = parseInt(parts[2], 10)
  const month = months[parseInt(parts[1], 10) - 1]
  const year = parseInt(parts[0], 10) + 543
  return day + ' ' + month + ' ' + year
}

const formatDate = formatDateDisplay

function formatTime(timeStr) {
  if (!timeStr) return '-'
  const str = String(timeStr)
  if (str.includes('T')) {
    const timePart = str.split('T')[1]
    return timePart.substring(0, 5)
  }
  return str.substring(0, 5)
}

function statusClass(status) {
  if (status === 'approved') return 'bg-green-100 text-green-700'
  if (status === 'pending') return 'bg-yellow-100 text-yellow-700'
  if (status === 'rejected') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-700'
}

function statusLabel(status) {
  const labels = { approved: 'อนุมัติ', pending: 'รออนุมัติ', rejected: 'ไม่อนุมัติ' }
  return labels[status] || status
}

function switchTab(tab) {
  activeTab.value = tab
  currentPage.value = 1
  records.value = []
  fetchReport()
}

function applyPreset(preset) {
  activePreset.value = preset.label
  filters.startDate = preset.start
  filters.endDate = preset.end
  fetchReport()
}

async function fetchCompanies() {
  try {
    const response = await api.get('/api/companies')
    companies.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching companies:', error)
  }
}

async function fetchReport() {
  loading.value = true
  currentPage.value = 1
  try {
    const params = {
      start_date: filters.startDate,
      end_date: filters.endDate,
    }
    if (filters.companyId) params.company_id = filters.companyId

    let endpoint = '/api/reports/attendance'
    if (activeTab.value === 'leave') endpoint = '/api/reports/leave'
    if (activeTab.value === 'ot') endpoint = '/api/reports/ot'

    const response = await api.get(endpoint, { params })
    const data = response.data.data
    records.value = data.records || []

    if (activeTab.value === 'attendance') {
      summary.totalDays = data.summary?.total_days || 0
      summary.onTime = data.summary?.on_time || 0
      summary.late = data.summary?.late || 0
      summary.absent = data.summary?.absent || 0
    } else {
      summary.total = data.summary?.total || 0
      summary.approved = data.summary?.approved || 0
      summary.pending = data.summary?.pending || 0
      summary.rejected = data.summary?.rejected || 0
    }
  } catch (error) {
    console.error('Error fetching report:', error)
  } finally {
    loading.value = false
  }
}

function exportCSV() {
  if (records.value.length === 0) return
  exporting.value = true

  let headers, rows

  if (activeTab.value === 'attendance') {
    headers = ['วันที่', 'ชื่อ', 'รหัส', 'บริษัท', 'เวลาเข้า', 'เวลาออก', 'สถานะ']
    rows = records.value.map(r => [
      formatDateDisplay(r.date),
      r.employee?.name || '',
      r.employee?.code || '',
      r.employee?.company?.name || '',
      r.check_in ? formatTime(r.check_in) : '-',
      r.check_out ? formatTime(r.check_out) : '-',
      r.is_late ? 'สาย' : 'ปกติ'
    ])
  } else if (activeTab.value === 'leave') {
    headers = ['วันที่เริ่ม', 'วันที่สิ้นสุด', 'ชื่อ', 'รหัส', 'บริษัท', 'ประเภท', 'เหตุผล', 'สถานะ']
    rows = records.value.map(r => [
      formatDateDisplay(r.start_date),
      formatDateDisplay(r.end_date),
      r.employee?.name || '',
      r.employee?.code || '',
      r.employee?.company?.name || '',
      r.leaveType?.name || '',
      r.reason || '',
      statusLabel(r.status)
    ])
  } else {
    headers = ['วันที่', 'ชื่อ', 'รหัส', 'บริษัท', 'เวลาเริ่ม', 'เวลาสิ้นสุด', 'เหตุผล', 'สถานะ']
    rows = records.value.map(r => [
      formatDateDisplay(r.date),
      r.employee?.name || '',
      r.employee?.code || '',
      r.employee?.company?.name || '',
      r.start_time || '-',
      r.end_time || '-',
      r.reason || '',
      statusLabel(r.status)
    ])
  }

  const csvContent = [headers, ...rows].map(row =>
    row.map(cell => '"' + String(cell).replace(/"/g, '""') + '"').join(',')
  ).join('\n')

  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  const tabNames = { attendance: 'attendance', leave: 'leave', ot: 'ot' }
  link.download = tabNames[activeTab.value] + '_' + filters.startDate + '_' + filters.endDate + '.csv'
  link.click()
  URL.revokeObjectURL(link.href)

  setTimeout(() => { exporting.value = false }, 500)
}

function exportPDF() {
  if (records.value.length === 0) return
  exporting.value = true
  const params = new URLSearchParams({
    start_date: filters.startDate,
    end_date: filters.endDate,
  })
  if (filters.companyId) params.append('company_id', filters.companyId)
  const urls = {
    attendance: '/api/reports/export-attendance-pdf',
    leave: '/api/reports/export-leave-pdf',
    ot: '/api/reports/export-ot-pdf',
  }
  window.location.href = urls[activeTab.value] + '?' + params.toString()
  setTimeout(() => { exporting.value = false }, 2000)
}

onMounted(() => {
  fetchCompanies()
  fetchReport()
})
</script>
