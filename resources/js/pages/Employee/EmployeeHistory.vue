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
    const response = await api.get(`/api/employees/${store.user?.emp_id}/history`)
    attendanceHistory.value = response.data.data.attendance
    leaveHistory.value = response.data.data.leave
  } catch (error) {
    console.error('Failed to load history:', error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">My History</h1>
      <p class="text-gray-500">ประวัติของฉัน</p>
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

    <!-- Attendance History -->
    <div v-if="selectedTab === 'attendance'" class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลาเข้า</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลาออก</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="record in attendanceHistory" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.check_in || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.check_out || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Leave History -->
    <div v-if="selectedTab === 'leave'" class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ประเภทลางาน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เหตุผล</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="record in leaveHistory" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.leaveType?.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.start_date }} - {{ record.end_date }}</td>
            <td class="px-6 py-4 text-sm text-gray-900">{{ record.reason }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
