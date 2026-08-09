<template>
  <div class="min-h-screen bg-gradient-to-br from-navy via-slate-800 to-blue-900">
    <header class="bg-white/10 backdrop-blur-sm border-b border-white/10">
      <div class="max-w-2xl mx-auto px-4 py-4 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-white hover:text-blue-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-xl font-bold text-white">ขอลางาน</h1>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-8">
      <!-- Success Message -->
      <div v-if="success" class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-6 mb-6 text-center">
        <p class="text-emerald-400 text-lg font-semibold">ส่งคำขอสำเร็จ!</p>
        <p class="text-blue-200 mt-2">รอหัวหน้าอนุมัติ</p>
        <router-link to="/employee/menu" class="mt-4 inline-block px-6 py-2 bg-emerald-500/30 rounded-lg text-white hover:bg-emerald-500/40 transition-colors">
          กลับหน้าเมนู
        </router-link>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-6">
        <!-- ประเภทการลา -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">ประเภทการลา</label>
          <select v-model="form.leave_type_id" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            <option value="" disabled class="text-gray-800">เลือกประเภท</option>
            <option v-for="type in leaveTypes" :key="type.id" :value="type.id" class="text-gray-800">{{ type.name }}</option>
          </select>
        </div>

        <!-- วันที่ -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-white font-semibold mb-2">ตั้งแต่วันที่</label>
              <input v-model="form.start_date" type="date" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required />
            </div>
            <div>
              <label class="block text-white font-semibold mb-2">ถึงวันที่</label>
              <input v-model="form.end_date" type="date" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required />
            </div>
          </div>
        </div>

        <!-- เหตุผล -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
          <label class="block text-white font-semibold mb-3">เหตุผล</label>
          <textarea v-model="form.reason" rows="3" class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="กรอกเหตุผลการลา"></textarea>
        </div>

        <!-- Error -->
        <div v-if="error" class="bg-red-500/20 border border-red-500/30 rounded-xl p-4">
          <p class="text-red-400 text-sm">{{ error }}</p>
        </div>

        <!-- Submit -->
        <button type="submit" :disabled="loading" class="w-full py-4 bg-blue-500 hover:bg-blue-600 disabled:bg-blue-500/50 rounded-xl text-white font-bold text-lg transition-colors flex items-center justify-center gap-2">
          <span v-if="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ loading ? 'กำลังส่ง...' : 'ส่งคำขอ' }}</span>
        </button>
      </form>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const error = ref('')
const success = ref(false)
const leaveTypes = ref([])

const form = reactive({
  leave_type_id: '',
  start_date: '',
  end_date: '',
  reason: ''
})

onMounted(async () => {
  try {
    const res = await axios.get('/api/leave/types')
    if (res.data.success) leaveTypes.value = res.data.data
  } catch (e) {
    leaveTypes.value = [{ id: 1, name: 'ลากิจ' }, { id: 2, name: 'ลาป่วย' }, { id: 3, name: 'ลาพักผ่อน' }]
  }
})

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/employee/leave-requests', form)
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
