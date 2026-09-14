<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const shifts = ref([])
const employees = ref([])
const companies = ref([])
const workShifts = ref([])
const showForm = ref(false)
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedCompany = ref('')
const filterCompany = ref('')
const searchQuery = ref('')

const newShift = ref({
  emp_id: '',
  work_date: '',
  shift_code: '',
})

onMounted(async () => {
  await Promise.all([loadShifts(), loadCompanies(), loadWorkShifts()])
})

watch(selectedMonth, () => loadShifts())
watch(filterCompany, () => loadShifts())

const loadShifts = async () => {
  loading.value = true
  try {
    const params = { month: selectedMonth.value }
    if (filterCompany.value) params.company_id = filterCompany.value
    const response = await api.get('/api/shift-schedules', { params })
    shifts.value = response.data.data?.data || response.data.data || []
    if (response.data.work_shifts && response.data.work_shifts.length > 0) {
      workShifts.value = response.data.work_shifts
    }
  } catch (error) {
    console.error('Failed to load shifts:', error)
  } finally {
    loading.value = false
  }
}

const loadEmployees = async () => {
  try {
    const params = { shift_only: true, per_page: 9999 }
    if (selectedCompany.value) params.company_id = selectedCompany.value
    if (searchQuery.value) params.search = searchQuery.value
    const response = await api.get('/api/employees', { params })
    employees.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load employees:', error)
  }
}

const loadCompanies = async () => {
  try {
    const response = await api.get('/api/companies')
    companies.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load companies:', error)
  }
}

const loadWorkShifts = async () => {
  try {
    const response = await api.get('/api/shift-schedules', { params: { month: selectedMonth.value } })
    workShifts.value = response.data.work_shifts || []
  } catch {
    workShifts.value = [
      { group_number: 1, start_time: '08:00', end_time: '17:00', work_hours: 8 },
      { group_number: 2, start_time: '08:30', end_time: '17:30', work_hours: 8 },
      { group_number: 3, start_time: '07:00', end_time: '16:00', work_hours: 8 },
      { group_number: 4, start_time: '07:30', end_time: '16:30', work_hours: 8 },
      { group_number: 5, start_time: '09:00', end_time: '18:00', work_hours: 8 },
      { group_number: 6, start_time: '09:30', end_time: '18:30', work_hours: 8 },
      { group_number: 7, start_time: '10:00', end_time: '19:00', work_hours: 8 },
      { group_number: 8, start_time: '10:30', end_time: '19:30', work_hours: 8 },
      { group_number: 9, start_time: '11:00', end_time: '20:00', work_hours: 8 },
      { group_number: 10, start_time: '11:30', end_time: '20:30', work_hours: 8 },
      { group_number: 11, start_time: '12:00', end_time: '21:00', work_hours: 8 },
      { group_number: 12, start_time: '12:30', end_time: '21:30', work_hours: 8 },
      { group_number: 13, start_time: '13:00', end_time: '22:00', work_hours: 8 },
      { group_number: 14, start_time: '14:00', end_time: '23:00', work_hours: 8 },
      { group_number: 15, start_time: '15:00', end_time: '00:00', work_hours: 8 },
      { group_number: 16, start_time: '16:00', end_time: '01:00', work_hours: 8 },
    ]
  }
}

let empSearchTimeout = null
function debounceLoadEmployees() {
  clearTimeout(empSearchTimeout)
  empSearchTimeout = setTimeout(() => loadEmployees(), 300)
}

// รหัสกะ WC0001-WC0016 ตรงกับ group_number 0-15 บวก 1 เสมอ (ดู ShiftCodeHelper.php ฝั่ง
// backend) - เดิมฟังก์ชันนี้ไม่ได้บวก 1 ทำให้ได้รหัส WC0000-WC0015 ซึ่ง ShiftCodeHelper
// ไม่รู้จักเลยสักตัว การกำหนดกะรายวันผ่านหน้านี้จึงบันทึก shift_code ที่ resolve
// ไม่ได้จริง (เวลาเข้า-ออก/ชั่วโมงพักไม่ถูกคำนวณให้เลย)
function getShiftCode(gn) {
  return 'WC' + String(gn + 1).padStart(4, '0')
}

function getShiftByCode(code) {
  const gn = parseInt(code.replace('WC', ''), 10) - 1
  return workShifts.value.find(ws => ws.group_number === gn)
}

function formatTime(t) {
  if (!t) return '-'
  const str = String(t)
  const match = str.match(/(\d{1,2}):(\d{2})/)
  return match ? `${match[1].padStart(2, '0')}:${match[2]}` : str.substring(0, 5)
}

