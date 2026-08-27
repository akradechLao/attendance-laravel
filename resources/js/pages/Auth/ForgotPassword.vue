<template>
  <div class="min-h-screen flex items-center justify-center p-4 bg-gray-50">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-3xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">ลืมรหัสผ่าน</h2>
        <p class="text-gray-500 text-center text-sm mb-6">กรอกข้อมูลเพื่อรีเซ็ตรหัสผ่าน</p>

        <div v-if="step === 1">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทผู้ใช้</label>
            <div class="flex gap-3">
              <button @click="form.user_type = 'admin'" :class="form.user_type === 'admin' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="flex-1 py-2.5 rounded-xl font-medium transition-all">ผู้ดูแลระบบ</button>
              <button @click="form.user_type = 'employee'" :class="form.user_type === 'employee' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="flex-1 py-2.5 rounded-xl font-medium transition-all">พนักงาน</button>
            </div>
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ form.user_type === 'admin' ? 'ชื่อผู้ใช้' : 'รหัสพนักงาน' }}</label>
            <input v-model="form.username" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" :placeholder="form.user_type === 'admin' ? 'กรอกชื่อผู้ใช้' : 'กรอกรหัสพนักงาน'" required />
          </div>
          <button @click="handleRequest" :disabled="loading" class="w-full py-3 bg-gradient-to-r from-blue-800 to-blue-600 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50">
            {{ loading ? 'กำลังส่ง...' : 'ส่งรหัสรีเซ็ต' }}
          </button>
        </div>

        <div v-if="step === 2">
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 text-center">
            <p class="text-green-700 font-medium">รหัสรีเซ็ตของคุณคือ</p>
            <p class="text-3xl font-bold text-green-800 mt-2 tracking-widest">{{ resetToken }}</p>
            <p class="text-green-600 text-sm mt-2">กรอกรหัสนี้ด้านล่างเพื่อตั้งรหัสผ่านใหม่</p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">รหัสรีเซ็ต</label>
            <input v-model="form.token" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all tracking-widest text-center text-lg" placeholder="กรอกรหัสรีเซ็ต" required />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่านใหม่</label>
            <input v-model="form.new_password" type="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 4 ตัว)" required />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">ยืนยันรหัสผ่าน</label>
            <input v-model="form.new_password_confirmation" type="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none transition-all" placeholder="กรอกรหัสผ่านอีกครั้ง" required />
          </div>
          <button @click="handleReset" :disabled="loading" class="w-full py-3 bg-gradient-to-r from-green-700 to-green-600 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50">
            {{ loading ? 'กำลังเปลี่ยน...' : 'เปลี่ยนรหัสผ่าน' }}
          </button>
        </div>

        <div v-if="step === 3" class="text-center py-6">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-2">เปลี่ยนรหัสผ่านสำเร็จ!</h3>
          <p class="text-gray-500 mb-6">กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่</p>
          <router-link to="/login" class="inline-block px-8 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-all">เข้าสู่ระบบ</router-link>
        </div>

        <div v-if="error" class="mt-4 bg-red-50 border border-red-200 rounded-xl p-3 text-center">
          <p class="text-red-600 text-sm">{{ error }}</p>
        </div>

        <div class="mt-6 text-center">
          <router-link to="/login" class="text-gray-500 hover:text-gray-700 text-sm">← กลับหน้าเข้าสู่ระบบ</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

const step = ref(1)
const loading = ref(false)
const error = ref('')
const resetToken = ref('')

const form = reactive({
  username: '',
  user_type: 'admin',
  token: '',
  new_password: '',
  new_password_confirmation: ''
})

async function handleRequest() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/auth/forgot-password', {
      username: form.username,
      user_type: form.user_type
    })
    resetToken.value = res.data.token
    step.value = 2
  } catch (err) {
    error.value = err.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

async function handleReset() {
  loading.value = true
  error.value = ''
  try {
    await axios.post('/api/auth/reset-password', {
      username: form.username,
      token: form.token,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation,
      user_type: form.user_type
    })
    step.value = 3
  } catch (err) {
    error.value = err.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
