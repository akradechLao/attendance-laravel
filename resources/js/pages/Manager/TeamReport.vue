<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const employees = ref([])
const selectedEmployee = ref('')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const reportData = ref(null)

onMounted(async () => {
  await loadEmployees()
})

const loadEmployees = async () => {
  try {
    const response = await api.get('/api/employees')
    employees.value = response.data.data
  } catch (error) {
    console.error('Failed to load employees:', error)
  }
}

const loadReport = async () => {
  if (!selectedEmployee.value || !selectedMonth.value) {
    alert('กรุณาเลือกพนักงานและเดือน')
    return
  }

  loading.value = true
  try {
    const response = await api.get('/api/reports/monthly', {
      params: {
        emp_id: selectedEmployee.value,
        month: selectedMonth.value,
      }
    })
    reportData.value = response.data.data
  } catch (error) {
    console.error('Failed to load report:', error)
  } finally {
    loading.value = false
  }
}

const exportReport = async () => {
  try {
    const response = await api.get('/api/reports/monthly/export', {
      params: {
        emp_id: selectedEmployee.value,
        month: selectedMonth.value,
      },
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `report_${selectedMonth.value}.pdf`)
    document.body.appendChild(link)
    link.click()
  } catch (error) {
    console.error('Failed to export report:', error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Team Report</h1>
        <p class="text-gray-500">รายงานทีมงาน (ผู้จัดการ)</p>
      </div>
      <div class="flex gap-3">
        <select v-model="selectedEmployee" class="px-3 py-2 border rounded-lg">
          <option value="">เลือกพนักงาน</option>
          <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
        </select>
        <input v-model="selectedMonth" type="month" class="px-3 py-2 border rounded-lg" />
        <button @click="loadReport" :disabled="loading" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {{ loading ? 'กำลังโหลด...' : 'แสดงรายงาน' }}
        </button>
        <button @click="exportReport" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          Export PDF
        </button>
      </div>
    </div>

    <!-- Report Data -->
    <div v-if="reportData" class="grid grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-xl shadow-sm border">
        <div class="text-sm text-gray-500">วันทำงาน</div>
        <div class="text-2xl font-bold text-gray-900">{{ reportData.working_days }}</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border">
        <div class="text-sm text-gray-500">มาตรงเวลา</div>
        <div class="text-2xl font-bold text-green-600">{{ reportData.on_time }}</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border">
        <div class="text-sm text-gray-500">สาย</div>
        <div class="text-2xl font-bold text-yellow-600">{{ reportData.late }}</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border">
        <div class="text-sm text-gray-500">ลา</div>
        <div class="text-2xl font-bold text-red-600">{{ reportData.leave }}</div>
      </div>
    </div>

    <!-- Report Table -->
    <div v-if="reportData" class="bg-white rounded-xl shadow-sm border overflow-hidden">
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
          <tr v-for="record in reportData.records" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.check_in || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.check_out || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ record.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
