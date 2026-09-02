<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">มอบหมายกะอัตโนมัติ</h1>
          <p class="text-gray-500">พนักงานกะ (Shift) — มอบหมายกะรายเดือน</p>
        </div>
      </div>

      <!-- Step 1: Select Employees -->
      <div class="card">
        <h3 class="text-sm font-semibold text-navy mb-3">1. เลือกพนักงาน</h3>
        <div class="flex flex-col md:flex-row gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="form.companyFilter" class="input-field" @change="loadEmployees">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input v-model="form.search" type="text" class="input-field" placeholder="ชื่อ/รหัสพนักงาน..." />
          </div>
        </div>
        <div v-if="loading" class="text-center py-4">
          <div class="w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
        <div v-else class="max-h-72 overflow-y-auto border rounded-lg">
          <table class="w-full">
            <thead class="sticky top-0 bg-gray-50">
              <tr>
                <th class="text-center px-3 py-2 w-10">
                  <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="rounded" />
                </th>
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">พนักงาน</th>
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">บริษัท</th>
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">กะปัจจุบัน</th>
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">ช่วงวันที่</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="emp in filteredEmployees" :key="emp.id"
                  class="hover:bg-gray-50 cursor-pointer"
                  :class="form.emp_ids.includes(emp.id) ? 'bg-blue-50' : ''"
                  @click="toggleEmp(emp.id)">
                <td class="text-center px-3 py-2" @click.stop>
                  <input type="checkbox" :checked="form.emp_ids.includes(emp.id)" @change="toggleEmp(emp.id)" class="rounded" />
                </td>
                <td class="px-3 py-2">
                  <div class="text-sm font-medium text-navy">{{ emp.name }}</div>
                  <div class="text-[10px] text-gray-400">{{ emp.employee_code }}</div>
                </td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ emp.company_name }}</td>
                <td class="px-3 py-2">
                  <span v-if="emp.current_shift" :class="[
                    'px-2 py-0.5 rounded text-[10px] font-medium',
                    emp.current_shift.is_overnight ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
                  ]">
                    {{ emp.current_shift.start_time }}-{{ emp.current_shift.end_time }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2 text-[10px] text-gray-400">
                  {{ emp.current_shift?.start_date || '-' }} ~ {{ emp.current_shift?.end_date || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-2 text-xs text-gray-500">
          เลือกแล้ว {{ form.emp_ids.length }} คน
        </div>
      </div>

      <!-- Step 2: Shift + Date Range -->
      <div class="card">
        <h3 class="text-sm font-semibold text-navy mb-3">2. เลือกกะ + วันที่</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div class="md:col-span-2 lg:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-2">Shift Code *</label>
            <div class="grid grid-cols-4 md:grid-cols-8 gap-2">
              <button v-for="s in shifts" :key="s.id"
                      @click="form.shift_code = codeFromGroup(s.group_number)"
                      :class="form.shift_code === codeFromGroup(s.group_number)
                        ? 'ring-2 ring-blue-500 bg-blue-50'
                        : 'hover:bg-gray-50'"
                      class="p-2 rounded-lg border text-center transition-all">
                <div class="text-xs font-bold text-navy">{{ codeFromGroup(s.group_number) }}</div>
                <div class="text-[10px] text-gray-500">{{ s.start_time?.substring(0,5) }}-{{ s.end_time?.substring(0,5) }}</div>
                <div class="text-[10px]" :class="s.is_overnight ? 'text-purple-500' : 'text-gray-400'">
                  {{ s.work_hours }}ชม. {{ s.is_overnight ? '(ข้ามคืน)' : '' }}
                </div>
              </button>
            </div>
            <div v-if="form.shift_code" class="mt-2 text-xs text-blue-600">
              เลือก: {{ form.shift_code }} — {{ getShiftTime(form.shift_code) }}
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่ม *</label>
            <input v-model="form.start_date" type="date" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่สิ้นสุด *</label>
            <input v-model="form.end_date" type="date" class="input-field" />
          </div>
          <div class="flex items-end gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" v-model="form.skip_sunday" class="rounded" />
              ข้ามวันอาทิตย์
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" v-model="form.skip_holiday" class="rounded" />
              ข้ามวันหยุดบริษัท
            </label>
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <button @click="autoPreview" :disabled="previewing || form.emp_ids.length === 0 || !form.shift_code"
                  class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 disabled:opacity-50">
            {{ previewing ? 'กำลังคำนวณ...' : 'Preview วันที่' }}
          </button>
        </div>
      </div>

      <!-- Step 3: Calendar Preview -->
      <Transition name="slide-down">
        <div v-if="previewData" class="card">
          <h3 class="text-sm font-semibold text-navy mb-3">3. ผลลัพธ์</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="p-3 bg-green-50 rounded-lg text-center">
              <div class="text-2xl font-bold text-green-600">{{ previewData.total_days }}</div>
              <div class="text-xs text-gray-500">วันที่จะสร้าง</div>
            </div>
            <div class="p-3 bg-red-50 rounded-lg text-center">
              <div class="text-2xl font-bold text-red-500">{{ previewData.total_skipped }}</div>
              <div class="text-xs text-gray-500">วันที่ข้าม</div>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg text-center">
              <div class="text-2xl font-bold text-blue-600">{{ previewData.employee_count }}</div>
              <div class="text-xs text-gray-500">พนักงาน</div>
            </div>
            <div class="p-3 bg-yellow-50 rounded-lg text-center">
              <div class="text-2xl font-bold text-yellow-600">{{ previewData.existing_count }}</div>
              <div class="text-xs text-gray-500">รายการเดิม (จะอัพเดท)</div>
            </div>
          </div>

          <!-- Calendar -->
          <div class="mb-4">
            <h4 class="text-xs font-semibold text-gray-600 mb-2">วันที่จะสร้างกะ (สีเขียว) / วันที่ข้าม (สีแดง)</h4>
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
              <div class="font-semibold text-gray-500 py-1">อา</div>
              <div class="font-semibold text-gray-500 py-1">จ</div>
              <div class="font-semibold text-gray-500 py-1">อ</div>
              <div class="font-semibold text-gray-500 py-1">พ</div>
              <div class="font-semibold text-gray-500 py-1">พฤ</div>
              <div class="font-semibold text-gray-500 py-1">ศ</div>
              <div class="font-semibold text-gray-500 py-1">ส</div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
              <template v-for="n in firstDayOfWeek" :key="'empty-'+n">
                <div></div>
              </template>
              <template v-for="day in daysInRange" :key="day">
                <div class="py-1 rounded" :class="getDayClass(day)">
                  {{ day }}
                </div>
              </template>
            </div>
          </div>

          <!-- Skipped dates -->
          <div v-if="previewData.dates_skipped.length > 0" class="mb-4">
            <h4 class="text-xs font-semibold text-gray-600 mb-2">วันที่ข้าม ({{ previewData.dates_skipped.length }} วัน)</h4>
            <div class="flex flex-wrap gap-1">
              <span v-for="s in previewData.dates_skipped" :key="s.date"
                    class="px-2 py-0.5 bg-red-50 text-red-600 rounded text-[10px]">
                {{ formatDateShort(s.date) }} — {{ s.reason }}
              </span>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="previewData = null" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
              แก้ไข
            </button>
            <button @click="autoAssign" :disabled="saving" class="btn-primary text-sm">
              {{ saving ? 'กำลังมอบหมาย...' : 'ยืนยันมอบหมายกะ' }}
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const previewing = ref(false)
const employees = ref([])
const shifts = ref([])
const companies = ref([])
const previewData = ref(null)

const form = reactive({
  emp_ids: [],
  companyFilter: '',
  search: '',
  shift_code: '',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  skip_sunday: true,
  skip_holiday: true,
})

const filteredEmployees = computed(() => {
  if (!form.search) return employees.value
  const q = form.search.toLowerCase()
  return employees.value.filter(e =>
    (e.name || '').toLowerCase().includes(q) ||
    (e.employee_code || '').toLowerCase().includes(q)
  )
})

const isAllSelected = computed(() => {
  return filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => form.emp_ids.includes(e.id))
})

const firstDayOfWeek = computed(() => {
  if (!form.start_date) return 0
  return new Date(form.start_date).getDay()
})

const daysInRange = computed(() => {
  if (!form.start_date || !form.end_date) return 0
  const start = new Date(form.start_date)
  const end = new Date(form.end_date)
  return Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
})

function codeFromGroup(group) {
  return 'WC' + String(group + 1).padStart(4, '0')
}

function getShiftTime(code) {
  const s = shifts.value.find(sh => codeFromGroup(sh.group_number) === code)
  if (!s) return ''
  return `${s.start_time?.substring(0,5)} - ${s.end_time?.substring(0,5)} (${s.work_hours} ชม.${s.is_overnight ? ' ข้ามคืน' : ''})`
}

function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getDate()}/${d.getMonth() + 1}`
}

function getDayClass(day) {
  const date = new Date(form.start_date)
  date.setDate(date.getDate() + day - 1)
  const dateStr = date.toISOString().slice(0, 10)

  if (previewData.value?.dates_to_create?.includes(dateStr)) {
    return 'bg-green-100 text-green-700 font-medium'
  }
  if (previewData.value?.dates_skipped?.find(s => s.date === dateStr)) {
    return 'bg-red-50 text-red-400'
  }
  return 'text-gray-600'
}

function toggleEmp(empId) {
  const idx = form.emp_ids.indexOf(empId)
  if (idx === -1) {
    form.emp_ids = [...form.emp_ids, empId]
  } else {
    form.emp_ids = form.emp_ids.filter(id => id !== empId)
  }
  previewData.value = null
}

function toggleAll() {
  if (isAllSelected.value) {
    form.emp_ids = []
  } else {
    form.emp_ids = filteredEmployees.value.map(e => e.id)
  }
  previewData.value = null
}

async function loadEmployees() {
  loading.value = true
  try {
    const params = { per_page: 9999, shift_only: true }
    if (form.companyFilter) params.company_id = form.companyFilter
    const [empRes, shiftRes, compRes] = await Promise.all([
      api.get('/api/employees', { params }),
      api.get('/api/shift-assignments', { params: form.companyFilter ? { company_id: form.companyFilter } : {} }),
      api.get('/api/companies'),
    ])
    const emps = empRes.data.data?.data || empRes.data.data || []
    const shiftEmps = shiftRes.data.data?.employees || []
    // Merge: use employee data + current_shift from shift-assignments
    const shiftMap = {}
    shiftEmps.forEach(e => { shiftMap[e.id] = e.current_shift })
    employees.value = emps.map(e => ({
      ...e,
      current_shift: shiftMap[e.id] || null,
    }))
    shifts.value = shiftRes.data.data?.shifts || []
    companies.value = compRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function autoPreview() {
  if (form.emp_ids.length === 0) { alert('กรุณาเลือกพนักงาน'); return }
  if (!form.shift_code) { alert('กรุณาเลือกกะ'); return }
  if (!form.start_date || !form.end_date) { alert('กรุณากรอกวันที่'); return }
  previewing.value = true
  try {
    const res = await api.post('/api/shift-assignments/auto-preview', {
      emp_ids: form.emp_ids,
      shift_code: form.shift_code,
      start_date: form.start_date,
      end_date: form.end_date,
      skip_sunday: form.skip_sunday,
      skip_holiday: form.skip_holiday,
    })
    previewData.value = res.data.data
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    previewing.value = false
  }
}

async function autoAssign() {
  saving.value = true
  try {
    await api.post('/api/shift-assignments/auto-assign', {
      emp_ids: form.emp_ids,
      shift_code: form.shift_code,
      start_date: form.start_date,
      end_date: form.end_date,
      skip_sunday: form.skip_sunday,
      skip_holiday: form.skip_holiday,
    })
    previewData.value = null
    form.emp_ids = []
    alert('มอบหมายกะเรียบร้อย')
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

onMounted(loadEmployees)
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
