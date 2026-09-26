<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const wfhRecords = ref([])
const loading = ref(false)

onMounted(async () => {
  await loadWfhRecords()
})

const loadWfhRecords = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/wfh')
    wfhRecords.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load records:', error)
  } finally {
    loading.value = false
  }
}

const getStatusBadge = (status) => {
  const badges = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancel_requested: 'bg-orange-100 text-orange-800',
    change_requested: 'bg-purple-100 text-purple-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}

const statusLabel = (status) => {
  const labels = {
    pending: 'รออนุมัติ',
    approved: 'อนุมัติ',
    rejected: 'ไม่อนุมัติ',
    cancel_requested: 'ขอยกเลิกรออนุมัติ',
    change_requested: 'ขอเปลี่ยนรออนุมัติ',
  }
  return labels[status] || status
}
</script>

<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">ปฏิบัติงานนอกสถานที่</h1>
        <p class="text-gray-500">รายการ WFH ของพนักงานทั้งหมด</p>
      </div>
    </div>

    <!-- WFH Records -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <div v-if="loading" class="p-6 text-center text-gray-500">กำลังโหลด...</div>
      <table v-else class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">พนักงาน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เหตุผล</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-if="wfhRecords.length === 0">
            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">ไม่มีรายการ</td>
          </tr>
          <tr v-for="record in wfhRecords" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              {{ record.employee?.name || record.emp_id }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              {{ record.date }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-900">{{ record.reason }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['px-2 py-1 rounded-full text-xs', getStatusBadge(record.status)]">
                {{ statusLabel(record.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </AppLayout>
</template>