function getShiftTimes(code) {
  const ws = getShiftByCode(code)
  if (!ws) return { start: '-', end: '-' }
  return { start: formatTime(ws.start_time), end: formatTime(ws.end_time) }
}

function onShiftCodeChange() {
  // times are derived from shift_code, nothing else needed
}

const selectedShiftInfo = computed(() => {
  if (!newShift.value.shift_code) return null
  return getShiftByCode(newShift.value.shift_code)
})

const groupedShifts = computed(() => {
  const groups = {}
  for (const shift of shifts.value) {
    const date = shift.work_date
    if (!groups[date]) groups[date] = []
    groups[date].push(shift)
  }
  const sorted = Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
  return sorted
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const year = d.getFullYear()
  const month = d.getMonth()
  const day = d.getDate()
  const weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์']
  const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
  return `วัน${weekdays[d.getDay()]}ที่ ${day} ${months[month]} ${year + 543}`
}

function addShift() {
  if (!newShift.value.emp_id || !newShift.value.work_date || !newShift.value.shift_code) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }
  api.post('/api/shift-schedules', {
    emp_id: newShift.value.emp_id,
    work_date: newShift.value.work_date,
    shift_code: newShift.value.shift_code,
  }).then(() => {
    alert('เพิ่มกะสำเร็จ')
    showForm.value = false
    newShift.value = { emp_id: '', work_date: '', shift_code: '' }
    selectedCompany.value = ''
    searchQuery.value = ''
    loadShifts()
  }).catch(err => {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาดในการเพิ่มกะ')
  })
}

function deleteShift(id) {
  if (!confirm('ต้องการลบนี้ใช่หรือไม่?')) return
  api.delete(`/api/shift-schedules/${id}`).then(() => {
    alert('ลบกะสำเร็จ')
    loadShifts()
  }).catch(() => {
    alert('เกิดข้อผิดพลาดในการลบ')
  })
}

function openForm() {
  showForm.value = true
  if (companies.value.length === 1) {
    selectedCompany.value = companies.value[0].id
    loadEmployees()
  }
}

const showBreakSettings = ref(false)
const breakEdits = ref({})
const savingBreak = ref(null)

function openBreakSettings() {
  const edits = {}
  for (const ws of workShifts.value) {
    edits[ws.group_number] = {
      start: ws.break_start_time || '',
      end: ws.break_end_time || '',
    }
  }
  breakEdits.value = edits
  showBreakSettings.value = true
}

