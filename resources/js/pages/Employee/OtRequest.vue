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

      <!-- OT History -->
      <div v-if="otHistory.length > 0" class="mt-8">
        <h2 class="text-white font-bold mb-4">ประวัติขอ OT</h2>
        <div class="space-y-3">
          <div v-for="ot in otHistory" :key="ot.id"
            class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
            <div class="flex items-center justify-between mb-2">
              <span class="text-white font-semibold">{{ formatDate(ot.date || ot.ot_date) }}</span>
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(ot.status).class]">
                {{ statusBadge(ot.status).text }}
              </span>
            </div>
            <p class="text-blue-200 text-sm">{{ ot.start_time }} - {{ ot.end_time }} ({{ ot.hours || '-' }} ชม.)</p>
            <p v-if="ot.reason" class="text-blue-300 text-xs mt-1">{{ ot.reason }}</p>
            <p v-if="ot.rejection_reason" class="text-red-300 text-xs mt-1">เหตุผลปฏิเสธ: {{ ot.rejection_reason }}</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const error = ref('')
const success = ref(false)
const otHistory = ref([])

const form = reactive({
  date: '',
  start_time: '',
  end_time: '',
  reason: ''
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr.split('T')[0])
  const day = d.getDate()
  const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
  return `${day} ${months[d.getMonth()]} ${d.getFullYear() + 543}`
}

function statusBadge(status) {
  const map = {
    pending_manager: { text: 'รออนุมัติผู้จัดการ', class: 'bg-amber-100 text-amber-700' },
    pending_hr: { text: 'รออนุมัติ HR', class: 'bg-blue-100 text-blue-700' },
    approved: { text: 'อนุมัติแล้ว', class: 'bg-green-100 text-green-700' },
    rejected: { text: 'ปฏิเสธ', class: 'bg-red-100 text-red-700' },
  }
  return map[status] || { text: status, class: 'bg-gray-100 text-gray-700' }
}

async function fetchHistory() {
  try {
    const res = await axios.get('/api/ot')
    otHistory.value = res.data.data?.data || res.data.data || []
  } catch (e) {
    // ignore
  }
}

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/employee/ot-requests', form)
    if (res.data.success) {
      success.value = true
      await fetchHistory()
    } else {
      error.value = res.data.message || 'เกิดข้อผิดพลาด'
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}

onMounted(fetchHistory)
</script>
