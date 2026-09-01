<template>
  <AppLayout>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">ปฏิทินมอบหมายกะ</h1>
          <p class="text-gray-500 text-sm">เลือกวันทำงานให้พนักงานรายวัน แล้วสรุปก่อนส่งเงินเดือน</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="activeTab = 'calendar'"
            class="px-3 py-1.5 text-sm rounded-lg"
            :class="activeTab === 'calendar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
          >ปฏิทิน</button>
          <button
            @click="activeTab = 'summary'"
            class="px-3 py-1.5 text-sm rounded-lg"
            :class="activeTab === 'summary' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'"
          >สรุปก่อนเงินเดือน</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="card p-3">
        <div class="flex flex-col md:flex-row gap-3 items-end">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">เดือน</label>
            <input v-model="selectedMonth" type="month" class="input-field text-sm" @change="loadData" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">บริษัท</label>
            <select v-model="selectedCompany" class="input-field text-sm" @change="loadData">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">ค้นหา</label>
            <input v-model="searchQuery" type="text" class="input-field text-sm" placeholder="ชื่อ/รหัส..." />
          </div>
          <div v-if="activeTab === 'calendar'">
            <label class="block text-xs font-medium text-gray-500 mb-1">มอบหมายด่วน</label>
            <select v-model="quickShiftCode" class="input-field text-sm">
              <option value="">เลือกกะ</option>
              <option v-for="s in shifts" :key="s.id" :value="'WC' + String(s.group_number).padStart(4, '0')">
                กะ {{ s.group_number }} ({{ s.start_time?.slice(0,5) }}-{{ s.end_time?.slice(0,5) }})
              </option>
              <option value="__day_off">วันหยุด</option>
            </select>
          </div>
          <div v-if="hasPendingChanges" class="ml-auto">
            <button @click="saveAllChanges" :disabled="saving" class="px-4 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-50">
              {{ saving ? 'กำลังบันทึก...' : '💾 บันทึกทั้งหมด' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <!-- Calendar Tab -->
        <template v-if="activeTab === 'calendar'">
          <!-- Shift legend -->
          <div class="flex flex-wrap gap-3 text-xs">
            <div v-for="s in shifts" :key="s.id" class="flex items-center gap-1">
              <span class="w-3 h-3 rounded" :class="getShiftColor(s.group_number)"></span>
              <span>กะ {{ s.group_number }}</span>
            </div>
            <div class="flex items-center gap-1">
              <span class="w-3 h-3 rounded bg-gray-300"></span>
              <span>วันหยุด/ไม่ได้กำหนด</span>
            </div>
          </div>

          <!-- Calendar grid -->
          <div class="card overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="sticky left-0 bg-gray-50 z-10 text-left px-2 py-2 min-w-[140px]">พนักงาน</th>
                    <th
                      v-for="d in daysInMonth"
                      :key="d"
                      class="text-center px-1 py-2 min-w-[28px]"
                      :class="isWeekend(d) ? 'bg-gray-100' : ''"
                    >
                      <div class="text-gray-400">{{ d }}</div>
                      <div class="text-[9px] text-gray-300">{{ getDayName(d) }}</div>
                    </th>
                    <th class="text-center px-2 py-2 min-w-[50px]">วัน</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="emp in filteredEmployees" :key="emp.id" class="hover:bg-gray-50">
                    <td class="sticky left-0 bg-white z-10 px-2 py-1.5">
                      <div class="flex items-center gap-1.5">
                        <input type="checkbox" :checked="selectedEmpIds.has(emp.id)" @change="toggleEmp(emp.id)" class="rounded" />
                        <div>
                          <div class="font-medium text-navy text-xs">{{ emp.name }}</div>
                          <div class="text-[10px] text-gray-400">{{ emp.employee_code }}</div>
                        </div>
                      </div>
                    </td>
                    <td
                      v-for="day in emp.days"
                      :key="day.date"
                      class="text-center px-0.5 py-0.5 cursor-pointer hover:opacity-80"
                      :class="day.is_holiday ? 'bg-gray-50' : ''"
                      @click="toggleDay(emp, day)"
                    >
                      <span
                        v-if="day.shift_code"
                        class="inline-block w-5 h-5 rounded text-[9px] leading-5 text-white font-medium"
                        :class="getShiftColorFromCode(day.shift_code)"
                        :title="`${emp.name} - ${day.date} - ${day.shift_code}`"
                      >{{ getShiftNumber(day.shift_code) }}</span>
                      <span
                        v-else-if="day.is_holiday"
                        class="inline-block w-5 h-5 rounded text-[9px] leading-5 text-gray-300 font-medium"
                        title="วันหยุด"
                      >-</span>
                      <span
                        v-else
                        class="inline-block w-5 h-5 rounded text-[9px] leading-5 border border-dashed border-gray-200 text-gray-300 font-medium"
                        title="ไม่ได้กำหนด"
                      ></span>
                    </td>
                    <td class="text-center px-2 py-1.5">
                      <span class="text-xs font-medium" :class="emp.assigned_days > 0 ? 'text-green-600' : 'text-gray-400'">
                        {{ emp.assigned_days }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Batch actions -->
          <div v-if="selectedEmpIds.size > 0" class="card p-3 bg-blue-50 border-blue-200 flex items-center justify-between">
            <span class="text-sm text-blue-700">เลือก {{ selectedEmpIds.size }} คน — คลิกวันในตารางเพื่อมอบหมายกะ</span>
            <div class="flex gap-2">
              <button @click="selectedEmpIds.clear()" class="text-xs text-gray-500 hover:text-gray-700">ยกเลิกเลือก</button>
            </div>
          </div>
        </template>

        <!-- Summary Tab -->
        <template v-if="activeTab === 'summary'">
          <div class="card overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="text-left px-4 py-3">ชื่อ</th>
                    <th class="text-left px-4 py-3">รหัส</th>
                    <th class="text-left px-4 py-3">บริษัท</th>
                    <th class="text-center px-4 py-3">กำหนดกะ (วัน)</th>
                    <th class="text-center px-4 py-3">เข้างานจริง (วัน)</th>
                    <th class="text-center px-4 py-3">ส่วนต่าง</th>
                    <th class="text-center px-4 py-3">ชั่วโมงจริง</th>
                    <th class="text-center px-4 py-3">สถานะ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="emp in summaryData" :key="emp.emp_id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-navy">{{ emp.name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ emp.employee_code }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ emp.company_name }}</td>
                    <td class="text-center px-4 py-3 font-medium">{{ emp.scheduled_days }}</td>
                    <td class="text-center px-4 py-3 font-medium">{{ emp.actual_days }}</td>
                    <td class="text-center px-4 py-3">
                      <span :class="emp.diff_days >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ emp.diff_days >= 0 ? '+' : '' }}{{ emp.diff_days }}
                      </span>
                    </td>
                    <td class="text-center px-4 py-3">{{ emp.actual_hours }} ชม.</td>
                    <td class="text-center px-4 py-3">
                      <span :class="emp.status === 'ok' ? 'text-green-600' : 'text-red-600'" class="text-xs font-medium">
                        {{ emp.status === 'ok' ? '✓ ตรง' : '✗ ขาด' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="summaryData.length === 0" class="text-center py-8 text-gray-400 text-sm">
              ไม่ข้อมูล
            </div>
          </div>
        </template>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('calendar')
const employees = ref([])
const companies = ref([])
const shifts = ref([])
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedCompany = ref('')
const searchQuery = ref('')
const quickShiftCode = ref('')
const selectedEmpIds = ref(new Set())
const pendingChanges = ref([])
const summaryData = ref([])

const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส']

const daysInMonth = computed(() => {
  return new Date(
    parseInt(selectedMonth.value.split('-')[0]),
    parseInt(selectedMonth.value.split('-')[1]),
    0
  ).getDate()
})

const hasPendingChanges = computed(() => pendingChanges.value.length > 0)

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return employees.value
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter(e =>
    e.name.toLowerCase().includes(q) || e.employee_code.toLowerCase().includes(q)
  )
})

function isWeekend(d) {
  const date = new Date(
    parseInt(selectedMonth.value.split('-')[0]),
    parseInt(selectedMonth.value.split('-')[1]) - 1,
    d
  )
  const w = date.getDay()
  return w === 0 || w === 6
}

function getDayName(d) {
  const date = new Date(
    parseInt(selectedMonth.value.split('-')[0]),
    parseInt(selectedMonth.value.split('-')[1]) - 1,
    d
  )
  return dayNames[date.getDay()]
}

function getShiftColor(groupNumber) {
  const colors = {
    1: 'bg-blue-500', 2: 'bg-green-500', 3: 'bg-purple-500', 4: 'bg-orange-500',
    5: 'bg-pink-500', 6: 'bg-teal-500', 7: 'bg-indigo-500', 8: 'bg-red-500',
  }
  return colors[groupNumber] || 'bg-gray-500'
}

function getShiftColorFromCode(code) {
  const num = parseInt(code.replace('WC', '')) || 0
  return getShiftColor(num)
}

function getShiftNumber(code) {
  return parseInt(code.replace('WC', '')) || '?'
}

function getShiftCodeFromNumber(num) {
  return 'WC' + String(num).padStart(4, '0')
}

function toggleEmp(empId) {
  if (selectedEmpIds.value.has(empId)) {
    selectedEmpIds.value.delete(empId)
  } else {
    selectedEmpIds.value.add(empId)
  }
}

function toggleDay(emp, day) {
  // Determine what to assign
  let shiftCode = null
  let dayType = 'day_off'

  if (quickShiftCode.value === '__day_off') {
    shiftCode = null
    dayType = 'day_off'
  } else if (quickShiftCode.value) {
    shiftCode = quickShiftCode.value
    dayType = 'working'
  } else {
    // Toggle: if has shift → remove; if no shift → add first shift
    if (day.shift_code) {
      shiftCode = null
      dayType = 'day_off'
    } else {
      shiftCode = getShiftCodeFromNumber(1)
      dayType = 'working'
    }
  }

  // If employees are selected, apply to all selected
  const targets = selectedEmpIds.value.size > 0
    ? employees.value.filter(e => selectedEmpIds.value.has(e.id))
    : [emp]

  for (const target of targets) {
    const targetDay = target.days.find(d => d.date === day.date)
    if (targetDay) {
      targetDay.shift_code = shiftCode
      targetDay.day_type = dayType

      // Recalculate assigned_days
      target.assigned_days = target.days.filter(d => d.day_type === 'working').length
    }

    // Add to pending changes
    pendingChanges.value.push({
      emp_id: target.id,
      work_date: day.date,
      shift_code: shiftCode,
      day_type: dayType,
    })
  }
}

async function saveAllChanges() {
  if (pendingChanges.value.length === 0) return
  saving.value = true
  try {
    await api.post('/api/shift-assignments/bulk-day', {
      updates: pendingChanges.value,
    })
    pendingChanges.value = []
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function loadSummary() {
  try {
    const res = await api.get('/api/shift-assignments/summary', {
      params: { month: selectedMonth.value, company_id: selectedCompany.value },
    })
    summaryData.value = res.data.data || []
  } catch (e) {
    console.error(e)
  }
}

async function loadData() {
  loading.value = true
  pendingChanges.value = []
  try {
    const [calendarRes, compRes] = await Promise.all([
      api.get('/api/shift-assignments/calendar', {
        params: { month: selectedMonth.value, company_id: selectedCompany.value },
      }),
      api.get('/api/companies'),
    ])
    employees.value = calendarRes.data.data?.employees || []
    shifts.value = calendarRes.data.data?.shifts || []
    companies.value = compRes.data.data || []

    if (activeTab.value === 'summary') {
      await loadSummary()
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// Watch tab changes
import { watch } from 'vue'
watch(activeTab, async (tab) => {
  if (tab === 'summary') {
    await loadSummary()
  }
})

onMounted(loadData)
</script>
