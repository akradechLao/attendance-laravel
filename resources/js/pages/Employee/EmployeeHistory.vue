<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import store from '../../store'
import api from '@/services/api'
import MonthCalendar from '../../components/MonthCalendar.vue'

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

// ─── Calendar view (ใช้ข้อมูลเดือนเดียวกับตารางที่โหลดไว้แล้ว ไม่ต้องยิง API เพิ่ม) ───
const viewMode = ref('table')
const selectedCalDay = ref(null)

function calCellInfo(dateStr) {
  if (selectedTab.value === 'leave') {
    const leave = leaveHistory.value.find(l => dateStr >= l.start_date && dateStr <= l.end_date) || null
    const dotClass = leave ? (leave.status === 'approved' ? 'bg-blue-400' : leave.status === 'rejected' ? 'bg-gray-300' : 'bg-blue-200') : ''
    return { attendance: null, leave, hasData: !!leave, dotClass }
  }
  const attendance = attendanceHistory.value.find(a => a.date === dateStr) || null
  const dotClass = attendance ? (attendance.status === 'late' ? 'bg-amber-400' : 'bg-emerald-400') : ''
  return { attendance, leave: null, hasData: !!attendance, dotClass }
}

function calCellClass(dateStr) {
  const info = calCellInfo(dateStr)
  if (info.attendance) {
    return info.attendance.status === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'
  }
  if (info.leave) {
    return info.leave.status === 'approved' ? 'bg-blue-50 text-blue-700' : info.leave.status === 'rejected' ? 'bg-gray-50 text-gray-400' : 'bg-blue-50/50 text-blue-400'
  }
  return 'text-gray-400 hover:bg-gray-50'
}

function selectCalDay(cell) {
  const info = calCellInfo(cell.date)
  if (!info.hasData) return
  selectedCalDay.value = { date: cell.date, attendance: info.attendance, leave: info.leave }
}

function calPrevMonth() { selectedCalDay.value = null; prevMonth() }
function calNextMonth() { selectedCalDay.value = null; nextMonth() }

watch(selectedTab, () => { selectedCalDay.value = null })
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
      <div class="flex items-center justify-between border-b">
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
        <div class="flex bg-gray-100 rounded-lg p-0.5 mb-1">
          <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-white shadow text-gray-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-xs font-medium transition-all">รายการ</button>
          <button @click="viewMode = 'calendar'" :class="viewMode === 'calendar' ? 'bg-white shadow text-gray-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-xs font-medium transition-all">ปฏิทิน</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <!-- Calendar View (ทั้งสองแท็บ) -->
      <div v-else-if="viewMode === 'calendar'" class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
        <MonthCalendar :year="statYear" :month="statMonth" @prev="calPrevMonth" @next="calNextMonth">
          <template #cell="{ cell }">
            <button
              v-if="cell"
              @click="selectCalDay(cell)"
              class="w-full h-full rounded-lg flex flex-col items-center justify-center text-[11px] transition-colors"
              :class="[calCellClass(cell.date), cell.isToday ? 'ring-2 ring-blue-400' : '', calCellInfo(cell.date).hasData ? 'cursor-pointer' : 'cursor-default']"
            >
              <span>{{ cell.day }}</span>
              <span v-if="calCellInfo(cell.date).hasData" class="w-1 h-1 rounded-full mt-0.5" :class="calCellInfo(cell.date).dotClass"></span>
            </button>
          </template>
        </MonthCalendar>

        <div class="flex flex-wrap gap-3 mt-4 pt-3 border-t border-gray-100 text-[11px] text-gray-500">
          <template v-if="selectedTab === 'attendance'">
            <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>ปกติ</div>
            <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>สาย</div>
          </template>
          <template v-else>
            <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>อนุมัติแล้ว</div>
            <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-200"></span>รออนุมัติ</div>
            <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span>ปฏิเสธ</div>
          </template>
          <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-200"></span>ไม่มีข้อมูล</div>
        </div>

        <div v-if="selectedCalDay" class="mt-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
          <p class="font-medium text-gray-800 text-sm mb-1">{{ fmtDate(selectedCalDay.date) }}</p>
          <template v-if="selectedCalDay.attendance">
            <p class="text-gray-500 text-xs">
              เข้า {{ fmtTime(selectedCalDay.attendance.check_in) }}
              {{ selectedCalDay.attendance.check_out ? ' → ออก ' + fmtTime(selectedCalDay.attendance.check_out) : '' }}
            </p>
            <p class="text-gray-500 text-xs mt-0.5">
              สถานะ: {{ selectedCalDay.attendance.status === 'late' ? `สาย ${selectedCalDay.attendance.late_minutes} นาที` : 'ปกติ' }}
              <span v-if="selectedCalDay.attendance.worked_hours != null">• ทำงาน {{ selectedCalDay.attendance.worked_hours }} ชม.</span>
            </p>
          </template>
          <template v-else-if="selectedCalDay.leave">
            <p class="text-gray-500 text-xs">{{ selectedCalDay.leave.leave_type || selectedCalDay.leave.leaveType?.name }} ({{ selectedCalDay.leave.total_days }} วัน)</p>
            <p class="text-gray-500 text-xs mt-0.5">
              {{ fmtDate(selectedCalDay.leave.start_date) }} - {{ fmtDate(selectedCalDay.leave.end_date) }} •
              {{ selectedCalDay.leave.status === 'approved' ? 'อนุมัติแล้ว' : selectedCalDay.leave.status === 'rejected' ? 'ปฏิเสธ' : 'รออนุมัติ' }}
            </p>
            <p v-if="selectedCalDay.leave.reason" class="text-gray-400 text-xs mt-0.5 italic">{{ selectedCalDay.leave.reason }}</p>
          </template>
        </div>
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
