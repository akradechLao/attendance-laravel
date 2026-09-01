<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">จัดการกะรายเดือน</h1>
          <p class="text-gray-500">กำหนดกะการทำงานสำหรับพนักงานแต่ละคน</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="card">
        <div class="flex flex-col md:flex-row gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เดือน</label>
            <input v-model="selectedMonth" type="month" class="input-field" @change="loadData" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="selectedCompany" class="input-field" @change="onCompanyChange">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ฝ่าย</label>
            <select v-model="selectedDivision" class="input-field" @change="onDivisionChange">
              <option value="">ทุกฝ่าย</option>
              <option v-for="d in divisions" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">แผนก</label>
            <select v-model="selectedDepartment" class="input-field" @change="loadData">
              <option value="">ทุกแผนก</option>
              <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input v-model="searchQuery" type="text" class="input-field" placeholder="ชื่อหรือรหัส..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <!-- Shift quick-assign cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          <button
            v-for="shift in shifts"
            :key="shift.id"
            @click="quickAssignShift = shift.id"
            class="card p-3 text-left transition-all hover:shadow-md"
            :class="quickAssignShift === shift.id ? 'ring-2 ring-blue-500 bg-blue-50' : ''"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-bold text-navy">กะ {{ shift.group_number }}</span>
              <span class="text-[10px] text-gray-400">{{ getAssignedCount(shift.group_number) }} คน</span>
            </div>
            <p class="text-sm font-semibold text-blue-600">{{ shift.start_time }}-{{ shift.end_time }}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ shift.is_overnight ? 'ข้ามวัน' : '' }} {{ shift.work_hours }}ชม.</p>
          </button>
          <button
            @click="quickAssignShift = ''"
            class="card p-3 text-left transition-all hover:shadow-md"
            :class="!quickAssignShift ? 'ring-2 ring-red-500 bg-red-50' : ''"
          >
            <span class="text-sm font-bold text-red-600">ลบกะ</span>
            <p class="text-[10px] text-gray-400 mt-0.5">นำพนักงานออก</p>
          </button>
        </div>

        <!-- Batch action bar -->
        <div v-if="selectedEmpIds.length > 0" class="card p-3 bg-blue-50 border-blue-200 flex items-center justify-between">
          <span class="text-sm font-medium text-blue-700">เลือก {{ selectedEmpIds.length }} คน</span>
          <div class="flex gap-2">
            <select v-model="batchShiftId" class="input-field text-sm py-1">
              <option value="">เลือกกะ</option>
              <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                กะ {{ shift.group_number }} ({{ shift.start_time }}-{{ shift.end_time }})
              </option>
            </select>
            <button @click="batchAssign" :disabled="!batchShiftId" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50">
              มอบหมาย
            </button>
            <button v-if="quickAssignShift" @click="quickBatchAssign" class="px-3 py-1 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
              มอบหมายกะ {{ quickAssignShift }}
            </button>
            <button @click="selectedEmpIds = []" class="px-3 py-1 bg-gray-200 text-gray-600 text-sm rounded-lg hover:bg-gray-300">ยกเลิก</button>
          </div>
        </div>

        <!-- Employee table -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-center px-4 py-3 w-10">
                    <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="rounded" />
                  </th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">ฝ่าย</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">แผนก</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">กะปัจจุบัน</th>
                  <th class="text-center px-4 py-3 text-sm font-semibold text-gray-600">มอบหมายกะ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="emp in filteredEmployees" :key="emp.id" class="hover:bg-gray-50"
                  :class="selectedEmpIds.includes(emp.id) ? 'bg-blue-50' : ''"
                >
                  <td class="text-center px-4 py-3">
                    <input type="checkbox" :checked="selectedEmpIds.includes(emp.id)" @change="toggleSelect(emp)" class="rounded" />
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                        <span class="text-blue-600 text-xs font-semibold">{{ emp.name.charAt(0) }}</span>
                      </div>
                      <span class="font-medium text-navy text-sm">{{ emp.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ emp.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ emp.division || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ emp.department || '-' }}</td>
                  <td class="px-4 py-3">
                    <span v-if="emp.current_shift" class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                      กะ {{ emp.current_shift.group_number }} ({{ emp.current_shift.start_time }}-{{ emp.current_shift.end_time }})
                    </span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">ไม่มีกะ</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <select
                      :value="emp.current_shift?.id || ''"
                      @change="e => quickAssign(emp, e.target.value)"
                      class="text-xs border rounded-lg px-2 py-1"
                    >
                      <option value="">ไม่มีกะ</option>
                      <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                        กะ {{ shift.group_number }}
                      </option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="filteredEmployees.length === 0" class="text-center py-8 text-gray-400 text-sm">
            ไม่พบพนักงาน
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const employees = ref([])
const companies = ref([])
const shifts = ref([])
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedCompany = ref('')
const selectedDivision = ref('')
const selectedDepartment = ref('')
const searchQuery = ref('')
const selectedEmpIds = ref([])
const batchShiftId = ref('')
const quickAssignShift = ref('')

