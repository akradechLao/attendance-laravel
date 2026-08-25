<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">ประวัติการแก้ไขข้อมูล</h1>
          <p class="text-gray-500">Audit Log - ดูการเปลี่ยนแปลงข้อมูลในระบบ</p>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="card text-center">
          <p class="text-sm text-gray-500">วันนี้</p>
          <p class="text-2xl font-bold text-navy">{{ stats.today || 0 }}</p>
        </div>
        <div class="card text-center">
          <p class="text-sm text-gray-500">สัปดาห์นี้</p>
          <p class="text-2xl font-bold text-blue-600">{{ stats.this_week || 0 }}</p>
        </div>
        <div class="card text-center">
          <p class="text-sm text-gray-500">เดือนนี้</p>
          <p class="text-2xl font-bold text-green-600">{{ stats.this_month || 0 }}</p>
        </div>
      </div>

      <div class="card">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่วันที่</label>
            <input v-model="filters.startDate" type="date" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ถึงวันที่</label>
            <input v-model="filters.endDate" type="date" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">การกระทำ</label>
            <select v-model="filters.action" class="input-field">
              <option value="">ทั้งหมด</option>
              <option value="create">สร้าง</option>
              <option value="update">แก้ไข</option>
              <option value="delete">ลบ</option>
              <option value="approve">อนุมัติ</option>
              <option value="reject">ไม่อนุมัติ</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทข้อมูล</label>
            <select v-model="filters.auditable_type" class="input-field">
              <option value="">ทั้งหมด</option>
              <option value="AttendanceLog">เข้างาน</option>
              <option value="LeaveRequest">ลางาน</option>
              <option value="OtRequest">OT</option>
              <option value="Payslip">สลิปเงินเดือน</option>
              <option value="ShiftSchedule">กะทำงาน</option>
              <option value="Employee">พนักงาน</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input v-model="filters.search" type="text" class="input-field" placeholder="ชื่อผู้ใช้..." />
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button @click="fetchLogs" :disabled="loading" class="btn-primary">
            {{ loading ? 'กำลังค้นหา...' : 'ค้นหา' }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่/เวลา</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ผู้ใช้</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">การกระทำ</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รายละเอียด</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ประเภท</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">ดู</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-if="logs.length === 0">
                  <td colspan="6" class="px-6 py-8 text-center text-gray-500">ไม่พบข้อมูล</td>
                </tr>
                <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                    {{ formatDateTime(log.created_at) }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <span :class="[
                        'w-2 h-2 rounded-full shrink-0',
                        log.user_type === 'admin' ? 'bg-blue-500' : 'bg-green-500'
                      ]"></span>
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ log.user_name || '-' }}</p>
                        <p class="text-xs text-gray-400">{{ log.user_type }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', actionClass(log.action)]">
                      {{ actionLabel(log.action) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-700 max-w-[300px] truncate">
                    {{ log.description || '-' }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500">
                    {{ modelName(log.auditable_type) }}
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button
                      @click="showDetail(log)"
                      class="text-blue-600 hover:text-blue-800 text-sm"
                    >
                      ดู
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="pagination.lastPage > 1" class="flex items-center justify-between px-6 py-4 border-t">
            <p class="text-sm text-gray-500">
              แสดง {{ (pagination.currentPage - 1) * pagination.perPage + 1 }}-{{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }} จาก {{ pagination.total }} รายการ
            </p>
            <div class="flex items-center gap-2">
              <button
                @click="pagination.currentPage > 1 && (filters.page = pagination.currentPage - 1, fetchLogs())"
                :disabled="pagination.currentPage === 1"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm"
              >
                ก่อนหน้า
              </button>
              <button
                @click="pagination.currentPage < pagination.lastPage && (filters.page = pagination.currentPage + 1, fetchLogs())"
                :disabled="pagination.currentPage === pagination.lastPage"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm"
              >
                ถัดไป
              </button>
            </div>
          </div>
        </div>
      </template>

      <div v-if="detailLog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="detailLog = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold text-gray-900">รายละเอียด</h3>
              <button @click="detailLog = null" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-500 uppercase">วันที่/เวลา</p>
                  <p class="text-sm font-medium">{{ formatDateTime(detailLog.created_at) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase">ผู้ใช้</p>
                  <p class="text-sm font-medium">{{ detailLog.user_name || '-' }} ({{ detailLog.user_type }})</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase">การกระทำ</p>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', actionClass(detailLog.action)]">
                    {{ actionLabel(detailLog.action) }}
                  </span>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase">IP Address</p>
                  <p class="text-sm font-medium">{{ detailLog.ip_address || '-' }}</p>
                </div>
              </div>

              <div>
                <p class="text-xs text-gray-500 uppercase mb-1">รายละเอียด</p>
                <p class="text-sm text-gray-700">{{ detailLog.description || '-' }}</p>
              </div>

              <div v-if="detailLog.old_values || detailLog.new_values" class="border-t pt-4">
                <p class="text-xs text-gray-500 uppercase mb-2">การเปลี่ยนแปลง</p>
                <div class="bg-gray-50 rounded-lg p-4 text-sm">
                  <template v-if="detailLog.old_values">
                    <p class="text-red-600 font-medium mb-1">ค่าเดิม:</p>
                    <pre class="text-xs text-gray-600 whitespace-pre-wrap">{{ JSON.stringify(detailLog.old_values, null, 2) }}</pre>
                  </template>
                  <template v-if="detailLog.new_values">
                    <p class="text-green-600 font-medium mb-1 mt-3">ค่าใหม่:</p>
                    <pre class="text-xs text-gray-600 whitespace-pre-wrap">{{ JSON.stringify(detailLog.new_values, null, 2) }}</pre>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(false)
const logs = ref([])
const stats = ref({})
const detailLog = ref(null)

const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
  perPage: 20,
  total: 0,
})

const today = new Date()
const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate())

function formatDateISO(d) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
}

const filters = reactive({
  startDate: formatDateISO(monthAgo),
  endDate: formatDateISO(today),
  action: '',
  auditable_type: '',
  search: '',
  page: 1,
})

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  const date = d.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' })
  const time = d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
  return date + ' ' + time
}

function actionClass(action) {
  const map = {
    create: 'bg-green-100 text-green-700',
    update: 'bg-blue-100 text-blue-700',
    delete: 'bg-red-100 text-red-700',
    approve: 'bg-emerald-100 text-emerald-700',
    reject: 'bg-orange-100 text-orange-700',
  }
  return map[action] || 'bg-gray-100 text-gray-700'
}

function actionLabel(action) {
  const map = { create: 'สร้าง', update: 'แก้ไข', delete: 'ลบ', approve: 'อนุมัติ', reject: 'ไม่อนุมัติ' }
  return map[action] || action
}

function modelName(type) {
  if (!type) return '-'
  const map = {
    'App\\Models\\AttendanceLog': 'เข้างาน',
    'App\\Models\\LeaveRequest': 'ลางาน',
    'App\\Models\\OtRequest': 'OT',
    'App\\Models\\Payslip': 'สลิปเงินเดือน',
    'App\\Models\\ShiftSchedule': 'กะทำงาน',
    'App\\Models\\Employee': 'พนักงาน',
    'App\\Models\\WfhRecord': 'WFH',
    'App\\Models\\CompanyHoliday': 'วันหยุด',
  }
  return map[type] || type.split('\\').pop()
}

async function fetchLogs() {
  loading.value = true
  try {
    const params = {
      page: filters.page,
      per_page: pagination.perPage,
    }
    if (filters.startDate) params.start_date = filters.startDate
    if (filters.endDate) params.end_date = filters.endDate
    if (filters.action) params.action = filters.action
    if (filters.auditable_type) params.auditable_type = filters.auditable_type
    if (filters.search) params.search = filters.search

    const response = await api.get('/api/audit-logs', { params })
    const data = response.data.data
    logs.value = data.data || []
    pagination.currentPage = data.current_page || 1
    pagination.lastPage = data.last_page || 1
    pagination.total = data.total || 0

    if (response.data.stats) {
      stats.value = response.data.stats
    }
  } catch (error) {
    console.error('Error fetching audit logs:', error)
  } finally {
    loading.value = false
  }
}

async function fetchSummary() {
  try {
    const response = await api.get('/api/audit-logs/summary')
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Error fetching audit summary:', error)
  }
}

function showDetail(log) {
  detailLog.value = log
}

watch(() => filters.action, () => { filters.page = 1; fetchLogs() })
watch(() => filters.auditable_type, () => { filters.page = 1; fetchLogs() })

onMounted(() => {
  fetchLogs()
  fetchSummary()
})
</script>
