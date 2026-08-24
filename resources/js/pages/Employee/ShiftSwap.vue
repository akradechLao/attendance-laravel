<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ขอย้ายเวร</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-4">
      <!-- Step 1: Pick Date -->
      <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
          <div :class="step >= 1 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500'"
            class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">1</div>
          <h2 class="font-bold text-gray-800">เลือกวันที่ต้องการสลับ</h2>
        </div>
        <input type="date" v-model="swapDate" :min="minDate"
          class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
          @change="onDateChange" />
      </div>

      <!-- Step 2: Pick Colleague -->
      <div v-if="step >= 2" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
          <div :class="step >= 2 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500'"
            class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">2</div>
          <h2 class="font-bold text-gray-800">เลือกคนที่ต้องการสลับด้วย</h2>
        </div>

        <!-- My shift info -->
        <div v-if="mySchedule" class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200">
          <p class="text-blue-700 text-sm font-medium">กะของฉัน: {{ mySchedule.shift_code }} ({{ mySchedule.shift_label }})</p>
        </div>
        <div v-else class="mb-4 p-3 bg-amber-50 rounded-xl border border-amber-200">
          <p class="text-amber-700 text-sm font-medium">ไม่มีเวรวันที่เลือก</p>
        </div>

        <div v-if="loadingEmployees" class="text-center py-6">
          <div class="w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
        <div v-else-if="availableEmployees.length === 0" class="text-center py-6 text-gray-400 text-sm">
          ไม่มีพนักงานที่มีเวรวันนี้
        </div>
        <div v-else class="space-y-2 max-h-64 overflow-y-auto">
          <button v-for="emp in availableEmployees" :key="emp.id"
            @click="selectEmployee(emp)"
            :class="form.target_id === emp.id ? 'ring-2 ring-blue-500 bg-blue-50' : 'hover:bg-gray-50'"
            class="w-full flex items-center justify-between p-3 rounded-xl border border-gray-200 text-left transition-all">
            <div>
              <p class="font-medium text-gray-800 text-sm">{{ emp.name }}</p>
              <p class="text-gray-400 text-xs">{{ emp.employee_code }}</p>
            </div>
            <div class="text-right">
              <span class="bg-gray-100 text-gray-700 text-[10px] font-medium px-2 py-1 rounded-full">
                {{ emp.shift_code }}
              </span>
              <p class="text-gray-400 text-[10px] mt-0.5">{{ emp.shift_label }}</p>
            </div>
          </button>
        </div>
      </div>

      <!-- Step 3: Confirm & Submit -->
      <div v-if="step >= 3" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-7 h-7 rounded-full bg-blue-500 text-white flex items-center justify-center text-sm font-bold">3</div>
          <h2 class="font-bold text-gray-800">ยืนยันการสลับเวร</h2>
        </div>

        <!-- Swap Summary -->
        <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
          <div class="flex items-center justify-center gap-3">
            <div class="text-center flex-1">
              <p class="text-xs text-gray-400 mb-1">ฉัน</p>
              <p class="font-bold text-gray-800 text-sm">{{ store.user?.name }}</p>
              <span class="inline-block mt-1 bg-blue-100 text-blue-700 text-[10px] font-medium px-2 py-0.5 rounded-full">
                {{ mySchedule?.shift_code }} ({{ mySchedule?.shift_label }})
              </span>
            </div>
            <div class="text-2xl text-gray-300">⇄</div>
            <div class="text-center flex-1">
              <p class="text-xs text-gray-400 mb-1">สลับกับ</p>
              <p class="font-bold text-gray-800 text-sm">{{ form.target_name }}</p>
              <span class="inline-block mt-1 bg-amber-100 text-amber-700 text-[10px] font-medium px-2 py-0.5 rounded-full">
                {{ form.target_shift_code }} ({{ form.target_shift_label }})
              </span>
            </div>
          </div>
          <p class="text-center text-gray-500 text-xs mt-3">วันที่ {{ formatDate(swapDate) }}</p>
        </div>

        <!-- Reason -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล (ไม่บังคับ)</label>
          <textarea v-model="form.reason" rows="2"
            class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
            placeholder="เช่น ต้องการสลับเวรเพื่อทำธุระส่วนตัว..." />
        </div>

        <button @click="submitSwap" :disabled="submitting || !mySchedule"
          class="w-full bg-blue-500 text-white py-3 rounded-xl font-bold hover:bg-blue-600 disabled:opacity-50 transition-colors">
          {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอสลับเวร' }}
        </button>
      </div>

      <!-- My Requests -->
      <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
        <h2 class="font-bold text-gray-800 mb-4">คำขอของฉัน</h2>
        <div v-if="loadingSwaps" class="text-center py-4">
          <div class="w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
        <div v-else-if="mySwaps.length === 0" class="text-center py-4 text-gray-400 text-sm">ยังไม่มีคำขอ</div>
        <div v-else class="space-y-2">
          <div v-for="swap in mySwaps" :key="swap.id"
            :class="swap.status === 'pending' ? 'border-amber-300 bg-amber-50' : swap.status === 'approved' ? 'border-emerald-300 bg-emerald-50' : 'border-red-300 bg-red-50'"
            class="p-4 rounded-xl border">
            <div class="flex items-center justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <p class="font-medium text-gray-800 text-sm">{{ swap.requester?.name }}</p>
                  <span class="text-gray-300">⇄</span>
                  <p class="font-medium text-gray-800 text-sm">{{ swap.target?.name }}</p>
                </div>
                <p class="text-gray-400 text-xs mt-1">
                  {{ formatDate(swap.swap_date) }} | {{ swap.requester_shift }} → {{ swap.target_shift }}
                </p>
                <p v-if="swap.reason" class="text-gray-400 text-xs mt-0.5 italic">เหตุผล: {{ swap.reason }}</p>
              </div>
              <span :class="swap.status === 'pending' ? 'bg-amber-100 text-amber-700' : swap.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                class="text-[10px] font-bold px-2 py-1 rounded-full shrink-0 ml-3">
                {{ statusLabel(swap.status) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast"
        :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl text-white text-sm font-medium shadow-lg z-50">
        {{ toast.message }}
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import store from '../../store'
import dayjs from 'dayjs'

const swapDate = ref(dayjs().format('YYYY-MM-DD'))
const minDate = dayjs().format('YYYY-MM-DD')
const step = ref(1)
const loadingEmployees = ref(false)
const loadingSwaps = ref(true)
const submitting = ref(false)
const mySchedule = ref(null)
const availableEmployees = ref([])
const mySwaps = ref([])
const toast = ref(null)

const form = ref({
  target_id: '',
  target_name: '',
  target_shift_code: '',
  target_shift_label: '',
  reason: '',
})

function formatDate(d) {
  return dayjs(d).format('D MMMM YYYY')
}

function statusLabel(s) {
  return { pending: 'รอหัวหน้าอนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ปฏิเสธ' }[s] || s
}

function showToast(type, message) {
  toast.value = { type, message }
  setTimeout(() => toast.value = null, 3000)
}

async function onDateChange() {
  step.value = 2
  form.value = { target_id: '', target_name: '', target_shift_code: '', target_shift_label: '', reason: '' }
  loadingEmployees.value = true
  try {
    const res = await axios.get('/api/shift-swaps/available-employees', { params: { date: swapDate.value } })
    if (res.data.success) {
      mySchedule.value = res.data.data.my_schedule
      availableEmployees.value = res.data.data.available_employees
    }
  } catch (e) {
    showToast('error', 'ไม่สามารถโหลดข้อมูลได้')
  } finally {
    loadingEmployees.value = false
  }
}

function selectEmployee(emp) {
  form.value.target_id = emp.id
  form.value.target_name = emp.name
  form.value.target_shift_code = emp.shift_code
  form.value.target_shift_label = emp.shift_label
  step.value = 3
}

async function submitSwap() {
  if (!form.value.target_id || !mySchedule.value) return
  submitting.value = true
  try {
    await axios.post('/api/shift-swaps', {
      requester_id: store.user?.id,
      target_id: form.value.target_id,
      swap_date: swapDate.value,
      requester_shift: mySchedule.value.shift_code,
      target_shift: form.value.target_shift_code,
      reason: form.value.reason || null,
    })
    showToast('success', 'ส่งคำขอสลับเวรสำเร็จ')
    step.value = 1
    swapDate.value = dayjs().format('YYYY-MM-DD')
    form.value = { target_id: '', target_name: '', target_shift_code: '', target_shift_label: '', reason: '' }
    loadMyRequests()
  } catch (e) {
    showToast('error', e.response?.data?.message || 'เกิดข้อผิดพลาด')
  } finally {
    submitting.value = false
  }
}

async function loadMyRequests() {
  loadingSwaps.value = true
  try {
    const res = await axios.get('/api/shift-swaps/my-requests')
    if (res.data.success) {
      mySwaps.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingSwaps.value = false
  }
}

onMounted(loadMyRequests)
</script>
