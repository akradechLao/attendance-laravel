<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const settings = ref({
  enable_face_recognition: true,
  face_recognition_threshold: 0.6,
  enable_remote_scan: true,
  enable_telegram: false,
  telegram_bot_token: '',
  telegram_chat_id: '',
  backup_enabled: false,
  backup_frequency: 'daily',
})

onMounted(async () => {
  await loadSettings()
})

const loadSettings = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/system-settings')
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
    await api.put('/api/system-settings', settings.value)
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
      <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
      <p class="text-gray-500">ตั้งค่าระบบ</p>
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
      <div class="space-y-6">
        <!-- Face Recognition Settings -->
        <div class="border-b pb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Face Recognition</h3>
          <div class="flex items-center gap-3">
            <input v-model="settings.enable_face_recognition" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <label class="text-sm text-gray-700">เปิดใช้งาน Face Recognition</label>
          </div>
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Face Recognition Threshold (0-1)</label>
            <input v-model="settings.face_recognition_threshold" type="number" step="0.1" min="0" max="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
        </div>

        <!-- Remote Scan Settings -->
        <div class="border-b pb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Remote Scan</h3>
          <div class="flex items-center gap-3">
            <input v-model="settings.enable_remote_scan" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <label class="text-sm text-gray-700">เปิดใช้งาน Remote Scan</label>
          </div>
        </div>

        <!-- Telegram Settings -->
        <div class="border-b pb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Telegram</h3>
          <div class="flex items-center gap-3">
            <input v-model="settings.enable_telegram" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <label class="text-sm text-gray-700">เปิดใช้งาน Telegram</label>
          </div>
          <div v-if="settings.enable_telegram" class="mt-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Bot Token</label>
              <input v-model="settings.telegram_bot_token" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Chat ID</label>
              <input v-model="settings.telegram_chat_id" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
            </div>
          </div>
        </div>

        <!-- Backup Settings -->
        <div>
          <h3 class="text-lg font-medium text-gray-900 mb-4">Backup</h3>
          <div class="flex items-center gap-3">
            <input v-model="settings.backup_enabled" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <label class="text-sm text-gray-700">เปิดใช้งาน Backup อัตโนมัติ</label>
          </div>
          <div v-if="settings.backup_enabled" class="mt-4">
            <label class="block text-sm font-medium text-gray-700">ความถี่</label>
            <select v-model="settings.backup_frequency" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="daily">รายวัน</option>
              <option value="weekly">รายสัปดาห์</option>
              <option value="monthly">รายเดือน</option>
            </select>
          </div>
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
