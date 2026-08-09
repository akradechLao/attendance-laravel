<template>
  <div class="min-h-screen bg-gradient-to-br from-navy via-slate-800 to-blue-900">
    <header class="bg-white/10 backdrop-blur-sm border-b border-white/10">
      <div class="max-w-2xl mx-auto px-4 py-4 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-white hover:text-blue-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-xl font-bold text-white">ขอโอที</h1>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-8">
      <div v-if="success" class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-6 mb-6 text-center">
        <p class="text-emerald-400 text-lg font-semibold">ส่งคำขอสำเร็จ!</p>
        <p class="text-blue-200 mt-2">รอหัวหน้าอนุมัติ</p>
        <router-link to="/employee/menu" class="mt-4 inline-block px-6 py-2 bg-emerald-500/30 rounded-lg text-white hover:bg-emerald-500/40 transition-colors">
          กลับหน้าเมนู
        </router-link>
      </div>

      <form v-else @submit.prevent="handleSubmit" class="space-y-6">
        <!-- วันที่ -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">วันที่ต้องการโอที</label>
          <input v-model="form.date" type="date" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required />
        </div>

        <!-- เวลา -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-white font-semibold mb-2">เริ่มเวลา</label>
              <input v-model="form.start_time" type="time" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required />
            </div>
            <div>
              <label class="block text-white font-semibold mb-2">สิ้นสุดเวลา</label>
              <input v-model="form.end_time" type="time" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required />
            </div>
          </div>
        </div>

        <!-- เหตุผล -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">เหตุผล</label>
          <textarea v-model="form.reason" rows="3" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="กรอกเหตุผลการขอโอที"></textarea>
        </div>

        <div v-if="error" class="bg-red-500/20 border border-red-500/30 rounded-xl p-4">
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>

        <button type="submit" :disabled="loading" class="w-full py-4 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-500/50 rounded-xl text-white font-bold text-lg transition-colors flex items-center justify-center gap-2">
          <span v-if="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ loading ? 'กำลังส่ง...' : 'ส่งคำขอ' }}</span>
        </button>
      </form>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

const loading = ref(false)
const error = ref('')
const success = ref(false)

const form = reactive({
  date: '',
  start_time: '',
  end_time: '',
  reason: ''
})

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/employee/ot-requests', form)
    if (res.data.success) {
      success.value = true
    } else {
      error.value = res.data.message || 'เกิดข้อผิดพลาด'
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
