<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ประวัติเข้างาน & คำเตือน</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <!-- Tab Bar -->
      <div class="flex bg-white rounded-xl p-1 border border-gray-200 shadow-sm mb-4">
        <button @click="tab = 'history'" :class="tab === 'history' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
          class="flex-1 py-2 rounded-lg text-sm font-medium transition-all">ประวัติ</button>
        <button @click="tab = 'warnings'" :class="tab === 'warnings' ? 'bg-red-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
          class="flex-1 py-2 rounded-lg text-sm font-medium transition-all">
          คำเตือน
          <span v-if="warnings.length" class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[10px] bg-white text-red-500 rounded-full font-bold">{{ warnings.length }}</span>
        </button>
      </div>

      <!-- History Tab -->
      <div v-if="tab === 'history'">
        <!-- View mode toggle -->
        <div class="flex bg-white rounded-xl p-1 border border-gray-200 shadow-sm mb-4">
          <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
            class="flex-1 py-1.5 rounded-lg text-xs font-medium transition-all">รายการ</button>
          <button @click="viewMode = 'calendar'" :class="viewMode === 'calendar' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
            class="flex-1 py-1.5 rounded-lg text-xs font-medium transition-all">ปฏิทิน</button>
        </div>

        <template v-if="viewMode === 'list'">
          <div v-if="loadingHistory" class="text-center py-12">
            <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>

          <div v-else-if="history.length === 0" class="text-center py-12">
            <p class="text-gray-400 text-sm">ไม่มีข้อมูลเข้างาน</p>
          </div>

          <div v-else class="space-y-2">
            <div v-for="record in history" :key="record.id" class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-800 text-sm">{{ formatDate(record.date) }}</p>
                  <p class="text-gray-400 text-xs mt-0.5">
                    {{ record.check_in ? record.check_in.substring(11, 16) : '-' }}
                    {{ record.check_out ? ' → ' + record.check_out.substring(11, 16) : '' }}
                  </p>
                </div>
                <span :class="record.status === 'on-time' ? 'bg-emerald-50 text-emerald-600' : record.status === 'late' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'"
                  class="text-[10px] font-bold px-2 py-1 rounded-full">
                  {{ statusLabel(record.status) }}
                </span>
              </div>
              <p v-if="record.note" class="text-gray-400 text-xs mt-2 italic">{{ record.note }}</p>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
            <MonthCalendar :year="calYear" :month="calMonth" @prev="calPrevMonth" @next="calNextMonth">
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

            <div v-if="calLoading" class="text-center py-6 text-gray-400 text-xs">กำลังโหลด...</div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-3 mt-4 pt-3 border-t border-gray-100 text-[11px] text-gray-500">
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>ปกติ</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>สาย</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>ลา</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-200"></span>ไม่มีข้อมูล</div>
            </div>

            <!-- Selected day detail -->
            <div v-if="selectedCalDay" class="mt-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
              <p class="font-medium text-gray-800 text-sm mb-1">{{ formatDate(selectedCalDay.date) }}</p>
              <template v-if="selectedCalDay.attendance">
                <p class="text-gray-500 text-xs">
                  เข้า {{ selectedCalDay.attendance.check_in || '-' }}
                  {{ selectedCalDay.attendance.check_out ? ' → ออก ' + selectedCalDay.attendance.check_out : '' }}
                </p>
                <p class="text-gray-500 text-xs mt-0.5">
                  สถานะ: {{ selectedCalDay.attendance.status === 'late' ? `สาย ${selectedCalDay.attendance.late_minutes} นาที` : 'ปกติ' }}
                  <span v-if="selectedCalDay.attendance.worked_hours">• ทำงาน {{ selectedCalDay.attendance.worked_hours }} ชม.</span>
                </p>
              </template>
              <template v-else-if="selectedCalDay.leave">
                <p class="text-gray-500 text-xs">ลา: {{ selectedCalDay.leave.leave_type }} ({{ selectedCalDay.leave.total_days }} วัน)</p>
                <p class="text-gray-500 text-xs mt-0.5">
                  {{ selectedCalDay.leave.start_date }} - {{ selectedCalDay.leave.end_date }} •
                  {{ selectedCalDay.leave.status === 'approved' ? 'อนุมัติแล้ว' : selectedCalDay.leave.status === 'pending' ? 'รออนุมัติ' : 'ปฏิเสธ' }}
                </p>
                <p v-if="selectedCalDay.leave.reason" class="text-gray-400 text-xs mt-0.5 italic">{{ selectedCalDay.leave.reason }}</p>
              </template>
            </div>
          </div>
        </template>
      </div>

      <!-- Warnings Tab -->
      <div v-if="tab === 'warnings'">
        <div v-if="loadingWarnings" class="text-center py-12">
          <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>

        <div v-else-if="warnings.length === 0" class="text-center py-12">
          <p class="text-gray-400 text-sm">ไม่มีคำเตือน</p>
        </div>

        <div v-else class="space-y-3">
          <!-- Summary Cards -->
          <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-amber-500">{{ warnings.length > 0 ? summary.late : 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">สายเดือนนี้</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-red-500">{{ warnings.length > 0 ? summary.absent : 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">ขาดเดือนนี้</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-purple-500">{{ warnings.length > 0 ? summary.early : 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">กลับก่อนเดือนนี้</p>
            </div>
          </div>

          <!-- Warning Items -->
          <div v-for="warn in warnings" :key="warn.id"
            :class="warn.severity === 'high' ? 'border-red-300 bg-red-50' : warn.severity === 'medium' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white'"
            class="rounded-xl p-4 border shadow-sm">
            <div class="flex items-start gap-3">
              <div :class="warn.severity === 'high' ? 'bg-red-100 text-red-600' : warn.severity === 'medium' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-600'"
                class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div class="flex-1">
                <p class="font-medium text-gray-800 text-sm">{{ warn.message }}</p>
                <p class="text-gray-400 text-xs mt-0.5">{{ formatDate(warn.created_at) }}</p>
                <span v-if="warn.severity === 'high'" class="inline-block mt-1 text-[10px] font-bold bg-red-500 text-white px-2 py-0.5 rounded-full">ระดับสูง</span>
                <span v-else-if="warn.severity === 'medium'" class="inline-block mt-1 text-[10px] font-bold bg-amber-500 text-white px-2 py-0.5 rounded-full">ระดับกลาง</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '../../services/api'
