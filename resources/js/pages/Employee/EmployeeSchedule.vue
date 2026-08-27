<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ตารางเวร</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <!-- Week Navigation -->
      <div class="flex items-center justify-between mb-4 bg-white rounded-xl p-3 border border-gray-200 shadow-sm">
        <button @click="prevWeek" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">{{ weekLabel }}</p>
          <p class="text-gray-400 text-xs">{{ startDate }} - {{ endDate }}</p>
        </div>
        <button @click="nextWeek" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <!-- Today Button -->
      <div class="text-center mb-4">
        <button @click="goToday" class="px-4 py-1.5 bg-blue-500 text-white text-xs font-medium rounded-full hover:bg-blue-600 transition-colors">
          วันนี้
        </button>
      </div>

      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else class="space-y-2">
        <div v-for="day in weekDays" :key="day.date"
          :class="day.isToday ? 'ring-2 ring-blue-400 bg-blue-50' : 'bg-white'"
          class="rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div :class="day.isToday ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'"
              class="w-12 h-12 rounded-xl flex flex-col items-center justify-center">
              <span class="text-lg font-bold leading-none">{{ day.dayNum }}</span>
              <span class="text-[10px] uppercase">{{ day.dayName }}</span>
            </div>
            <div>
              <p class="font-medium text-gray-800 text-sm">{{ day.fullDate }}</p>
              <p v-if="day.schedule" class="text-blue-600 text-xs font-medium mt-0.5">
                {{ day.schedule.shift_code }}
                <span v-if="day.schedule.start_time" class="text-gray-500"> {{ day.schedule.start_time?.substring(0, 5) }}-{{ day.schedule.end_time?.substring(0, 5) }}</span>
                <span v-if="day.schedule.day_type" class="text-gray-400">({{ day.schedule.day_type }})</span>
              </p>
              <p v-else class="text-gray-400 text-xs mt-0.5">-</p>
            </div>
          </div>
          <div v-if="day.isHoliday" class="text-xs font-medium text-red-500 bg-red-50 px-2 py-1 rounded-lg">
            {{ day.isHoliday }}
          </div>
        </div>
      </div>

      <div v-if="!loading && schedules.length === 0" class="text-center py-12">
        <p class="text-gray-400 text-sm">ไม่มีข้อมูลตารางเวรในสัปดาห์นี้</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const thDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์']
const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

function toDateStr(d) { return d.toISOString().split('T')[0] }
function fmtNum(n) { return String(n).padStart(2, '0') }

function startOfWeek(d) {
  const r = new Date(d)
  r.setDate(r.getDate() - r.getDay())
  r.setHours(0,0,0,0)
  return r
}
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r }
function fmtFull(d) { return `${thDays[d.getDay()]} ${d.getDate()} ${thMonths[d.getMonth()]} ${d.getFullYear() + 543}` }
function fmtShort(d) { return `${fmtNum(d.getDate())} ${thMonths[d.getMonth()]}` }

const loading = ref(true)
const schedules = ref([])
const currentWeekStart = ref(startOfWeek(new Date()))

const startDate = computed(() => fmtShort(currentWeekStart.value))
const endDate = computed(() => fmtShort(addDays(currentWeekStart.value, 6)))
const weekLabel = computed(() => {
  const y = currentWeekStart.value.getFullYear() + 543
  const start = new Date(currentWeekStart.value)
  const endOfYear = new Date(start.getFullYear(), 11, 31)
  const dayOfYear = Math.ceil((endOfYear - start) / 86400000)
  const weekNum = 52 - Math.floor(dayOfYear / 7)
  return `สัปดาห์ที่ ${Math.max(1, weekNum)} / ${y}`
})

const weekDays = computed(() => {
  const days = []
  const today = toDateStr(new Date())
  for (let i = 0; i < 7; i++) {
    const date = addDays(currentWeekStart.value, i)
    const dateStr = toDateStr(date)
    const schedule = schedules.value.find(s => s.date === dateStr)
    days.push({
      date: dateStr,
      dayNum: date.getDate(),
      dayName: thDays[date.getDay()].substring(0, 3),
      fullDate: fmtFull(date),
      isToday: dateStr === today,
      schedule: schedule || null,
    })
  }
  return days
})

function prevWeek() {
  currentWeekStart.value = addDays(currentWeekStart.value, -7)
  loadSchedule()
}

function nextWeek() {
  currentWeekStart.value = addDays(currentWeekStart.value, 7)
  loadSchedule()
}

function goToday() {
  currentWeekStart.value = startOfWeek(new Date())
  loadSchedule()
}

async function loadSchedule() {
  loading.value = true
  try {
    const res = await axios.get('/api/employee/schedule', {
      params: {
        start_date: toDateStr(currentWeekStart.value),
        end_date: toDateStr(addDays(currentWeekStart.value, 6)),
      }
    })
    if (res.data.success) {
      schedules.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadSchedule)
</script>
