<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ปฏิทินวันหยุด</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <!-- Year Selector -->
      <div class="flex items-center justify-center gap-4 mb-6">
        <button @click="year--" class="p-2 hover:bg-gray-100 rounded-lg">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <span class="font-bold text-gray-800 text-lg">{{ year + 543 }}</span>
        <button @click="year++" class="p-2 hover:bg-gray-100 rounded-lg">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <!-- Type Legend -->
      <div class="flex items-center justify-center gap-4 mb-6 text-xs">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-blue-500"></span>
          <span class="text-gray-500">วันหยุดราชการ</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          <span class="text-gray-500">วันหยุดบริษัท</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-amber-500"></span>
          <span class="text-gray-500">วันหยุดพิเศษ</span>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else class="space-y-6">
        <!-- Holiday List by Month -->
        <div>
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">วันหยุดประจำปี ({{ year + 543 }})</h2>
          <div v-if="holidays.length === 0" class="bg-white rounded-xl p-6 border border-gray-200 text-center text-gray-400 text-sm">
            ยังไม่มีวันหยุดในปีนี้
          </div>
          <div v-else>
            <div v-for="(monthHolidays, monthName) in holidaysByMonth" :key="monthName" class="mb-4">
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 px-1">{{ monthName }}</p>
              <div class="space-y-2">
                <div v-for="h in monthHolidays" :key="h.date"
                  :class="[typeStyle(h.type).card, isPast(h.date) ? 'opacity-50' : '', isToday(h.date) ? 'ring-2 ring-blue-400' : '']"
                  class="rounded-xl p-4 border shadow-sm flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div :class="isToday(h.date) ? 'bg-blue-500 text-white' : typeStyle(h.type).badge"
                      class="w-12 h-12 rounded-xl flex flex-col items-center justify-center">
                      <span class="text-lg font-bold leading-none">{{ dayNum(h.date) }}</span>
                      <span class="text-[10px] uppercase">{{ monthShort(h.date) }}</span>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-800 text-sm">{{ h.name }}</p>
                        <span :class="typeStyle(h.type).tag"
                          class="text-[9px] font-bold px-1.5 py-0.5 rounded-full">
                          {{ typeLabel(h.type) }}
                        </span>
                      </div>
                      <p class="text-gray-400 text-xs">{{ formatFull(h.date) }}</p>
                    </div>
                  </div>
                  <span v-if="isToday(h.date)" class="text-[10px] font-bold bg-blue-500 text-white px-2 py-0.5 rounded-full">วันนี้</span>
                  <span v-else-if="isPast(h.date)" class="text-[10px] font-bold bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">ผ่านแล้ว</span>
                  <span v-else class="text-[10px] font-bold text-gray-400">อีก {{ daysUntil(h.date) }} วัน</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '../../services/api'

const thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
const thMonthsFull = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const loading = ref(true)
const year = ref(new Date().getFullYear())
const holidays = ref([])

const typeStyles = {
  government: {
    card: 'bg-blue-50 border-blue-200',
    badge: 'bg-blue-100 text-blue-600',
    tag: 'bg-blue-100 text-blue-600',
  },
  company: {
    card: 'bg-emerald-50 border-emerald-200',
    badge: 'bg-emerald-100 text-emerald-600',
    tag: 'bg-emerald-100 text-emerald-600',
  },
  special: {
    card: 'bg-amber-50 border-amber-200',
    badge: 'bg-amber-100 text-amber-600',
    tag: 'bg-amber-100 text-amber-600',
  },
}

function typeStyle(t) { return typeStyles[t] || typeStyles.company }
function typeLabel(t) { return { government: 'ราชการ', company: 'บริษัท', special: 'พิเศษ' }[t] || 'บริษัท' }

function dayNum(d) { return new Date(d).getDate() }
function monthShort(d) { return thMonths[new Date(d).getMonth()] }
function isPast(d) { return new Date(d) < new Date(new Date().toDateString()) }
function isToday(d) { return new Date(d).toDateString() === new Date().toDateString() }
function daysUntil(d) {
  const diff = Math.ceil((new Date(d) - new Date()) / 86400000)
  return diff > 0 ? diff : 0
}
function formatFull(d) {
  const dt = new Date(d)
  return `${dt.getDate()} ${thMonthsFull[dt.getMonth()]} ${dt.getFullYear() + 543}`
}

const holidaysByMonth = computed(() => {
  const groups = {}
  for (const h of holidays.value) {
    const dt = new Date(h.date)
    const key = `${thMonthsFull[dt.getMonth()]} ${dt.getFullYear() + 543}`
    if (!groups[key]) groups[key] = []
    groups[key].push(h)
  }
  return groups
})

async function loadData() {
  loading.value = true
  try {
    const res = await api.get('/api/employee/holidays', { params: { year: year.value } })
    if (res.data.success) {
      holidays.value = res.data.data.holidays
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch(year, loadData)
onMounted(loadData)
</script>
