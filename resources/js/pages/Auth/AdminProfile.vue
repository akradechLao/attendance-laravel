<template>
  <div class="max-w-lg mx-auto p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">โปรไฟล์ของฉัน</h1>

    <div class="bg-white rounded-2xl shadow-md p-6">
      <div class="text-center mb-6">
        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
        </div>
        <p class="text-gray-500 text-sm">{{ profile.role }}</p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้ใช้</label>
          <input v-model="form.username" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" />
        </div>

        <hr class="my-4" />
        <p class="text-sm text-gray-500 font-medium">เปลี่ยนรหัสผ่าน (กรอกเฉพาะเมื่อต้องการเปลี่ยน)</p>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านเดิม</label>
          <input v-model="form.current_password" type="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" placeholder="กรอกรหัสผ่านเดิม" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
          <input v-model="form.password" type="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 4 ตัว)" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
          <input v-model="form.password_confirmation" type="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" />
        </div>
      </div>

      <div v-if="error" class="mt-4 bg-red-50 border border-red-200 rounded-xl p-3 text-center">
        <p class="text-red-600 text-sm">{{ error }}</p>
      </div>
      <div v-if="success" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-3 text-center">
        <p class="text-green-600 text-sm">{{ success }}</p>
      </div>

      <button @click="handleSave" :disabled="loading" class="mt-6 w-full py-3 bg-gradient-to-r from-blue-800 to-blue-600 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50">
        {{ loading ? 'กำลังบันทึก...' : 'บันทึก' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const error = ref('')
const success = ref('')
const profile = reactive({ id: null, username: '', role: '', company_id: null })

const form = reactive({
  username: '',
  current_password: '',
  password: '',
  password_confirmation: ''
})

onMounted(async () => {
  try {
    const res = await axios.get('/api/auth/profile')
    Object.assign(profile, res.data.data)
    form.username = profile.username
  } catch (e) {
    error.value = 'ไม่สามารถโหลดข้อมูลได้'
  }
})

async function handleSave() {
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    const payload = { username: form.username }
    if (form.password) {
      payload.current_password = form.current_password
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }
    const res = await axios.put('/api/auth/profile', payload)
    Object.assign(profile, res.data.data)
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    success.value = res.data.message
  } catch (err) {
    error.value = err.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
