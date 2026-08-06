<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const employees = ref([])
const selectedEmployee = ref('')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const photos = ref([])

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

const loadPhotos = async () => {
  if (!selectedEmployee.value || !selectedMonth.value) {
    alert('กรุณาเลือกพนักงานและเดือน')
    return
  }

  loading.value = true
  try {
    const response = await api.get('/api/photos', {
      params: {
        emp_id: selectedEmployee.value,
        month: selectedMonth.value,
      }
    })
    photos.value = response.data.data
  } catch (error) {
    console.error('Failed to load photos:', error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Photo History</h1>
        <p class="text-gray-500">ประวัติภาพถ่าย</p>
      </div>
      <div class="flex gap-3">
        <select v-model="selectedEmployee" class="px-3 py-2 border rounded-lg">
          <option value="">เลือกพนักงาน</option>
          <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
        </select>
        <input v-model="selectedMonth" type="month" class="px-3 py-2 border rounded-lg" />
        <button @click="loadPhotos" :disabled="loading" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {{ loading ? 'กำลังโหลด...' : 'แสดงรูปภาพ' }}
        </button>
      </div>
    </div>

    <!-- Photo Grid -->
    <div v-if="photos.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="photo in photos" :key="photo.id" class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <img :src="photo.path" :alt="photo.type" class="w-full h-48 object-cover" />
        <div class="p-3">
          <div class="text-sm text-gray-500">{{ photo.date }}</div>
          <div class="text-xs text-gray-400">{{ photo.type === 'check-in' ? 'เข้างาน' : 'ออกงาน' }}</div>
        </div>
      </div>
    </div>

    <!-- No Photos -->
    <div v-else-if="!loading && selectedEmployee" class="bg-white rounded-xl shadow-sm border p-12 text-center">
      <div class="text-gray-400">ไม่พบรูปภาพ</div>
    </div>
  </div>
</template>
