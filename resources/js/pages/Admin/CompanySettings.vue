<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const settings = ref({
  company_name: '',
  work_start_time: '08:30',
  work_end_time: '17:30',
  late_threshold: 15,
  location_radius: 200,
  enable_face_recognition: true,
  enable_remote_scan: true,
})

onMounted(async () => {
  await loadSettings()
})

const loadSettings = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/company-settings')
    settings.value = response.data.data?.data || response.data.data || {}
  } catch (error) {
    console.error('Failed to load settings:', error)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  loading.value = true
  try {
    await api.put('/api/company-settings', settings.value)
    alert('บันทึกการตั้งค่าสำเร็จ')
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการบันทึกการตั้งค่า')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Company Settings</h1>
      <p class="text-gray-500">ตั้งค่าบริษัท</p>
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
      <div class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">ชื่อบริษัท</label>
          <input v-model="settings.company_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">เวลาเข้างาน</label>
            <input v-model="settings.work_start_time" type="time" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">เวลาเลิกงาน</label>
            <input v-model="settings.work_end_time" type="time" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">กำหนดสาย (นาที)</label>
          <input v-model="settings.late_threshold" type="number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">รัศมีแผนที่ (เมตร)</label>
          <input v-model="settings.location_radius" type="number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>

        <div class="flex items-center gap-3">
          <input v-model="settings.enable_face_recognition" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
          <label class="text-sm text-gray-700">เปิดใช้งาน Face Recognition</label>
        </div>

        <div class="flex items-center gap-3">
          <input v-model="settings.enable_remote_scan" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
          <label class="text-sm text-gray-700">เปิดใช้งาน Remote Scan</label>
        </div>

        <div class="flex justify-end">
          <button @click="saveSettings" :disabled="loading" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'กำลังบันทึก...' : 'บันทึก' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
