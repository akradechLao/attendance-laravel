<script setup>
import { ref, computed, onMounted } from 'vue'
import store from '../../store'
import api from '@/services/api'

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']
const thMonthsShort = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']

const loading = ref(false)
const attendanceHistory = ref([])
const leaveHistory = ref([])
const selectedTab = ref('attendance')
const summary = ref(null)

const now = new Date()
const statMonth = ref(now.getMonth() + 1)
const statYear = ref(now.getFullYear())

const isCurrentMonth = computed(() => {
  const c = new Date()
  return statMonth.value === c.getMonth() + 1 && statYear.value === c.getFullYear()
})

onMounted(async () => {
  await loadHistory()
})

const loadHistory = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/employee/attendance/history', {
      params: { month: statMonth.value, year: statYear.value }
    })
    if (response.data.data && Array.isArray(response.data.data)) {
      attendanceHistory.value = response.data.data
      leaveHistory.value = []
      summary.value = null
    } else {
      attendanceHistory.value = response.data.data?.attendance || []
      leaveHistory.value = response.data.data?.leave || []
      summary.value = response.data.summary || null
    }
  } catch (error) {
    console.error('Failed to load history:', error)
  } finally {
    loading.value = false
  }
}

function prevMonth() {
  if (statMonth.value === 1) {
    statMonth.value = 12
    statYear.value--
  } else {
    statMonth.value--
  }
  loadHistory()
}

function nextMonth() {
  if (isCurrentMonth.value) return
  if (statMonth.value === 12) {
    statMonth.value = 1
    statYear.value++
  } else {
    statMonth.value++
  }
  loadHistory()
}

function fmtDate(d) {
  if (!d) return '-'
  const parts = d.split('-')
  if (parts.length < 3) return d
  const day = parseInt(parts[2])
  const month = parseInt(parts[1]) - 1
  return `${day} ${thMonthsShort[month]} ${parseInt(parts[0]) + 543}`
}

function fmtTime(t) {
  if (!t) return '-'
  return t.substring(0, 5)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ประวัติของฉัน</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <!-- Month Selector -->
      <div class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
          <button @click="prevMonth" class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <p class="text-base font-bold text-gray-700">{{ thMonths[statMonth - 1] }} {{ statYear + 543 }}</p>
          <button @click="nextMonth" :disabled="isCurrentMonth" :class="['w-9 h-9 rounded-full flex items-center justify-center transition-colors', isCurrentMonth ? 'bg-gray-50 text-gray-300 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200 text-gray-600']">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
      </div>

      <!-- Summary Stats (when viewing specific month) -->
      <div v-if="summary" class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl p-3 border border-gray-200 shadow-sm text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ summary.total_days }}</p>
          <p class="text-[10px] text-emerald-600 font-medium mt-0.5">วันที่เข้างาน</p>
        </div>
        <div class="bg-white rounded-xl p-3 border border-gray-200 shadow-sm text-center">
          <p class="text-2xl font-bold text-blue-600">{{ summary.on_time }}</p>
          <p class="text-[10px] text-blue-600 font-medium mt-0.5">ตรงเวลา</p>
        </div>
        <div class="bg-white rounded-xl p-3 border border-gray-200 shadow-sm text-center">
          <p class="text-2xl font-bold text-amber-600">{{ summary.late }}</p>
          <p class="text-[10px] text-amber-600 font-medium mt-0.5">สาย</p>
        </div>
        <div v-if="summary.total_late_minutes > 0" class="col-span-3 text-center text-xs text-amber-600">
          สายรวม {{ summary.total_late_minutes }} นาที
        </div>
        <div v-if="summary.leave_days > 0" class="col-span-3 bg-purple-50 rounded-xl p-2 text-center border border-purple-200">
          <p class="text-sm font-bold text-purple-600">ลารวม {{ summary.leave_days }} วัน</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b">
        <nav class="flex gap-6">
          <button
            @click="selectedTab = 'attendance'"
            :class="['py-2 px-1 border-b-2 font-medium text-sm', selectedTab === 'attendance' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >
            ประวัติการเข้างาน
          </button>
          <button
            @click="selectedTab = 'leave'"
            :class="['py-2 px-1 border-b-2 font-medium text-sm', selectedTab === 'leave' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >
            ประวัติการลา
          </button>
        </nav>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <!-- Attendance History -->
      <div v-else-if="selectedTab === 'attendance'" class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">กะ</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">เข้า</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">ออก</th>
              <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">สาย</th>
              <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">ชั่วโมงทำงาน</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="record in attendanceHistory" :key="record.id">
              <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">{{ fmtDate(record.date) }}</td>
              <td class="px-3 py-3 whitespace-nowrap text-sm">
                <div v-if="record.shift_code" class="flex flex-col">
                  <span class="text-blue-600 font-medium text-xs">{{ record.shift_code }}</span>
                  <span class="text-[10px] text-gray-400">{{ fmtTime(record.schedule_start) }}-{{ fmtTime(record.schedule_end) }}</span>
                </div>
                <span v-else class="text-gray-300">-</span>
              </td>
              <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                <span :class="record.check_in && record.schedule_start && record.check_in > record.schedule_start ? 'text-amber-600' : 'text-gray-900'">
                  {{ fmtTime(record.check_in) }}
                </span>
              </td>
              <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">{{ fmtTime(record.check_out) }}</td>
              <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                <span v-if="record.late_minutes > 0" class="text-amber-600 font-medium">{{ record.late_minutes }} นาที</span>
                <span v-else class="text-gray-300">-</span>
              </td>
              <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                <span v-if="record.worked_hours != null" class="text-blue-600 font-medium">{{ record.worked_hours }} ชม.</span>
                <span v-else class="text-gray-300">-</span>
              </td>
              <td class="px-3 py-3 whitespace-nowrap text-sm">
                <span :class="record.status === 'late' ? 'text-amber-600' : record.status === 'on_time' ? 'text-emerald-600' : 'text-gray-500'" class="font-medium">
                  {{ record.status === 'on_time' ? 'ปกติ' : record.status === 'late' ? 'สาย' : record.status || '-' }}
                </span>
              </td>
            </tr>
            <tr v-if="attendanceHistory.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Leave History -->
      <div v-else-if="selectedTab === 'leave'" class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ประเภท</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">เหตุผล</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="record in leaveHistory" :key="record.id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.leave_type || record.leaveType?.name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ fmtDate(record.start_date) }} - {{ fmtDate(record.end_date) }}</td>
              <td class="px-4 py-3 text-sm text-gray-900">{{ record.reason || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">
                <span :class="record.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : record.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ record.status === 'approved' ? 'อนุมัติแล้ว' : record.status === 'rejected' ? 'ปฏิเสธ' : 'รออนุมัติ' }}
                </span>
              </td>
            </tr>
            <tr v-if="leaveHistory.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</template>
