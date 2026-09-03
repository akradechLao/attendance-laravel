<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">สรุปวันนี้</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 text-sm mt-3">กำลังโหลดข้อมูล...</p>
      </div>

      <div v-else-if="error" class="text-center py-12">
        <div class="w-16 h-16 mx-auto rounded-full bg-red-100 flex items-center justify-center mb-3">
          <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <p class="text-red-500 text-sm font-medium">ไม่สามารถโหลดข้อมูลได้</p>
        <p class="text-gray-400 text-xs mt-1">{{ error }}</p>
        <button @click="fetchData" class="mt-3 bg-blue-500 text-white px-6 py-2 rounded-xl text-sm font-bold hover:bg-blue-600">
          ลองใหม่
        </button>
      </div>

      <div v-else class="space-y-4">
        <!-- Greeting -->
        <div class="text-center mb-2">
          <p class="text-gray-500 text-sm">{{ todayThai }}</p>
        </div>

        <!-- Today Status Card -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">สถานะวันนี้</h2>

          <!-- Today's Shift (from shift_schedules) -->
          <div v-if="data.today.today_shift_code" class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-blue-600 text-xs font-medium">เข้างานวันนี้ (กะ {{ data.today.today_shift_code }})</p>
                <p class="text-xl font-bold text-blue-700">{{ data.today.schedule_start }} - {{ data.today.schedule_end }} น.</p>
              </div>
              <span class="px-2 py-1 bg-blue-500 text-white text-[10px] font-bold rounded-full">วันนี้</span>
            </div>
          </div>

          <!-- All Assigned Shifts -->
          <div v-if="data.today.assigned_shifts?.length" class="mb-4">
            <p class="text-xs font-medium text-gray-500 mb-2">กะที่ได้รับมอบหมาย</p>
            <div class="space-y-2">
              <div v-for="shift in data.today.assigned_shifts" :key="shift.shift_code"
                class="flex items-center justify-between p-2 rounded-lg border transition-colors"
                :class="shift.is_active ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-200 opacity-60'">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full" :class="shift.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                  <div>
                    <p class="text-sm font-bold" :class="shift.is_active ? 'text-emerald-700' : 'text-gray-500'">
                      {{ shift.shift_code }}: {{ shift.start_time?.substring(0, 5) }} - {{ shift.end_time?.substring(0, 5) }} น.
                    </p>
                    <p class="text-[10px]" :class="shift.is_active ? 'text-emerald-500' : 'text-gray-400'">
                      {{ shift.work_hours }} ชม. | {{ shift.is_overnight ? 'ข้ามวัน' : 'ไม่ข้ามวัน' }}
                      <template v-if="shift.start_date || shift.end_date">
                        | {{ shift.start_date ? fmtDate(shift.start_date) : '...' }} - {{ shift.end_date ? fmtDate(shift.end_date) : '...' }}
                      </template>
                    </p>
                  </div>
                </div>
                <span v-if="shift.is_active" class="px-2 py-0.5 bg-emerald-500 text-white text-[10px] font-bold rounded-full">ใช้งานอยู่</span>
                <span v-else class="px-2 py-0.5 bg-gray-300 text-white text-[10px] font-bold rounded-full">ไม่ active</span>
              </div>
            </div>
          </div>

          <!-- No shift assigned -->
          <div v-if="!data.today.today_shift_code && !data.today.assigned_shifts?.length" class="mb-4 p-3 bg-gray-50 rounded-xl border border-gray-200 text-center">
            <p class="text-gray-400 text-sm">ไม่มีข้อมูลกะ</p>
          </div>

          <div v-if="data.today.is_checked_in" class="space-y-4">
            <!-- Check-in/out times -->
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-200">
                <p class="text-emerald-600 text-xs font-medium mb-1">เข้างาน</p>
                <p class="text-2xl font-bold text-emerald-700">{{ data.today.check_in?.substring(0, 5) || '-' }}</p>
                <p class="text-[10px] text-emerald-500 mt-1">{{ formatDateThai(data.today.date) }}</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-200">
                <p class="text-gray-500 text-xs font-medium mb-1">ออกงาน</p>
                <p class="text-2xl font-bold" :class="data.today.is_checked_out ? 'text-blue-700' : 'text-gray-300'">
                  {{ data.today.check_out?.substring(0, 5) || 'ยังไม่ออก' }}
                </p>
                <p v-if="data.today.is_checked_out" class="text-[10px] text-gray-400 mt-1">{{ formatDateThai(data.today.date) }}</p>
              </div>
            </div>

            <!-- Worked Hours -->
            <div v-if="data.today.worked_hours !== null" class="text-center p-3 bg-cyan-50 rounded-xl border border-cyan-200">
              <p class="text-cyan-600 text-xs font-medium mb-1">ชั่วโมงทำงานวันนี้</p>
              <p class="text-2xl font-bold text-cyan-700">{{ data.today.worked_hours }} ชม.</p>
            </div>
            <!-- Status -->
            <div class="text-center">
              <span v-if="data.today.status === 'on_time'"
                class="inline-flex items-center gap-1 px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full text-sm font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                ปกติ
              </span>
              <span v-else-if="data.today.status === 'late'"
                class="inline-flex items-center gap-1 px-4 py-2 bg-amber-100 text-amber-700 rounded-full text-sm font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                สาย {{ data.today.late_minutes }} นาที
              </span>
              <span v-else
                class="inline-flex items-center gap-1 px-4 py-2 bg-gray-100 text-gray-500 rounded-full text-sm font-bold">
                ยังไม่ได้เข้างาน
              </span>
            </div>
          </div>
          <div v-else class="text-center py-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-3">
              <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <p class="text-gray-400 text-sm">ยังไม่ได้เข้างานวันนี้</p>
            <router-link to="/employee" class="mt-3 inline-block bg-blue-500 text-white px-6 py-2 rounded-xl text-sm font-bold hover:bg-blue-600">
              ไปสแกนเข้างาน
            </router-link>
          </div>
        </div>

        <!-- Monthly Summary -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <button @click="prevMonth" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <div class="text-center">
              <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide">สรุปเดือน</h2>
              <p class="text-base font-bold text-gray-700">{{ thMonths[statMonth - 1] }} {{ statYear + 543 }}</p>
            </div>
            <button @click="nextMonth" :disabled="isCurrentMonth" :class="['w-8 h-8 rounded-full flex items-center justify-center transition-colors', isCurrentMonth ? 'bg-gray-50 text-gray-300 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200 text-gray-600']">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="text-center p-3 bg-emerald-50 rounded-xl border border-emerald-200">
              <p class="text-2xl font-bold text-emerald-600">{{ data.month.working_days }}</p>
              <p class="text-[10px] text-emerald-600 font-medium mt-0.5">วันที่เข้างาน</p>
            </div>
            <div class="text-center p-3 bg-blue-50 rounded-xl border border-blue-200">
              <p class="text-2xl font-bold text-blue-600">{{ data.month.on_time }}</p>
              <p class="text-[10px] text-blue-600 font-medium mt-0.5">เข้างานตรงเวลา</p>
            </div>
            <div class="text-center p-3 bg-amber-50 rounded-xl border border-amber-200">
              <p class="text-2xl font-bold text-amber-600">{{ data.month.late }}</p>
              <p class="text-[10px] text-amber-600 font-medium mt-0.5">สาย</p>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-xl border border-red-200">
              <p class="text-2xl font-bold text-red-600">{{ data.month.absent }}</p>
              <p class="text-[10px] text-red-600 font-medium mt-0.5">ขาดงาน</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-xl border border-purple-200">
              <p class="text-2xl font-bold text-purple-600">{{ data.month.leave_days }}</p>
              <p class="text-[10px] text-purple-600 font-medium mt-0.5">วันลา</p>
            </div>
            <div v-if="hasOt" class="text-center p-3 bg-cyan-50 rounded-xl border border-cyan-200">
              <p class="text-2xl font-bold text-cyan-600">{{ data.month.ot_hours }}</p>
              <p class="text-[10px] text-cyan-600 font-medium mt-0.5">ชม. OT</p>
            </div>
          </div>
          <!-- Late minutes -->
          <div v-if="data.month.total_late_minutes > 0" class="mt-3 text-center text-xs text-amber-600">
            สายรวม {{ data.month.total_late_minutes }} นาที
          </div>
        </div>

        <!-- Pending Requests -->
        <div v-if="data.pending.leave + data.pending.ot + data.pending.wfh > 0" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">รออนุมัติ</h2>
          <div class="flex gap-3">
            <router-link v-if="data.pending.leave > 0" to="/employee/leave"
              class="flex-1 bg-amber-50 rounded-xl p-3 text-center border border-amber-200 hover:bg-amber-100 transition-colors">
              <p class="text-xl font-bold text-amber-600">{{ data.pending.leave }}</p>
              <p class="text-[10px] text-amber-600 font-medium">ลางาน</p>
            </router-link>
            <router-link v-if="hasOt && data.pending.ot > 0" to="/employee/ot"
              class="flex-1 bg-amber-50 rounded-xl p-3 text-center border border-amber-200 hover:bg-amber-100 transition-colors">
              <p class="text-xl font-bold text-amber-600">{{ data.pending.ot }}</p>
              <p class="text-[10px] text-amber-600 font-medium">โอที</p>
            </router-link>
            <router-link v-if="data.pending.wfh > 0" to="/employee/wfh"
              class="flex-1 bg-amber-50 rounded-xl p-3 text-center border border-amber-200 hover:bg-amber-100 transition-colors">
              <p class="text-xl font-bold text-amber-600">{{ data.pending.wfh }}</p>
              <p class="text-[10px] text-amber-600 font-medium">WFH</p>
            </router-link>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 gap-3">
          <router-link to="/employee" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm text-center hover:shadow-md transition-shadow">
            <div class="w-10 h-10 mx-auto rounded-xl bg-indigo-500 flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <p class="font-bold text-gray-800 text-sm">สแกนเข้า/ออก</p>
          </router-link>
          <router-link to="/employee/history" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm text-center hover:shadow-md transition-shadow">
            <div class="w-10 h-10 mx-auto rounded-xl bg-cyan-500 flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <p class="font-bold text-gray-800 text-sm">ประวัติเข้างาน</p>
          </router-link>
          <router-link to="/employee/payslip" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm text-center hover:shadow-md transition-shadow">
            <div class="w-10 h-10 mx-auto rounded-xl bg-green-500 flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
              </svg>
            </div>
            <p class="font-bold text-gray-800 text-sm">สลิปเงินเดือน</p>
          </router-link>
          <router-link to="/employee/leave" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm text-center hover:shadow-md transition-shadow">
            <div class="w-10 h-10 mx-auto rounded-xl bg-blue-500 flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <p class="font-bold text-gray-800 text-sm">ขอลางาน</p>
          </router-link>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import state from '../../store'

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const loading = ref(true)
const error = ref(null)
const hasOt = computed(() => state.user?.has_ot === true || state.user?.has_ot === 1)
const now = new Date()
const statMonth = ref(now.getMonth() + 1)
const statYear = ref(now.getFullYear())
const data = ref({
  today: { check_in: null, check_out: null, status: null, late_minutes: null, is_checked_in: false, is_checked_out: false, schedule_start: null, schedule_end: null, today_shift_code: null, assigned_shifts: [], worked_hours: null },
  month: { working_days: 0, on_time: 0, late: 0, absent: 0, leave_days: 0, total_late_minutes: 0, ot_hours: 0 },
  pending: { leave: 0, ot: 0, wfh: 0 },
})

