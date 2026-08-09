<template>
  <div class="min-h-screen bg-gradient-to-br from-navy via-slate-800 to-blue-900">
    <header class="bg-white/10 backdrop-blur-sm border-b border-white/10">
      <div class="max-w-2xl mx-auto px-4 py-4 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-white hover:text-blue-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-xl font-bold text-white">เปลี่ยนรหัสผ่าน</h1>
      </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-12">
      <!-- Success -->
      <div v-if="success" class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-6 mb-6 text-center">
        <svg class="w-12 h-12 text-emerald-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-emerald-400 text-lg font-semibold">เปลี่ยนรหัสผ่านสำเร็จ!</p>
        <p class="text-blue-200 mt-2">กรุณาเข้าสู่ระบบใหม่ด้วยรหัสผ่านใหม่</p>
        <button @click="handleLogout" class="mt-4 px-6 py-2 bg-emerald-500/30 rounded-lg text-white hover:bg-emerald-500/40 transition-colors">
          เข้าสู่ระบบใหม่
        </button>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-6">
        <!-- รหัสผ่านเดิม -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">รหัสผ่านเดิม</label>
          <input v-model="form.current_password" type="password" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="กรอกรหัสผ่านเดิม" required />
        </div>

        <!-- รหัสผ่านใหม่ -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">รหัสผ่านใหม่</label>
          <input v-model="form.new_password" type="password" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 4 ตัว)" required minlength="4" />
        </div>

        <!-- ยืนยันรหัสผ่าน -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">ยืนยันรหัสผ่านใหม่</label>
          <input v-model="form.new_password_confirmation" type="password" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required />
        </div>

        <!-- Error -->
        <div v-if="error" class="bg-red-500/20 border border-red-500/30 rounded-xl p-4">
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>

        <!-- Submit -->
        <button type="submit" :disabled="loading" class="w-full py-4 bg-blue-500 hover:bg-blue-600 disabled:bg-blue-500/50 rounded-xl text-white font-bold text-lg transition-colors flex items-center justify-center gap-2">
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