const divisions = computed(() => {
  const set = new Set(employees.value.map(e => e.division).filter(Boolean))
  return [...set].sort()
})

const departments = computed(() => {
  const filtered = selectedDivision.value
    ? employees.value.filter(e => e.division === selectedDivision.value)
    : employees.value
  const set = new Set(filtered.map(e => e.department).filter(Boolean))
  return [...set].sort()
})

const filteredEmployees = computed(() => {
  let list = employees.value
  if (selectedDivision.value) list = list.filter(e => e.division === selectedDivision.value)
  if (selectedDepartment.value) list = list.filter(e => e.department === selectedDepartment.value)
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(e => e.name.toLowerCase().includes(q) || e.employee_code.toLowerCase().includes(q))
  }
  return list
})

const isAllSelected = computed(() => {
  return filteredEmployees.value.length > 0 && filteredEmployees.value.every(e => selectedEmpIds.value.includes(e.id))
})

function getAssignedCount(groupNumber) {
  return employees.value.filter(e => e.current_shift?.group_number === groupNumber).length
}

function toggleSelect(emp) {
  const idx = selectedEmpIds.value.indexOf(emp.id)
  if (idx === -1) {
    selectedEmpIds.value.push(emp.id)
  } else {
    selectedEmpIds.value.splice(idx, 1)
  }
}

function toggleAll() {
  if (isAllSelected.value) {
    const ids = new Set(filteredEmployees.value.map(e => e.id))
    selectedEmpIds.value = selectedEmpIds.value.filter(id => !ids.has(id))
  } else {
    const ids = new Set(selectedEmpIds.value)
    filteredEmployees.value.forEach(e => { if (!ids.has(e.id)) selectedEmpIds.value.push(e.id) })
  }
}

function onCompanyChange() {
  selectedDivision.value = ''
  selectedDepartment.value = ''
  loadData()
}

function onDivisionChange() {
  selectedDepartment.value = ''
}

async function loadData() {
  loading.value = true
  try {
    const [locRes, compRes] = await Promise.all([
      api.get('/api/shift-assignments', { params: { month: selectedMonth.value, company_id: selectedCompany.value } }),
      api.get('/api/companies'),
    ])
    employees.value = locRes.data.data?.employees || []
    shifts.value = locRes.data.data?.shifts || []
    companies.value = compRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function quickAssign(emp, shiftId) {
  const startDate = selectedMonth.value + '-01'
  const endDate = new Date(new Date(startDate).setMonth(new Date(startDate).getMonth() + 1, 0)).toISOString().slice(0, 10)
  try {
    if (!shiftId) {
      // Remove shift
      await api.delete('/api/shift-assignments', { data: { employee_ids: [emp.id] } })
    } else {
      await api.post('/api/shift-assignments', {
        employee_ids: [emp.id],
        shift_id: shiftId,
        start_date: startDate,
        end_date: endDate,
      })
    }
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  }
}

async function batchAssign() {
  if (selectedEmpIds.value.length === 0 || !batchShiftId.value) return
  const startDate = selectedMonth.value + '-01'
  const endDate = new Date(new Date(startDate).setMonth(new Date(startDate).getMonth() + 1, 0)).toISOString().slice(0, 10)
  try {
    await api.post('/api/shift-assignments', {
      employee_ids: selectedEmpIds.value,
      shift_id: batchShiftId.value,
      start_date: startDate,
      end_date: endDate,
    })
    selectedEmpIds.value = []
    batchShiftId.value = ''
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  }
}

async function quickBatchAssign() {
  if (selectedEmpIds.value.length === 0 || !quickAssignShift.value) return
  // Find shift ID from group number
  const shift = shifts.value.find(s => String(s.group_number) === String(quickAssignShift.value))
  if (!shift) return
  batchShiftId.value = shift.id
  await batchAssign()
  quickAssignShift.value = ''
}

onMounted(loadData)
</script>
