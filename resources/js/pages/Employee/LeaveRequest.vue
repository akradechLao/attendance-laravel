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
            <input type="date" v-model="form.start_date" :min="minDate" class="w-full border rounded-lg p-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่สิ้นสุด</label>
            <input type="date" v-model="form.end_date" :min="form.start_date || minDate" class="w-full border rounded-lg p-2" />
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
         <div v-if="selectedBalance && selectedBalance.code === 'maternity' && totalDays > 0" class="text-sm" :class="totalDays <= (maxDaysPerType['maternity'] || 999) ? 'text-green-600' : 'text-red-600'">
           สูงสุดลาแบบคลอด: {{ maxDaysPerType['maternity'] }} วัน
         </div>
         <div v-if="selectedBalance && selectedBalance.code === 'maternity' && totalDays > (maxDaysPerType['maternity'] || 999)" class="text-sm text-red-600 bg-red-50 p-2 rounded-lg">
           ⚠️ ลาแบบคลอดได้สูงสุด {{ maxDaysPerType['maternity'] }} วันเท่านั้น (คุณต้องการ {{ totalDays }} วัน)
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
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-[#0f172a]">คำขอลาของฉัน</h2>
        <div class="flex bg-gray-100 rounded-lg p-0.5">
          <button @click="leaveViewMode = 'list'" :class="leaveViewMode === 'list' ? 'bg-white shadow text-gray-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-xs font-medium transition-all">รายการ</button>
          <button @click="leaveViewMode = 'calendar'" :class="leaveViewMode === 'calendar' ? 'bg-white shadow text-gray-700' : 'text-gray-500'" class="px-2.5 py-1 rounded-md text-xs font-medium transition-all">ปฏิทิน</button>
        </div>
      </div>

      <template v-if="leaveViewMode === 'list'">
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
      </template>

      <template v-else>
        <MonthCalendar :year="leaveCalYear" :month="leaveCalMonth" @prev="leaveCalPrevMonth" @next="leaveCalNextMonth">
          <template #cell="{ cell }">
            <button
              v-if="cell"
              @click="selectLeaveCalDay(cell)"
              class="w-full h-full rounded-lg flex flex-col items-center justify-center text-[11px] transition-colors"
              :class="[leaveCalCellClass(cell.date), cell.isToday ? 'ring-2 ring-blue-400' : '', leaveCalCellInfo(cell.date) ? 'cursor-pointer' : 'cursor-default']"
            >
              <span>{{ cell.day }}</span>
              <span v-if="leaveCalCellInfo(cell.date)" class="w-1 h-1 rounded-full mt-0.5" :class="leaveCalDotClass(cell.date)"></span>
            </button>
          </template>
        </MonthCalendar>

        <div class="flex flex-wrap gap-3 mt-4 pt-3 border-t border-gray-100 text-[11px] text-gray-500">
          <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>อนุมัติแล้ว</div>
          <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>รออนุมัติ</div>
          <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>ปฏิเสธ</div>
        </div>

        <div v-if="selectedLeaveDay" class="mt-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
          <p class="font-medium text-gray-800 text-sm mb-1">{{ selectedLeaveDay.leave_type?.name }}</p>
          <p class="text-gray-500 text-xs">{{ selectedLeaveDay.start_date }} - {{ selectedLeaveDay.end_date }} ({{ selectedLeaveDay.total_days }} วัน)</p>
          <p class="text-gray-500 text-xs mt-0.5">สถานะ: {{ statusText(selectedLeaveDay.status) }}</p>
          <p v-if="selectedLeaveDay.reason" class="text-gray-400 text-xs mt-0.5 italic">{{ selectedLeaveDay.reason }}</p>
        </div>
      </template>
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
import MonthCalendar from '@/components/MonthCalendar.vue'

const user = computed(() => state.user)
const employeeId = computed(() => user.value?.id)
const year = new Date().getFullYear()
const balances = ref([])
const myLeaves = ref([])
const submitting = ref(false)
const toast = ref(null)

// ─── Calendar view (ใช้ myLeaves ที่โหลดไว้แล้ว กรองตามเดือนฝั่ง client) ───
const leaveViewMode = ref('list')
const leaveToday = new Date()
const leaveCalYear = ref(leaveToday.getFullYear())
const leaveCalMonth = ref(leaveToday.getMonth() + 1)
const selectedLeaveDay = ref(null)

function leaveCalCellInfo(dateStr) {
  return myLeaves.value.find(l => dateStr >= l.start_date && dateStr <= l.end_date) || null
}

function leaveCalDotClass(dateStr) {
  const l = leaveCalCellInfo(dateStr)
  if (!l) return ''
  return l.status === 'approved' ? 'bg-green-400' : l.status === 'rejected' ? 'bg-red-400' : 'bg-yellow-400'
}

function leaveCalCellClass(dateStr) {
  const l = leaveCalCellInfo(dateStr)
  if (!l) return 'text-gray-400 hover:bg-gray-50'
  if (l.status === 'approved') return 'bg-green-50 text-green-700'
  if (l.status === 'rejected') return 'bg-red-50 text-red-700'
  return 'bg-yellow-50 text-yellow-700'
}

function selectLeaveCalDay(cell) {
  const l = leaveCalCellInfo(cell.date)
  if (l) selectedLeaveDay.value = l
}

function leaveCalPrevMonth() {
  selectedLeaveDay.value = null
  leaveCalMonth.value--
  if (leaveCalMonth.value < 1) { leaveCalMonth.value = 12; leaveCalYear.value-- }
}

function leaveCalNextMonth() {
  selectedLeaveDay.value = null
  leaveCalMonth.value++
  if (leaveCalMonth.value > 12) { leaveCalMonth.value = 1; leaveCalYear.value++ }
}

const maxDaysPerType = { maternity: 120, sick: 30, personal: 6, annual: 6, ordination: 15, unpaid: 0 }

const minDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() - 30)
  return d.toISOString().slice(0, 10)
})

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
    // POST /api/leave resolves to LeaveController@store, which requires an
    // `employee_id` this page never sends (422) and skips the quota check,
    // auto-approval and balance deduction. The employee endpoint takes the
    // employee from the token instead of the request body.
    await api.post('/api/employee/leave-requests', form.value)
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
