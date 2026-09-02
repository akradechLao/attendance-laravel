<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">มอบหมาย OT บังคับ</h1>
          <p class="text-gray-500">เลือกพนักงานแล้วกดมอบหมาย OT</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="card">
        <div class="flex flex-col md:flex-row gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
            <input v-model="selectedDate" type="date" class="input-field" @change="loadData" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="selectedCompany" class="input-field" @change="loadData">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหาพนักงาน</label>
            <input v-model="searchQuery" type="text" class="input-field" placeholder="ชื่อ/รหัสพนักงาน..." />
          </div>
        </div>
      </div>

      <!-- Batch Assign Panel (shown when employees are selected) -->
      <Transition name="slide-down">
        <div v-if="selectedEmpIds.length > 0" class="card bg-blue-50 border-blue-200">
          <div class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
              <label class="block text-sm font-semibold text-navy mb-2">
                เลือกแล้ว {{ selectedEmpIds.length }} คน
              </label>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">วันที่ *</label>
                  <input v-model="batchForm.ot_date" type="date" class="input-field text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">เวลาเริ่ม *</label>
                  <input v-model="batchForm.start_time" type="time" class="input-field text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">เวลาสิ้นสุด *</label>
                  <input v-model="batchForm.end_time" type="time" class="input-field text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">เหตุผล</label>
                  <input v-model="batchForm.reason" type="text" class="input-field text-sm" placeholder="เหตุผล..." />
                </div>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="selectedEmpIds = []" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                ยกเลิก
              </button>
              <button @click="batchAssign" :disabled="saving" class="btn-primary text-sm">
                {{ saving ? 'กำลังมอบหมาย...' : 'มอบหมาย OT บังคับ' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <template v-else>
        <!-- Employee Table with Checkboxes -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-center px-4 py-3 w-10">
                    <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="rounded" />
                  </th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">พนักงาน</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-sm font-semibold text-gray-600">แผนก</th>
                  <th class="text-center px-4 py-3 text-sm font-semibold text-gray-600">สถานะ OT</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="emp in filteredEmployees" :key="emp.id"
                    class="hover:bg-gray-50 cursor-pointer"
                    :class="selectedEmpIds.includes(emp.id) ? 'bg-blue-50' : ''"
                    @click="toggleEmp(emp.id)">
                  <td class="text-center px-4 py-3" @click.stop>
                    <input type="checkbox" :checked="selectedEmpIds.includes(emp.id)" @change="toggleEmp(emp.id)" class="rounded" />
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="text-blue-600 font-semibold text-xs">{{ (emp.name || '').charAt(0) }}</span>
                      </div>
                      <div>
                        <div class="text-sm font-medium text-navy">{{ emp.name }}</div>
                        <div class="text-[10px] text-gray-400">{{ emp.employee_code }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ emp.company?.name || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ emp.department || '-' }}</td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="getAssignment(emp.id)" :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      getAssignment(emp.id).status === 'assigned' ? 'bg-blue-100 text-blue-700' :
                      getAssignment(emp.id).status === 'completed' ? 'bg-green-100 text-green-700' :
                      'bg-gray-100 text-gray-500'
                    ]">
                      {{ getAssignment(emp.id).status === 'assigned' ? 'มอบหมายแล้ว' : getAssignment(emp.id).status === 'completed' ? 'เสร็จสิ้น' : 'ยกเลิก' }}
                    </span>
                    <span v-else class="text-gray-300 text-xs">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="filteredEmployees.length === 0" class="text-center py-8 text-gray-400">
            ไม่พบพนักงาน
          </div>
        </div>

        <!-- Existing Assignments for selected date -->
        <div v-if="assignments.length > 0" class="card">
          <h3 class="text-sm font-semibold text-navy mb-3">
            OT บังคับวันที่ {{ formatDate(selectedDate) }} ({{ assignments.length }} ราย)
          </h3>
          <div class="space-y-2">
            <div v-for="a in assignments" :key="a.id"
                 class="flex items-center justify-between p-3 rounded-lg border"
                 :class="a.status === 'assigned' ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-blue-600 font-semibold text-xs">{{ (a.employee?.name || '').charAt(0) }}</span>
                </div>
                <div>
                  <div class="text-sm font-medium text-navy">{{ a.employee?.name }}</div>
                  <div class="text-[10px] text-gray-400">{{ a.start_time?.substring(0,5) }} - {{ a.end_time?.substring(0,5) }} | {{ a.reason || '-' }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-medium',
                  a.status === 'assigned' ? 'bg-blue-100 text-blue-700' :
                  a.status === 'completed' ? 'bg-green-100 text-green-700' :
                  'bg-gray-100 text-gray-500'
                ]">
                  {{ a.status === 'assigned' ? 'มอบหมายแล้ว' : a.status === 'completed' ? 'เสร็จสิ้น' : 'ยกเลิก' }}
                </span>
                <button v-if="a.status === 'assigned'" @click="cancelAssignment(a)"
                        class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100">
                  ยกเลิก
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const employees = ref([])
const assignments = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const selectedEmpIds = ref([])
const searchQuery = ref('')

const batchForm = reactive({
  ot_date: new Date().toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  reason: '',
})

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return employees.value
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter(e =>
    (e.name || '').toLowerCase().includes(q) ||
    (e.employee_code || '').toLowerCase().includes(q)
  )
})

const isAllSelected = computed(() => {
  return filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => selectedEmpIds.value.includes(e.id))
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
  const d = new Date(dateStr)
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear() + 543}`
}

function getAssignment(empId) {
  return assignments.value.find(a => a.emp_id === empId && a.ot_date === selectedDate.value)
}

function toggleEmp(empId) {
  const idx = selectedEmpIds.value.indexOf(empId)
  if (idx === -1) {
    selectedEmpIds.value = [...selectedEmpIds.value, empId]
  } else {
    selectedEmpIds.value = selectedEmpIds.value.filter(id => id !== empId)
  }
}

function toggleAll() {
  if (isAllSelected.value) {
    selectedEmpIds.value = []
  } else {
    selectedEmpIds.value = filteredEmployees.value.map(e => e.id)
  }
}

async function loadData() {
  loading.value = true
  try {
    const [eRes, aRes, cRes] = await Promise.all([
      api.get('/api/employees', { params: { company_id: selectedCompany.value || undefined, per_page: 9999 } }),
      api.get('/api/mandatory-ot', { params: { date: selectedDate.value, company_id: selectedCompany.value || undefined } }),
      api.get('/api/companies'),
    ])
    employees.value = eRes.data.data?.data || eRes.data.data || []
    assignments.value = aRes.data.data || []
    companies.value = cRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function batchAssign() {
  if (!batchForm.ot_date || !batchForm.start_time || !batchForm.end_time) {
    alert('กรุณากรอกวันที่และเวลาให้ครบ')
    return
  }
  if (selectedEmpIds.value.length === 0) {
    alert('กรุณาเลือกพนักงานอย่างน้อย 1 คน')
    return
  }
  saving.value = true
  try {
    await api.post('/api/mandatory-ot/batch', {
      emp_ids: selectedEmpIds.value,
      ...batchForm,
    })
    selectedEmpIds.value = []
    batchForm.start_time = ''
    batchForm.end_time = ''
    batchForm.reason = ''
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function cancelAssignment(a) {
  if (!confirm(`ยกเลิก OT บังคับของ ${a.employee?.name} ใช่หรือไม่?`)) return
  try {
    await api.delete(`/api/mandatory-ot/${a.id}`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

onMounted(loadData)
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
