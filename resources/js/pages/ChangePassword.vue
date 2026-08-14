<template>
  <AppLayout>
  <div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-6">เปลี่ยนรหัสผ่าน</h1>
    <div class="bg-white rounded-lg shadow p-6">
      <form @submit.prevent="changePassword">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านปัจจุบัน</label>
            <input v-model="form.current_password" type="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
            <input v-model="form.new_password" type="password" required minlength="6" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
            <input v-model="form.new_password_confirmation" type="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>
          <div v-if="success" class="text-green-600 text-sm">{{ success }}</div>
          <button type="submit" :disabled="loading" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'กำลังเปลี่ยน...' : 'เปลี่ยนรหัสผ่าน' }}
          </button>
        </div>
      </form>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const form = ref({ current_password: '', new_password: '', new_password_confirmation: '' })
const loading = ref(false)
const error = ref('')
const success = ref('')

const changePassword = async () => {
  error.value = ''
  success.value = ''
  if (form.value.new_password !== form.value.new_password_confirmation) {
    error.value = 'รหัสผ่านใหม่ไม่ตรงกัน'
    return
  }
  if (form.value.new_password.length < 6) {
    error.value = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'
    return
  }
  loading.value = true
  try {
    await api.post('/api/permissions/change-password', form.value)
    success.value = 'เปลี่ยนรหัสผ่านสำเร็จ'
    form.value = { current_password: '', new_password: '', new_password_confirmation: '' }
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