const isCurrentMonth = computed(() => {
  const c = new Date()
  return statMonth.value === c.getMonth() + 1 && statYear.value === c.getFullYear()
})

const todayThai = computed(() => {
  const d = new Date()
  const days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์']
  return `${days[d.getDay()]}ที่ ${d.getDate()} ${thMonths[d.getMonth()]} ${d.getFullYear() + 543}`
})

function formatDateThai(dateStr) {
  if (!dateStr) return ''
  const parts = String(dateStr).split('T')[0].split('-')
  const year = parseInt(parts[0]) + 543
  const month = thMonths[parseInt(parts[1]) - 1]
  const day = parseInt(parts[2])
  return `${day} ${month} ${year}`
}

function fmtDate(d) {
  if (!d) return ''
  const parts = String(d).split('T')[0].split('-')
  return `${parseInt(parts[2])}/${parseInt(parts[1])}`
}

async function fetchData() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/api/employee/dashboard', {
      params: { month: statMonth.value, year: statYear.value }
    })
    if (res.data.success) {
      data.value = res.data.data
      if (res.data.data.month?.month) statMonth.value = res.data.data.month.month
      if (res.data.data.month?.year) statYear.value = res.data.data.month.year
    } else {
      error.value = res.data.message || 'ไม่สามารถโหลดข้อมูลได้'
    }
  } catch (e) {
    if (e.response?.status === 401) {
      error.value = 'กรุณาเข้าสู่ระบบใหม่'
    } else {
      error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'
    }
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
  fetchData()
}

function nextMonth() {
  if (isCurrentMonth.value) return
  if (statMonth.value === 12) {
    statMonth.value = 1
    statYear.value++
  } else {
    statMonth.value++
  }
  fetchData()
}

onMounted(() => fetchData())
</script>
