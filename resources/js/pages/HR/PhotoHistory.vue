<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../services/api'

const loading = ref(false)
const employees = ref([])
const searchQuery = ref('')
const selectedCompany = ref('')

const companies = [
  { id: 1, name: 'NTC' },
  { id: 2, name: 'ETC1992' },
  { id: 3, name: 'ETECH' },
  { id: 4, name: 'STC' },
]

onMounted(async () => {
  await loadEmployees()
})

const loadEmployees = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/employees', { params: { per_page: 999 } })
    employees.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load employees:', error)
  } finally {
    loading.value = false
  }
}

const filteredEmployees = computed(() => {
  let list = employees.value
  if (selectedCompany.value) {
    list = list.filter(e => e.company_id == selectedCompany.value)
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(e => (e.name || '').toLowerCase().includes(q) || (e.employee_code || '').toLowerCase().includes(q))
  }
  return list
})

const registeredCount = computed(() => filteredEmployees.value.filter(e => e.face_data_count > 0).length)
const registeredPct = computed(() => filteredEmployees.value.length > 0 ? Math.round((registeredCount.value / filteredEmployees.value.length) * 100) : 0)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-navy">สถานะลงทะเบียนใบหน้า</h1>
      <p class="text-gray-500">ตรวจสอบพนักงานที่ลงทะเบียนใบหน้าแล้ว / ยังไม่ลงทะเบียน</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card p-4">
        <div class="text-sm text-gray-500">ทั้งหมด</div>
        <div class="text-2xl font-bold text-navy">{{ filteredEmployees.length }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-green-600">ลงทะเบียนแล้ว</div>
        <div class="text-2xl font-bold text-green-600">{{ registeredCount }} <span class="text-sm font-normal">({{ registeredPct }}%)</span></div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-red-600">ยังไม่ลงทะเบียน</div>
        <div class="text-2xl font-bold text-red-600">{{ notRegisteredCount }}</div>
      </div>
    </div>

    <!-- Filter -->
    <div class="card p-4">
      <div class="flex flex-col sm:flex-row gap-3">
        <input v-model="searchQuery" type="text" class="input-field flex-1" placeholder="ค้นหาชื่อหรือรหัส..." />
        <select v-model="selectedCompany" class="input-field w-auto">
          <option value="">ทุกบริษัท</option>
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

    <!-- Table -->
    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50">
              <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
              <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
              <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
              <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
              <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">จำนวนภาพ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="emp in filteredEmployees" :key="emp.id" class="hover:bg-gray-50">
              <td class="px-6 py-3 font-medium text-navy">{{ emp.name }}</td>
              <td class="px-6 py-3 text-gray-600">{{ emp.employee_code }}</td>
              <td class="px-6 py-3 text-gray-600">{{ emp.company?.name }}</td>
              <td class="px-6 py-3 text-center">
                <span v-if="emp.face_data_count > 0"
                  class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                  ✅ ลงทะเบียนแล้ว
                </span>
                <span v-else
                  class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                  ❌ ยังไม่ลงทะเบียน
                </span>
              </td>
              <td class="px-6 py-3 text-center text-sm">
                {{ emp.face_data_count || 0 }} / 5
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
