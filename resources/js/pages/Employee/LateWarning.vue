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
import { ref, onMounted } from 'vue'
import axios from 'axios'

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

function formatDate(d) {
  const dt = new Date(d)
  return `${dt.getDate()} ${thMonths[dt.getMonth()]} ${dt.getFullYear() + 543}`
}

onMounted(async () => {
  try {
    const [histRes, warnRes] = await Promise.allSettled([
      axios.get('/api/employee/attendance/history', { params: { limit: 30 } }),
      axios.get('/api/employee/warnings'),
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
