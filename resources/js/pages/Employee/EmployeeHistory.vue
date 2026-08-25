<script setup>
import { ref, onMounted } from 'vue'
import store from '../../store'
import api from '@/services/api'

const loading = ref(false)
const attendanceHistory = ref([])
const leaveHistory = ref([])
const selectedTab = ref('attendance')

onMounted(async () => {
  await loadHistory()
})

const loadHistory = async () => {
  loading.value = true
  try {
    const response = await api.get('/employee/attendance/history')
    attendanceHistory.value = response.data.data?.attendance || []
    leaveHistory.value = response.data.data?.leave || []
  } catch (error) {
    console.error('Failed to load history:', error)
  } finally {
    loading.value = false
  }
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
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">เข้า</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ออก</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">สาย</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="record in attendanceHistory" :key="record.id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.date }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.check_in || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.check_out || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                <span v-if="record.late_minutes > 0" class="text-amber-600 font-medium">{{ record.late_minutes }} นาที</span>
                <span v-else class="text-gray-300">-</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">
                <span :class="record.status === 'late' ? 'text-amber-600' : record.status === 'on_time' ? 'text-emerald-600' : 'text-gray-500'" class="font-medium">
                  {{ record.status === 'on_time' ? 'ปกติ' : record.status === 'late' ? 'สาย' : record.status || '-' }}
                </span>
              </td>
            </tr>
            <tr v-if="attendanceHistory.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">ไม่มีข้อมูล</td>
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
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.leaveType?.name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ record.start_date }} - {{ record.end_date }}</td>
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
