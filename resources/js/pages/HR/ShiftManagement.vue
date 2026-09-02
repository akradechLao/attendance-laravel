<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const shifts = ref([])
const employees = ref([])
const companies = ref([])
const showForm = ref(false)
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedCompany = ref('')
const searchQuery = ref('')
const newShift = ref({
  emp_id: '',
  work_date: '',
  start_time: '08:00',
  end_time: '17:00',
  shift_code: 'Full Day',
})

onMounted(async () => {
  await loadShifts()
  await loadEmployees()
  await loadCompanies()
})

const loadShifts = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/shift-schedules', {
      params: { month: selectedMonth.value }
    })
    shifts.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load shifts:', error)
  } finally {
    loading.value = false
  }
}

const loadEmployees = async () => {
  try {
    const params = { shift_only: true, per_page: 9999 }
    if (selectedCompany.value) params.company_id = selectedCompany.value
    if (searchQuery.value) params.search = searchQuery.value
    const response = await api.get('/api/employees', { params })
    employees.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load employees:', error)
  }
}

const loadCompanies = async () => {
  try {
    const response = await api.get('/api/companies')
    companies.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load companies:', error)
  }
}

let empSearchTimeout = null
function debounceLoadEmployees() {
  clearTimeout(empSearchTimeout)
  empSearchTimeout = setTimeout(() => loadEmployees(), 300)
}

const addShift = async () => {
  if (!newShift.value.emp_id || !newShift.value.work_date) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }

  try {
    await api.post('/api/shift-schedules', {
      ...newShift.value,
    })
    alert('เพิ่มกะสำเร็จ')
    showForm.value = false
    newShift.value = { emp_id: '', work_date: '', start_time: '08:00', end_time: '17:00', shift_code: 'Full Day' }
    selectedCompany.value = ''
    searchQuery.value = ''
    await loadShifts()
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการเพิ่มกะ')
  }
}

const deleteShift = async (id) => {
  if (confirm('คุณต้องการลบนี้ใช่หรือไม่?')) {
    try {
      await api.delete(`/api/shift-schedules/${id}`)
      alert('ลบกะสำเร็จ')
      await loadShifts()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการลบกะ')
    }
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Shift Management</h1>
        <p class="text-gray-500">จัดการกะทำงาน</p>
      </div>
      <div class="flex gap-3">
        <input v-model="selectedMonth" type="month" @change="loadShifts" class="px-3 py-2 border rounded-lg" />
        <button @click="showForm = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          + เพิ่มกะ
        </button>
      </div>
    </div>

    <!-- Shift List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">พนักงาน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลา</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รหัสกะ</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="shift in shifts" :key="shift.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ shift.employee?.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ shift.date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ shift.start_time }} - {{ shift.end_time }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ shift.shift_code }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button @click="deleteShift(shift.id)" class="text-red-600 hover:text-red-800">ลบ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Shift Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">เพิ่มกะ</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">บริษัท</label>
            <select v-model="selectedCompany" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @change="loadEmployees">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">ค้นหาพนักงาน</label>
            <input v-model="searchQuery" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="ชื่อหรือรหัส..." @input="debounceLoadEmployees" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">พนักงาน (เฉพาะเข้ากะ)</label>
            <select v-model="newShift.emp_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">เลือกพนักงาน</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.employee_code }} - {{ emp.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่</label>
            <input v-model="newShift.work_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">เวลาเริ่ม</label>
              <input v-model="newShift.start_time" type="time" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">เวลาสิ้นสุด</label>
              <input v-model="newShift.end_time" type="time" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">รหัสกะ</label>
            <select v-model="newShift.shift_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="Full Day">Full Day</option>
              <option value="Half Day">Half Day</option>
              <option value="Night">Night</option>
              <option value="วันหยุด">วันหยุด</option>
            </select>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="showForm = false; selectedCompany = ''; searchQuery = ''" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
            <button @click="addShift" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">เพิ่ม</button>
          </div>
        </div>
      </div>
    </div>
    </div>
  </AppLayout>
</template>
