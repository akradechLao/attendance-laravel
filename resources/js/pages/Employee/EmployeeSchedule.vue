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
import dayjs from 'dayjs'
import 'dayjs/locale/th'

dayjs.locale('th')

const loading = ref(true)
const schedules = ref([])
const currentWeekStart = ref(dayjs().startOf('week'))

const startDate = computed(() => currentWeekStart.value.format('DD MMM'))
const endDate = computed(() => currentWeekStart.value.add(6, 'day').format('DD MMM YYYY'))
const weekLabel = computed(() => {
  const start = currentWeekStart.value
  const end = start.add(6, 'day')
  return `สัปดาห์ที่ ${start.format('W')} / ${end.year() + 543}`
})

const weekDays = computed(() => {
  const days = []
  const today = dayjs().format('YYYY-MM-DD')
  for (let i = 0; i < 7; i++) {
    const date = currentWeekStart.value.add(i, 'day')
    const dateStr = date.format('YYYY-MM-DD')
    const schedule = schedules.value.find(s => s.date === dateStr)
    days.push({
      date: dateStr,
      dayNum: date.format('D'),
      dayName: date.format('ddd'),
      fullDate: date.format('dddd D MMMM YYYY'),
      isToday: dateStr === today,
      schedule: schedule || null,
    })
  }
  return days
})

function prevWeek() {
  currentWeekStart.value = currentWeekStart.value.subtract(7, 'day')
  loadSchedule()
}

function nextWeek() {
  currentWeekStart.value = currentWeekStart.value.add(7, 'day')
  loadSchedule()
}

function goToday() {
  currentWeekStart.value = dayjs().startOf('week')
  loadSchedule()
}

async function loadSchedule() {
  loading.value = true
  try {
    const res = await axios.get('/api/employee/schedule', {
      params: {
        start_date: currentWeekStart.value.format('YYYY-MM-DD'),
        end_date: currentWeekStart.value.add(6, 'day').format('YYYY-MM-DD'),
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
