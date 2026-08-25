<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ขอลา</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-6">

    <!-- Leave Balance -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">สิทธิ์ลาคงเหลือ ({{ year }})</h2>
      <div class="grid grid-cols-3 gap-3">
        <div v-for="b in balances" :key="b.leave_type_id"
             class="text-center p-3 rounded-lg"
             :class="b.remaining > 0 ? 'bg-blue-50' : 'bg-gray-50'">
          <div class="text-lg font-bold" :class="b.remaining > 0 ? 'text-blue-600' : 'text-gray-400'">
            {{ b.remaining }}
          </div>
          <div class="text-xs text-gray-500">{{ b.name }}</div>
          <div class="text-[10px] text-gray-400">ใช้แล้ว {{ b.used }}/{{ b.entitled }}</div>
          <div v-if="b.vacation_accumulated > 0" class="text-[10px] text-purple-500 mt-1 font-semibold">+{{ b.vacation_accumulated }} วันพิเศษ</div>
          <div v-if="b.vacation_expiry_date" class="text-[10px] text-orange-400">หมดอายุ {{ b.vacation_expiry_date }}</div>
        </div>
      </div>
    </div>

    <!-- Leave Form -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ส่งคำขอลา</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">ประเภทลา</label>
          <select v-model="form.leave_type_id" class="w-full border rounded-lg p-2">
            <option value="">เลือกประเภทลา</option>
            <option v-for="b in balances" :key="b.leave_type_id" :value="b.leave_type_id"
                    :disabled="b.remaining <= 0 && b.code !== 'unpaid'">
              {{ b.name }} (เหลือ {{ b.remaining }} วัน)
            </option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่เริ่ม</label>
            <input type="date" v-model="form.start_date" class="w-full border rounded-lg p-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่สิ้นสุด</label>
            <input type="date" v-model="form.end_date" class="w-full border rounded-lg p-2" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">เหตุผล</label>
          <textarea v-model="form.reason" rows="3" class="w-full border rounded-lg p-2" placeholder="กรอกเหตุผล..." />
        </div>
        <div v-if="totalDays > 0" class="text-sm text-blue-600">
          จำนวนวันลา: {{ totalDays }} วัน
        </div>
        <div v-if="selectedBalance && totalDays > 0" class="text-sm" :class="totalDays <= selectedBalance.remaining ? 'text-green-600' : 'text-red-600'">
          เหลือหลังลา: {{ Math.max(0, selectedBalance.remaining - totalDays) }} วัน
        </div>
        <div v-if="selectedBalance && totalDays > selectedBalance.remaining && selectedBalance.code !== 'unpaid'" class="text-sm text-red-600 bg-red-50 p-2 rounded-lg">
          ⚠️ วันลาประเภทนี้เหลือ {{ selectedBalance.remaining }} วัน แต่คุณต้องการลา {{ totalDays }} วัน (เกิน {{ totalDays - selectedBalance.remaining }} วัน)
        </div>
        <button @click="submitLeave" :disabled="submitting"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอลา' }}
        </button>
      </div>
    </div>

    <!-- My Requests -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">คำขอลาของฉัน</h2>
      <div v-if="myLeaves.length === 0" class="text-center py-4 text-gray-500">ยังไม่มีคำขอ</div>
      <div v-else class="space-y-3">
        <div v-for="leave in myLeaves" :key="leave.id"
             class="p-3 rounded-lg flex justify-between items-center"
             :class="{'bg-yellow-50': leave.status==='pending', 'bg-green-50': leave.status==='approved', 'bg-red-50': leave.status==='rejected'}">
          <div>
            <div class="font-semibold text-sm">{{ leave.leave_type?.name }}</div>
            <div class="text-xs text-gray-500">{{ leave.start_date }} - {{ leave.end_date }} ({{ leave.total_days }} วัน)</div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="{'bg-yellow-100 text-yellow-700': leave.status==='pending', 'bg-green-100 text-green-700': leave.status==='approved', 'bg-red-100 text-red-700': leave.status==='rejected'}">
            {{ statusText(leave.status) }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type==='success' ? 'bg-green-600' : 'bg-red-600'">{{ toast.message }}</div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const user = computed(() => state.user)
const employeeId = computed(() => user.value?.id)
const year = new Date().getFullYear()
const balances = ref([])
const myLeaves = ref([])
const submitting = ref(false)
const toast = ref(null)

const form = ref({ leave_type_id: '', start_date: '', end_date: '', reason: '' })

const totalDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return 0
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  return Math.max(1, Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1)
})

const selectedBalance = computed(() => {
  if (!form.value.leave_type_id) return null
  return balances.value.find(b => b.leave_type_id == form.value.leave_type_id) || null
})

const loadData = async () => {
  try {
    const [balRes, leaveRes] = await Promise.all([
      api.get('/api/leave/balance', { params: { emp_id: employeeId.value, year } }),
      api.get('/api/leave/my-requests', { params: { emp_id: employeeId.value } })
    ])
    balances.value = balRes.data.data || []
    myLeaves.value = leaveRes.data.data || []
  } catch (err) { console.error(err) }
}

const submitLeave = async () => {
  submitting.value = true
  try {
    await api.post('/api/leave', { ...form.value, emp_id: employeeId.value })
    showToast('success', 'ส่งคำขอลาสำเร็จ')
    form.value = { leave_type_id: '', start_date: '', end_date: '', reason: '' }
    loadData()
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
  submitting.value = false
}

const statusText = (s) => ({ pending:'รอหัวหน้าอนุมัติ', approved:'อนุมัติแล้ว', rejected:'ปฏิเสธ' })[s] || s
const showToast = (type, message) => { toast.value = {type, message}; setTimeout(() => toast.value = null, 3000) }

onMounted(loadData)
</script>
