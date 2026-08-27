<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ร้องขอเข้ากะ</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <!-- Tabs -->
      <div class="border-b">
        <nav class="flex gap-6">
          <button @click="tab = 'form'" :class="['py-2 px-1 border-b-2 font-medium text-sm', tab === 'form' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500']">ส่งคำขอ</button>
          <button @click="tab = 'history'" :class="['py-2 px-1 border-b-2 font-medium text-sm', tab === 'history' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500']">ประวัติคำขอ</button>
        </nav>
      </div>

      <!-- Form -->
      <div v-if="tab === 'form'" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทคำขอ</label>
          <div class="flex gap-2">
            <button @click="form.request_type = 'assign'" :class="form.request_type === 'assign' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="flex-1 py-2.5 rounded-xl font-medium text-sm transition-all">ขอกะใหม่</button>
            <button @click="form.request_type = 'modify'" :class="form.request_type === 'modify' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="flex-1 py-2.5 rounded-xl font-medium text-sm transition-all">แก้เวลาเข้า-ออก</button>
            <button @click="form.request_type = 'remove'" :class="form.request_type === 'remove' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="flex-1 py-2.5 rounded-xl font-medium text-sm transition-all">ขอลบกะ</button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">กลุ่มกะ</label>
          <select v-model="form.work_shift_id" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm">
            <option value="">-- เลือกกลุ่มกะ --</option>
            <option v-for="s in shifts" :key="s.id" :value="s.id">กลุ่ม {{ s.group_number }} ({{ s.start_time }}-{{ s.end_time }}, {{ s.work_hours }} ชม.)</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่ม</label>
            <input v-model="form.start_date" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่สิ้นสุด</label>
            <input v-model="form.end_date" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm" />
          </div>
        </div>

        <div v-if="form.request_type === 'modify'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้าใหม่</label>
            <input v-model="form.new_start_time" type="time" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เวลาออกใหม่</label>
            <input v-model="form.new_end_time" type="time" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
          <textarea v-model="form.reason" rows="2" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm resize-none" placeholder="กรอกเหตุผล (ถ้ามี)"></textarea>
        </div>

        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-3 text-center">
          <p class="text-red-600 text-sm">{{ error }}</p>
        </div>

        <button @click="submit" :disabled="submitting" class="w-full py-3 bg-gradient-to-r from-blue-800 to-blue-600 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50">
          {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอ' }}
        </button>
      </div>

      <!-- History -->
      <div v-if="tab === 'history'" class="space-y-3">
        <div v-if="loading" class="text-center py-8">
          <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
        <div v-else-if="requests.length === 0" class="text-center py-8 text-gray-400 text-sm">ไม่มีคำขอ</div>
        <div v-for="r in requests" :key="r.id" class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
          <div class="flex justify-between items-start mb-2">
            <div>
              <span :class="typeBadgeClass(r.request_type)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ typeLabel(r.request_type) }}</span>
              <span class="ml-2 text-sm font-medium text-gray-800">กะกลุ่ม {{ r.work_shift?.group_number }}</span>
            </div>
            <span :class="statusBadgeClass(r.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(r.status) }}</span>
          </div>
          <p class="text-gray-500 text-xs">{{ r.start_date }}{{ r.end_date && r.end_date !== r.start_date ? ' - ' + r.end_date : '' }}</p>
          <p v-if="r.request_type === 'modify' && r.new_start_time" class="text-blue-600 text-xs mt-1">แก้เวลา: {{ r.new_start_time }} - {{ r.new_end_time }}</p>
          <p v-if="r.reason" class="text-gray-400 text-xs mt-1">{{ r.reason }}</p>
          <p v-if="r.supervisor_note" class="text-gray-500 text-xs mt-1">หมายเหตุ: {{ r.supervisor_note }}</p>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/api'

const tab = ref('form')
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const shifts = ref([])
const requests = ref([])

const form = reactive({
  work_shift_id: '',
  request_type: 'assign',
  start_date: '',
  end_date: '',
  new_start_time: '',
  new_end_time: '',
  reason: '',
})

onMounted(async () => {
  try {
    const res = await api.get('/api/shift-requests/available-shifts')
    if (res.data.success) {
      shifts.value = res.data.data.shifts
    }
  } catch (e) {
    console.error(e)
  }
})

async function submit() {
  error.value = ''
  if (!form.work_shift_id || !form.start_date) {
    error.value = 'กรุณาเลือกกลุ่มกะและวันที่'
    return
  }
  if (form.request_type === 'modify' && (!form.new_start_time || !form.new_end_time)) {
    error.value = 'กรุณาระบุเวลาเข้า-ออกใหม่'
    return
  }
  submitting.value = true
  try {
    await api.post('/api/shift-requests', {
      work_shift_id: form.work_shift_id,
      request_type: form.request_type,
      start_date: form.start_date,
      end_date: form.end_date || form.start_date,
      new_start_time: form.new_start_time || null,
      new_end_time: form.new_end_time || null,
      reason: form.reason || null,
    })
    alert('ส่งคำขอสำเร็จ')
    form.work_shift_id = ''
    form.start_date = ''
    form.end_date = ''
    form.new_start_time = ''
    form.new_end_time = ''
    form.reason = ''
    tab.value = 'history'
    loadHistory()
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    submitting.value = false
  }
}

async function loadHistory() {
  loading.value = true
  try {
    const res = await api.get('/api/shift-requests/my-requests')
    if (res.data.success) requests.value = res.data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function typeLabel(t) {
  return { assign: 'ขอกะใหม่', modify: 'แก้เวลา', remove: 'ขอลบกะ' }[t] || t
}
function typeBadgeClass(t) {
  return { assign: 'bg-blue-100 text-blue-700', modify: 'bg-amber-100 text-amber-700', remove: 'bg-red-100 text-red-700' }[t] || 'bg-gray-100 text-gray-700'
}
function statusLabel(s) {
  return { pending: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ปฏิเสธ' }[s] || s
}
function statusBadgeClass(s) {
  return { pending: 'bg-amber-100 text-amber-700', approved: 'bg-emerald-100 text-emerald-700', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'
}
</script>
