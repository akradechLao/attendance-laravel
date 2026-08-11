<template>
  <div class="p-4 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#0f172a]">ข้อเสนอ WFH วันเสาร์</h1>
      <div class="text-sm text-gray-500">1 วัน/เดือน (เฉพาะวันเสาร์)</div>
    </div>

    <!-- Month Selector -->
    <div class="bg-white rounded-xl shadow p-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">เลือกเดือน</label>
      <input type="month" v-model="selectedMonth" @change="loadData" 
             class="w-full border rounded-lg p-2" />
    </div>

    <!-- Current Status -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">สถานะเดือนนี้</h2>
      <div class="grid grid-cols-3 gap-4">
        <div class="text-center p-3 bg-blue-50 rounded-lg">
          <div class="text-2xl font-bold text-blue-600">{{ used }}</div>
          <div class="text-xs text-gray-500">ใช้แล้ว</div>
        </div>
        <div class="text-center p-3 bg-green-50 rounded-lg">
          <div class="text-2xl font-bold text-green-600">{{ remaining }}</div>
          <div class="text-xs text-gray-500">เหลือ</div>
        </div>
        <div class="text-center p-3 bg-gray-50 rounded-lg">
          <div class="text-2xl font-bold text-gray-600">{{ total }}</div>
          <div class="text-xs text-gray-500">สิทธิ์/เดือน</div>
        </div>
      </div>
    </div>

    <!-- Saturday Calendar -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">เลือกวันเสาร์</h2>
      <div v-if="loading" class="text-center py-8 text-gray-500">กำลังโหลด...</div>
      <div v-else class="grid grid-cols-5 gap-3">
        <button v-for="sat in saturdays" :key="sat.date"
                @click="!sat.occupied && selectDate(sat.date)"
                :disabled="sat.occupied || remaining === 0"
                :class="[
                  'p-4 rounded-lg text-center transition',
                  sat.occupied ? 'bg-gray-100 text-gray-400 cursor-not-allowed' :
                  selectedDate === sat.date ? 'bg-blue-600 text-white' :
                  'bg-green-50 text-green-700 hover:bg-green-100'
                ]">
          <div class="text-lg font-bold">{{ sat.day }}</div>
          <div class="text-xs">{{ sat.occupied ? 'ไม่ว่าง' : 'ว่าง' }}</div>
        </button>
      </div>
    </div>

    <!-- Reason Form -->
    <div v-if="selectedDate" class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">เหตุผล (ไม่บังคับ)</h2>
      <textarea v-model="reason" rows="3" placeholder="เช่น ต้องไปทำธุระ..."
                class="w-full border rounded-lg p-2" />
      
      <button @click="submitRequest" :disabled="submitting"
              class="mt-4 w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50">
        {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอ WFH' }}
      </button>
    </div>

    <!-- My Requests This Month -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">รายการ WFH เดือนนี้</h2>
      <div v-if="myRequests.length === 0" class="text-center py-4 text-gray-500">
        ยังไม่มีรายการ
      </div>
      <div v-else class="space-y-3">
        <div v-for="req in myRequests" :key="req.id"
             class="flex items-center justify-between p-3 rounded-lg"
             :class="{
               'bg-yellow-50': req.status === 'pending',
               'bg-green-50': req.status === 'approved',
               'bg-red-50': req.status === 'rejected'
             }">
          <div>
            <div class="font-semibold">{{ formatDate(req.date) }}</div>
            <div class="text-xs text-gray-500">{{ req.reason || 'ไม่ระบุเหตุผล' }}</div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="{
                   'bg-yellow-100 text-yellow-700': req.status === 'pending',
                   'bg-green-100 text-green-700': req.status === 'approved',
                   'bg-red-100 text-red-700': req.status === 'rejected'
                 }">
            {{ statusText(req.status) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'">
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedDate = ref(null)
const reason = ref('')
const loading = ref(true)
const submitting = ref(false)
const saturdays = ref([])
const myRequests = ref([])
const used = ref(0)
const remaining = ref(1)
const total = 1
const toast = ref(null)

const user = computed(() => state.user)
const employeeId = computed(() => user.value?.id)

const loadData = async () => {
  loading.value = true
  try {
    const [satRes, myRes] = await Promise.all([
      api.get('/wfh/available-saturdays', { params: { month: selectedMonth.value } }),
      api.get('/wfh/my-requests', { params: { emp_id: employeeId.value, month: selectedMonth.value } })
    ])
    saturdays.value = satRes.data.data
    myRequests.value = myRes.data.data
    used.value = myRes.data.used
    remaining.value = myRes.data.remaining
  } catch (err) {
    console.error(err)
  }
  loading.value = false
}

const selectDate = (date) => {
  selectedDate.value = date
}

const submitRequest = async () => {
  if (!selectedDate.value) return
  submitting.value = true
  try {
    await api.post('/wfh', {
      emp_id: employeeId.value,
      date: selectedDate.value,
      reason: reason.value
    })
    showToast('success', 'ส่งคำขอ WFH สำเร็จ')
    selectedDate.value = null
    reason.value = ''
    loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  submitting.value = false
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('th-TH', { 
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
  })
}

const statusText = (status) => {
  return { pending: 'รอหัวหน้าอนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ปฏิเสธ' }[status] || status
}

const showToast = (type, message) => {
  toast.value = { type, message }
  setTimeout(() => toast.value = null, 3000)
}

onMounted(loadData)
</script>
