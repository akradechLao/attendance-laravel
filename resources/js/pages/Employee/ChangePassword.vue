<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-md mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">เปลี่ยนรหัสผ่าน</h1>
      </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-6">
      <div v-if="success" class="bg-emerald-50 border border-emerald-200 rounded-xl p-6 mb-6 text-center">
        <svg class="w-12 h-12 text-emerald-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-emerald-700 text-lg font-semibold">เปลี่ยนรหัสผ่านสำเร็จ!</p>
        <p class="text-gray-500 mt-2">กรุณาเข้าสู่ระบบใหม่ด้วยรหัสผ่านใหม่</p>
        <button @click="handleLogout" class="mt-4 px-6 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors">
          เข้าสู่ระบบใหม่
        </button>
      </div>

      <form v-else @submit.prevent="handleSubmit" class="space-y-4">
        <div class="card">
          <label class="block text-sm font-semibold text-navy mb-2">รหัสผ่านเดิม</label>
          <input v-model="form.current_password" type="password" class="input-field" placeholder="กรอกรหัสผ่านเดิม" required />
        </div>

        <div class="card">
          <label class="block text-sm font-semibold text-navy mb-2">รหัสผ่านใหม่</label>
          <input v-model="form.new_password" type="password" class="input-field" placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 4 ตัว)" required minlength="4" />
        </div>

        <div class="card">
          <label class="block text-sm font-semibold text-navy mb-2">ยืนยันรหัสผ่านใหม่</label>
          <input v-model="form.new_password_confirmation" type="password" class="input-field" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required />
        </div>

        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4">
          <p class="text-red-600 text-sm">{{ error }}</p>
        </div>

        <button type="submit" :disabled="loading" class="btn-primary w-full py-3 flex items-center justify-center gap-2">
          <span v-if="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ loading ? 'กำลังเปลี่ยน...' : 'เปลี่ยนรหัสผ่าน' }}</span>
        </button>
      </form>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { logout } from '../../store'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const success = ref(false)

const form = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

async function handleSubmit() {
  if (form.new_password !== form.new_password_confirmation) {
    error.value = 'รหัสผ่านใหม่ไม่ตรงกัน'
    return
  }
  if (form.new_password.length < 4) {
    error.value = 'รหัสผ่านต้องมีอย่างน้อย 4 ตัว'
    return
  }

  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/employee/change-password', {
      current_password: form.current_password,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation
    })
    if (res.data.success) {
      success.value = true
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'เปลี่ยนรหัสผ่านล้มเหลว'
  } finally {
    loading.value = false
  }
}

function handleLogout() {
  logout()
  router.push('/login')
}
</script>