async function saveBreak(ws) {
  const edit = breakEdits.value[ws.group_number]
  if (!!edit.start !== !!edit.end) {
    alert('กรุณากรอกเวลาพักให้ครบทั้งเริ่มและสิ้นสุด หรือเว้นว่างทั้งคู่')
    return
  }
  savingBreak.value = ws.group_number
  try {
    await api.put(`/api/work-shifts/${ws.id}/break`, {
      break_start_time: edit.start || null,
      break_end_time: edit.end || null,
    })
    ws.break_start_time = edit.start || null
    ws.break_end_time = edit.end || null
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาดในการบันทึกเวลาพัก')
  } finally {
    savingBreak.value = null
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-navy">จัดการกะทำงาน</h1>
          <p class="text-gray-500">กำหนดกะทำงานตามรหัส WC0001-WC0016</p>
        </div>
        <div class="flex gap-3 items-center">
          <select v-model="filterCompany" class="px-3 py-2 border rounded-lg text-sm">
            <option value="">ทุกบริษัท</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <input v-model="selectedMonth" type="month" class="px-3 py-2 border rounded-lg text-sm" />
          <button @click="openBreakSettings" class="px-4 py-2 border border-navy text-navy rounded-lg hover:bg-gray-50 text-sm font-medium">
            ตั้งค่าเวลาพัก
          </button>
          <button @click="openForm" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-slate-800 text-sm font-medium">
            + เพิ่มกะ
          </button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-gray-500">กำลังโหลด...</div>

      <div v-else-if="groupedShifts.length === 0" class="text-center py-12 text-gray-400">
        ไม่มีข้อมูลกะในเดือนนี้
      </div>

      <div v-else class="space-y-4">
        <div v-for="([date, dayShifts]) in groupedShifts" :key="date" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="bg-gray-50 px-4 py-2 border-b flex items-center justify-between">
            <span class="font-semibold text-navy text-sm">{{ formatDate(date) }}</span>
            <span class="text-xs text-gray-500">{{ dayShifts.length }} คน</span>
          </div>
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">รหัส</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">ชื่อพนักงาน</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">รหัสกะ</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">เวลา</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="shift in dayShifts" :key="shift.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-600">{{ shift.employee?.employee_code }}</td>
                <td class="px-4 py-2 font-medium text-navy">{{ shift.employee?.name }}</td>
                <td class="px-4 py-2">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ shift.shift_code }}
                  </span>
                </td>
                <td class="px-4 py-2 text-gray-600">{{ getShiftTimes(shift.shift_code).start }} - {{ getShiftTimes(shift.shift_code).end }}</td>
                <td class="px-4 py-2">
                  <button @click="deleteShift(shift.id)" class="text-red-500 hover:text-red-700 text-xs">ลบ</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Shift Modal -->
      <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-navy mb-4">เพิ่มกะ</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท *</label>
              <select v-model="selectedCompany" class="w-full px-3 py-2 border rounded-lg text-sm" @change="loadEmployees" required>
                <option value="">เลือกบริษัท</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาพนักงาน</label>
              <input v-model="searchQuery" type="text" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="ชื่อหรือรหัส..." @input="debounceLoadEmployees" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน (เฉพาะเข้ากะ) *</label>
              <select v-model="newShift.emp_id" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                <option value="">{{ !selectedCompany.value ? 'เลือกบริษัทก่อน' : 'เลือกพนักงาน' }}</option>
                <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.employee_code }} - {{ emp.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ *</label>
              <input v-model="newShift.work_date" type="date" class="w-full px-3 py-2 border rounded-lg text-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">รหัสกะ *</label>
              <select v-model="newShift.shift_code" class="w-full px-3 py-2 border rounded-lg text-sm" required @change="onShiftCodeChange">
                <option value="">เลือกรหัสกะ</option>
                <option v-for="ws in workShifts" :key="ws.group_number" :value="getShiftCode(ws.group_number)">
                  {{ getShiftCode(ws.group_number) }} — {{ ws.start_time?.substring(0,5) }}-{{ ws.end_time?.substring(0,5) }} ({{ ws.work_hours }}ชม.)
                </option>
              </select>
            </div>
            <div v-if="selectedShiftInfo" class="p-3 bg-blue-50 rounded-lg text-sm text-blue-800">
              เวลาเข้างาน: <strong>{{ selectedShiftInfo.start_time?.substring(0,5) }}</strong> —
              เวลาออกงาน: <strong>{{ selectedShiftInfo.end_time?.substring(0,5) }}</strong> ({{ selectedShiftInfo.work_hours }} ชม.)
            </div>
            <div class="flex gap-3 justify-end pt-2 border-t">
              <button @click="showForm = false; selectedCompany = ''; searchQuery = ''" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="addShift" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-slate-800 text-sm font-medium">เพิ่ม</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Break Time Settings Modal -->
      <div v-if="showBreakSettings" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[85vh] overflow-y-auto">
          <h3 class="text-lg font-bold text-navy mb-1">ตั้งค่าเวลาพักตามกะ</h3>
          <p class="text-sm text-gray-500 mb-4">
            กำหนดช่วงเวลาพักของแต่ละกะ ระบบจะหักเวลานี้ออกจากชั่วโมงทำงานจริงให้อัตโนมัติ
            (เว้นว่างทั้งสองช่อง = กะนี้ไม่มีการหักเวลาพัก)
          </p>
          <div class="space-y-2">
            <div v-for="ws in workShifts" :key="ws.group_number" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 p-3 border rounded-lg">
              <div class="w-full sm:w-32 shrink-0">
                <span class="text-sm font-semibold text-navy">{{ getShiftCode(ws.group_number) }}</span>
                <p class="text-xs text-gray-400">{{ formatTime(ws.start_time) }}-{{ formatTime(ws.end_time) }}{{ ws.is_overnight ? ' (ข้ามคืน)' : '' }}</p>
              </div>
              <div class="flex items-center gap-2 flex-1">
                <input type="time" v-model="breakEdits[ws.group_number].start" class="px-2 py-1.5 border rounded-lg text-sm w-full sm:w-auto" />
                <span class="text-gray-400 text-sm shrink-0">ถึง</span>
                <input type="time" v-model="breakEdits[ws.group_number].end" class="px-2 py-1.5 border rounded-lg text-sm w-full sm:w-auto" />
              </div>
              <button
                @click="saveBreak(ws)"
                :disabled="savingBreak === ws.group_number"
                class="px-3 py-1.5 bg-navy text-white text-xs font-medium rounded-lg hover:bg-slate-800 disabled:opacity-50 shrink-0"
              >
                {{ savingBreak === ws.group_number ? 'กำลังบันทึก...' : 'บันทึก' }}
              </button>
            </div>
          </div>
          <div class="flex justify-end pt-4 mt-2 border-t">
            <button @click="showBreakSettings = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ปิด</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