import MonthCalendar from '../../components/MonthCalendar.vue'

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const tab = ref('history')
const loadingHistory = ref(true)
const loadingWarnings = ref(true)
const history = ref([])
const warnings = ref([])
const summary = ref({ late: 0, absent: 0, early: 0 })

function statusLabel(s) {
  return s === 'on-time' ? 'ปกติ' : s === 'late' ? 'สาย' : s === 'early' ? 'กลับก่อน' : s === 'absent' ? 'ขาด' : s
}

// ─── Calendar view (History tab) ───
const viewMode = ref('list')
const today = new Date()
const calYear = ref(today.getFullYear())
const calMonth = ref(today.getMonth() + 1)
const calLoading = ref(false)
const calAttendance = ref([])
const calLeave = ref([])
const selectedCalDay = ref(null)

function calCellInfo(dateStr) {
  const att = calAttendance.value.find(a => a.date === dateStr) || null
  const leave = calLeave.value.find(l => dateStr >= l.start_date && dateStr <= l.end_date) || null
  const dotClass = att
    ? (att.status === 'late' ? 'bg-amber-400' : 'bg-emerald-400')
    : leave
      ? 'bg-blue-400'
      : ''
  return { attendance: att, leave, hasData: !!(att || leave), dotClass }
}

function calCellClass(dateStr) {
  const info = calCellInfo(dateStr)
  if (info.attendance) {
    return info.attendance.status === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'
  }
  if (info.leave) {
    return info.leave.status === 'approved' ? 'bg-blue-50 text-blue-700' : 'bg-blue-50/50 text-blue-400'
  }
  return 'text-gray-400 hover:bg-gray-50'
}

function selectCalDay(cell) {
  const info = calCellInfo(cell.date)
  if (!info.hasData) return
  selectedCalDay.value = { date: cell.date, attendance: info.attendance, leave: info.leave }
}

async function loadCalendarMonth() {
  calLoading.value = true
  selectedCalDay.value = null
  try {
    const res = await api.get('/api/employee/attendance/history', { params: { month: calMonth.value, year: calYear.value } })
    if (res.data.success) {
      calAttendance.value = res.data.data.attendance || []
      calLeave.value = res.data.data.leave || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    calLoading.value = false
  }
}

function calPrevMonth() {
  calMonth.value--
  if (calMonth.value < 1) { calMonth.value = 12; calYear.value-- }
  loadCalendarMonth()
}

function calNextMonth() {
  calMonth.value++
  if (calMonth.value > 12) { calMonth.value = 1; calYear.value++ }
  loadCalendarMonth()
}

let calLoaded = false
watch(viewMode, (mode) => {
  if (mode === 'calendar' && !calLoaded) {
    calLoaded = true
    loadCalendarMonth()
  }
})

function formatDate(d) {
  const dt = new Date(d)
  return `${dt.getDate()} ${thMonths[dt.getMonth()]} ${dt.getFullYear() + 543}`
}

onMounted(async () => {
  try {
    const [histRes, warnRes] = await Promise.allSettled([
      api.get('/api/employee/attendance/history', { params: { limit: 30 } }),
      api.get('/api/employee/warnings'),
    ])
    if (histRes.status === 'fulfilled' && histRes.value.data.success) {
      history.value = histRes.value.data.data
    }
    if (warnRes.status === 'fulfilled' && warnRes.value.data.success) {
      warnings.value = warnRes.value.data.data.warnings || []
      summary.value = warnRes.value.data.data.summary || { late: 0, absent: 0, early: 0 }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingHistory.value = false
    loadingWarnings.value = false
  }
})
</script>
