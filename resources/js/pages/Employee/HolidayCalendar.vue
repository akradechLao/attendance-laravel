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

      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else class="space-y-6">
        <!-- Leave Balance Cards -->
        <div>
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">สิทธิลาคงเหลือ</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div v-for="b in balances" :key="b.leave_type_id"
              class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
              <p class="text-gray-400 text-xs font-medium mb-1">{{ b.name }}</p>
              <div class="flex items-end gap-1">
                <span class="text-2xl font-bold" :class="b.remaining > 0 ? 'text-blue-600' : 'text-gray-300'">
                  {{ b.remaining }}
                </span>
                <span class="text-gray-400 text-xs mb-1">วัน</span>
              </div>
              <div class="mt-2 flex gap-2 text-[10px]">
                <span class="text-gray-400">ใช้ไป {{ b.used }}</span>
                <span class="text-gray-400">•</span>
                <span class="text-gray-400">มี {{ b.entitled }}</span>
              </div>
              <div v-if="b.vacation_accumulated > 0" class="mt-1 text-[10px] text-amber-500">
                ลาพักร้อนสะสม +{{ b.vacation_accumulated }} วัน
              </div>
            </div>
          </div>
        </div>

        <!-- Holiday List -->
        <div>
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">วันหยุดประจำปี ({{ year + 543 }})</h2>
          <div v-if="holidays.length === 0" class="bg-white rounded-xl p-6 border border-gray-200 text-center text-gray-400 text-sm">
            ยังไม่มีวันหยุดในปีนี้
          </div>
          <div v-else class="space-y-2">
            <div v-for="h in holidays" :key="h.date"
              :class="isPast(h.date) ? 'opacity-50' : isToday(h.date) ? 'ring-2 ring-blue-400 bg-blue-50' : ''"
              class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div :class="isPast(h.date) ? 'bg-gray-100 text-gray-400' : isToday(h.date) ? 'bg-blue-500 text-white' : 'bg-red-50 text-red-500'"
                  class="w-12 h-12 rounded-xl flex flex-col items-center justify-center">
                  <span class="text-lg font-bold leading-none">{{ dayNum(h.date) }}</span>
                  <span class="text-[10px] uppercase">{{ monthShort(h.date) }}</span>
                </div>
                <div>
                  <p class="font-medium text-gray-800 text-sm">{{ h.name }}</p>
                  <p class="text-gray-400 text-xs">{{ formatFull(h.date) }}</p>
                </div>
              </div>
              <span v-if="isToday(h.date)" class="text-[10px] font-bold bg-blue-500 text-white px-2 py-0.5 rounded-full">วันนี้</span>
              <span v-else-if="isPast(h.date)" class="text-[10px] font-bold bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">ผ่านแล้ว</span>
              <span v-else class="text-[10px] font-bold bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full">อีก {{ daysUntil(h.date) }} วัน</span>
            </div>
          </div>
        </div>

        <!-- My Leave Markers -->
        <div v-if="myLeaves.length > 0">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">วันลาที่ได้รับอนุมัติแล้ว</h2>
          <div class="space-y-2">
            <div v-for="(leave, i) in myLeaves" :key="i"
              class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between">
              <div>
                <p class="font-medium text-gray-800 text-sm">{{ leave.type }}</p>
                <p class="text-gray-400 text-xs">
                  {{ formatFull(leave.start_date) }}
                  <span v-if="leave.start_date !== leave.end_date"> - {{ formatFull(leave.end_date) }}</span>
                </p>
              </div>
              <span class="bg-blue-50 text-blue-600 text-[10px] font-medium px-2 py-1 rounded-full">
                {{ leave.days }} วัน
              </span>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
const thMonthsFull = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']
const thDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์']

const loading = ref(true)
const year = ref(new Date().getFullYear())
const holidays = ref([])
const balances = ref([])
const myLeaves = ref([])

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

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/employee/holidays', { params: { year: year.value } })
    if (res.data.success) {
      holidays.value = res.data.data.holidays
      balances.value = res.data.data.balances
      myLeaves.value = res.data.data.my_leaves
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
