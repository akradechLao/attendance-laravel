<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const leaveRequests = ref([])

onMounted(async () => {
  await loadLeaveRequests()
})

const loadLeaveRequests = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/leave-requests', {
      params: { status: 'pending' }
    })
    leaveRequests.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load leave requests:', error)
  } finally {
    loading.value = false
  }
}

const approveLeave = async (id) => {
  if (confirm('คุณต้องการอนุมัติคำขอนี้ใช่หรือไม่?')) {
    try {
      await api.post(`/api/leave-requests/${id}/approve`)
      alert('อนุมัติคำขอสำเร็จ')
      await loadLeaveRequests()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการอนุมัติคำขอ')
    }
  }
}

const rejectLeave = async (id) => {
  const reason = prompt('กรุณาระบุเหตุผลในการไม่อนุมัติ:')
  if (reason) {
    try {
      await api.post(`/api/leave-requests/${id}/reject`, { reason })
      alert('ไม่อนุมัติคำขอสำเร็จ')
      await loadLeaveRequests()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการไม่อนุมัติคำขอ')
    }
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-navy">อนุมัติลา</h1>
      <p class="text-gray-500">อนุมัติคำขอลา</p>
    </div>

    <!-- Leave Requests -->
    <div class="card overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">พนักงาน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ประเภทลางาน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เหตุผล</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="request in leaveRequests" :key="request.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ request.employee?.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ request.leaveType?.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ request.start_date }} - {{ request.end_date }}</td>
            <td class="px-6 py-4 text-sm text-gray-900">{{ request.reason }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button @click="approveLeave(request.id)" class="text-green-600 hover:text-green-800 mr-3">อนุมัติ</button>
              <button @click="rejectLeave(request.id)" class="text-red-600 hover:text-red-800">ไม่อนุมัติ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </AppLayout>
</template>
