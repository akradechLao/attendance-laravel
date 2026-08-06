<script setup>
import { ref, onMounted } from 'vue'
import store from '../../store'
import api from '@/services/api'


const showForm = ref(false)
const selectedDate = ref(new Date().toISOString().split('T')[0])
const selectedEndDate = ref('')
const reason = ref('')
const loading = ref(false)
const wfhRecords = ref([])

onMounted(async () => {
  await loadWfhRecords()
})

const loadWfhRecords = async () => {
  try {
    const response = await api.get('/api/wfh-records', {
      params: { emp_id: store.user?.emp_id }
    })
    wfhRecords.value = response.data.data
  } catch (error) {
    console.error('Failed to load WFH records:', error)
  }
}

const submitWfh = async () => {
  if (!selectedDate.value || !reason.value) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }

  loading.value = true
  try {
    await api.post('/api/wfh-records', {
      emp_id: store.user?.emp_id,
      start_date: selectedDate.value,
      end_date: selectedEndDate.value || selectedDate.value,
      reason: reason.value,
    })
    alert('ส่งคำขอ WFH สำเร็จ')
    showForm.value = false
    selectedDate.value = new Date().toISOString().split('T')[0]
    selectedEndDate.value = ''
    reason.value = ''
    await loadWfhRecords()
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการส่งคำขอ WFH')
  } finally {
    loading.value = false
  }
}

const getStatusBadge = (status) => {
  const badges = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Work From Home</h1>
        <p class="text-gray-500">จัดการการลา WFH</p>
      </div>
      <button
        @click="showForm = true"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        + ขอ WFH
      </button>
    </div>

    <!-- WFH Records -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เหตุผล</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="record in wfhRecords" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              {{ record.start_date }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-900">{{ record.reason }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['px-2 py-1 rounded-full text-xs', getStatusBadge(record.status)]">
                {{ record.status === 'pending' ? 'รออนุมัติ' : record.status === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- WFH Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">ขอ WFH</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่เริ่ม</label>
            <input v-model="selectedDate" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่สิ้นสุด</label>
            <input v-model="selectedEndDate" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">เหตุผล</label>
            <textarea v-model="reason" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="กรอกเหตุผล..."></textarea>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
            <button @click="submitWfh" :disabled="loading" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ loading ? 'กำลังส่ง...' : 'ส่งคำขอ' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
