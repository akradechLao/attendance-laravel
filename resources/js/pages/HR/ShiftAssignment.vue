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
            <select v-model="selectedCompany" class="input-field" @change="loadData">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <!-- Shift summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="shift in shifts" :key="shift.id" class="card p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-navy">กะที่ {{ shift.group_number }}</span>
              <span class="text-xs text-gray-500">{{ getAssignedCount(shift.group_number) }} คน</span>
            </div>
            <p class="text-lg font-bold text-blue-600">{{ shift.start_time }} - {{ shift.end_time }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ shift.is_overnight ? 'ข้ามวัน' : 'ไม่ข้ามวัน' }} | {{ shift.work_hours }} ชม.</p>
          </div>
        </div>

        <!-- Assign form -->
        <div class="card p-4">
          <h3 class="font-semibold text-navy mb-3">มอบหมายกะ</h3>
          <div class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 mb-1">เลือกพนักงาน</label>
              <div class="relative">
                <input
                  v-model="empSearch"
                  type="text"
                  class="input-field w-full"
                  placeholder="ค้นหาชื่อหรือรหัส..."
                />
                <div v-if="empSearch && filteredUnassigned.length > 0" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                  <button
                    v-for="emp in filteredUnassigned"
                    :key="emp.id"
                    @click="toggleSelect(emp)"
                    class="w-full text-left px-4 py-2 hover:bg-blue-50 text-sm flex items-center gap-2"
                  >
                    <input type="checkbox" :checked="selectedEmpIds.includes(emp.id)" class="rounded" />
                    <span>{{ emp.employee_code }} {{ emp.name }}</span>
                  </button>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">กะที่ต้องการ</label>
              <select v-model="assignShiftId" class="input-field">
                <option value="">เลือกกะ</option>
                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                  กะ {{ shift.group_number }} ({{ shift.start_time }}-{{ shift.end_time }})
                </option>
              </select>
            </div>
            <button
              @click="doAssign"
              :disabled="selectedEmpIds.length === 0 || !assignShiftId"
              class="btn-primary disabled:opacity-50"
            >
              มอบหมาย ({{ selectedEmpIds.length }} คน)
            </button>
          </div>
        </div>

        <!-- Employee table -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">กะปัจจุบัน</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">เวลาเข้างาน</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="emp in employees" :key="emp.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="text-blue-600 text-xs font-semibold">{{ emp.name.charAt(0) }}</span>
                      </div>
                      <span class="font-medium text-navy text-sm">{{ emp.name }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-600">{{ emp.employee_code }}</td>
                  <td class="px-6 py-4 text-sm text-gray-600">{{ emp.company_name }}</td>
                  <td class="px-6 py-4">
                    <span v-if="emp.current_shift" class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                      กะ {{ emp.current_shift.group_number }}
                    </span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">ไม่มีกะ</span>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-600">
                    {{ emp.current_shift ? emp.current_shift.start_time + ' - ' + emp.current_shift.end_time : '-' }}
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button
                      v-if="emp.current_shift"
                      @click="removeShift(emp)"
                      class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"
                    >
                      ลบกะ
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
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
const empSearch = ref('')
const selectedEmpIds = ref([])
const assignShiftId = ref('')

const filteredUnassigned = computed(() => {
  if (!empSearch.value) return []
  const q = empSearch.value.toLowerCase()
  return employees.value.filter(e =>
    e.name.toLowerCase().includes(q) || e.employee_code.toLowerCase().includes(q)
  ).slice(0, 20)
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

async function doAssign() {
  if (selectedEmpIds.value.length === 0 || !assignShiftId.value) return
  try {
    const startDate = selectedMonth.value + '-01'
    const endDate = new Date(new Date(startDate).setMonth(new Date(startDate).getMonth() + 1, 0)).toISOString().slice(0, 10)
    await api.post('/api/shift-assignments', {
      employee_ids: selectedEmpIds.value,
      shift_id: assignShiftId.value,
      start_date: startDate,
      end_date: endDate,
    })
    selectedEmpIds.value = []
    assignShiftId.value = ''
    empSearch.value = ''
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  }
}

async function removeShift(emp) {
  if (!confirm(`ลบกะของ ${emp.name} ใช่หรือไม่?`)) return
  try {
    await api.delete('/api/shift-assignments', { data: { employee_ids: [emp.id] } })
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

onMounted(loadData)
</script>
