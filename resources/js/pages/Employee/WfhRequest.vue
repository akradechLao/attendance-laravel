<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ขอ WFH</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <div class="flex items-center justify-between">
        <div></div>
        <div class="text-sm text-gray-500">{{ total }} วัน/เดือน (วันเสาร์)</div>
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

    <!-- Date Picker -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">เลือกวัน WFH (วันเสาร์)</h2>
      <div v-if="loading" class="text-center py-8 text-gray-500">กำลังโหลด...</div>
      <div v-else>
        <div class="grid grid-cols-7 gap-2 mb-3">
          <div v-for="day in ['จ','อ','พ','พฤ','ศ','ส','อา']" :key="day" class="text-center text-xs text-gray-500 font-medium py-1">{{ day }}</div>
          <div v-for="(blank, i) in startDayBlanks" :key="'b'+i"></div>
          <button v-for="d in daysInMonth" :key="d.date"
                  @click="d.isSaturday && !occupiedMap[d.date] && !d.isTooOld && selectDate(d.date)"
                  :disabled="!d.isSaturday || occupiedMap[d.date] || remaining === 0 || d.isTooOld"
                  :class="[
                    'p-2 rounded-lg text-center transition text-xs',
                    !d.isSaturday ? 'bg-gray-50 text-gray-300 cursor-not-allowed' :
                    occupiedMap[d.date] ? 'bg-gray-100 text-gray-400 cursor-not-allowed' :
                    d.isTooOld ? 'bg-gray-50 text-gray-300 cursor-not-allowed' :
                    selectedDate === d.date ? 'bg-blue-600 text-white' :
                    'bg-green-50 text-green-700 hover:bg-green-100'
                  ]">
            <div class="font-bold">{{ d.day }}</div>
            <div v-if="occupiedMap[d.date]" class="text-[9px]">ไม่ว่าง</div>
          </button>
        </div>
        <p class="text-xs text-gray-400 text-center">เลือกวันที่ต้องการ WFH (สีเขียว = ว่าง, ลงได้ย้อนหลัง 30 วัน)</p>
      </div>
    </div>

    <!-- Reason Form -->
    <div v-if="selectedDate" class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">วันที่เลือก: {{ formatSelectedDate }}</h2>
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
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const selectedMonth = ref(new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0'))
const selectedDate = ref(null)
const reason = ref('')
const loading = ref(true)
const submitting = ref(false)
const myRequests = ref([])
const used = ref(0)
const remaining = ref(1)
const total = ref(1)
const toast = ref(null)
const occupiedMap = ref({})

const user = computed(() => state.user)
const employeeId = computed(() => user.value?.id)

const daysInMonth = computed(() => {
  const [year, month] = selectedMonth.value.split('-').map(Number)
  const daysCount = new Date(year, month, 0).getDate()
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const minDate = new Date(today)
  minDate.setDate(minDate.getDate() - 30)
  return Array.from({ length: daysCount }, (_, i) => {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`
    const d = new Date(year, month - 1, i + 1)
    return {
      date: dateStr,
      day: i + 1,
      isSaturday: d.getDay() === 6,
      isWeekend: d.getDay() === 0 || d.getDay() === 6,
      isPast: d < today,
      isTooOld: d < minDate,
    }
  })
})

const startDayBlanks = computed(() => {
  const [year, month] = selectedMonth.value.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1).getDay()
  return firstDay === 0 ? 6 : firstDay - 1
})

const formatSelectedDate = computed(() => {
  if (!selectedDate.value) return ''
  const [y, m, d] = selectedDate.value.split('-').map(Number)
  const dt = new Date(y, m - 1, d)
  return dt.toLocaleDateString('th-TH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const loadData = async () => {
  loading.value = true
  try {
    const [satRes, myRes] = await Promise.all([
      api.get('/wfh/available-saturdays', { params: { month: selectedMonth.value } }),
      api.get('/wfh/my-requests', { params: { month: selectedMonth.value } })
    ])
    myRequests.value = myRes.data.data || []
    used.value = myRes.data.used || 0
    remaining.value = myRes.data.remaining ?? 1
    total.value = myRes.data.quota || 1

    const newMap = {}
    if (satRes.data.data) {
      for (const sat of satRes.data.data) {
        newMap[sat.date] = sat.occupied
      }
    }
    occupiedMap.value = newMap
  } catch (err) {
    console.error('WFH load error:', err)
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
  if (!date) return ''
  const [y, m, d] = date.split('-').map(Number)
  const dt = new Date(y, m - 1, d)
  return dt.toLocaleDateString('th-TH', { 
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
