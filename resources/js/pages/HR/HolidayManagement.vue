<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const holidays = ref([])
const showForm = ref(false)
const selectedYear = ref(new Date().getFullYear())
const newHoliday = ref({
  name: '',
  date: '',
})

const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']

onMounted(async () => {
  await loadHolidays()
})

const loadHolidays = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/holidays', {
      params: { year: selectedYear.value }
    })
    holidays.value = response.data.data
  } catch (error) {
    console.error('Failed to load holidays:', error)
  } finally {
    loading.value = false
  }
}

const addHoliday = async () => {
  if (!newHoliday.value.name || !newHoliday.value.date) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }

  try {
    await api.post('/api/holidays', {
      ...newHoliday.value,
    })
    alert('เพิ่มวันหยุดสำเร็จ')
    showForm.value = false
    newHoliday.value = { name: '', date: '' }
    await loadHolidays()
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการเพิ่มวันหยุด')
  }
}

const deleteHoliday = async (id) => {
  if (confirm('คุณต้องการลบวันหยุดนี้ใช่หรือไม่?')) {
    try {
      await api.delete(`/api/holidays/${id}`)
      alert('ลบวันหยุดสำเร็จ')
      await loadHolidays()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการลบวันหยุด')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Holiday Management</h1>
        <p class="text-gray-500">จัดการวันหยุดราชการ</p>
      </div>
      <div class="flex gap-3">
        <select v-model="selectedYear" @change="loadHolidays" class="px-3 py-2 border rounded-lg">
          <option v-for="year in [2024, 2025, 2026]" :key="year" :value="year">{{ year }}</option>
        </select>
        <button @click="showForm = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          + เพิ่มวันหยุด
        </button>
      </div>
    </div>

    <!-- Holiday List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เดือน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อวันหยุด</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="holiday in holidays" :key="holiday.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ holiday.date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ months[new Date(holiday.date).getMonth()] }}</td>
            <td class="px-6 py-4 text-sm text-gray-900">{{ holiday.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button @click="deleteHoliday(holiday.id)" class="text-red-600 hover:text-red-800">ลบ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Holiday Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">เพิ่มวันหยุด</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">ชื่อวันหยุด</label>
            <input v-model="newHoliday.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="เช่น วันขึ้นปีใหม่" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่</label>
            <input v-model="newHoliday.date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
            <button @click="addHoliday" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">เพิ่ม</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
