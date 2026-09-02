<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">มอบหมาย OT บังคับ</h1>
          <p class="text-gray-500">พนักงานประจำ (Day) — มอบหมาย OT ตามช่วงเวลา</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 border-b border-gray-200">
        <button @click="mode = 'manual'"
                :class="mode === 'manual' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium transition-colors">
          มอบหมายรายวัน
        </button>
        <button @click="mode = 'auto'"
                :class="mode === 'auto' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium transition-colors">
          มอบหมายอัตโนมัติ (รายเดือน)
        </button>
      </div>

      <!-- ==================== MODE: MANUAL ==================== -->
      <template v-if="mode === 'manual'">
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

        <!-- Batch Assign Panel -->
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
          <!-- Employee Table -->
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

          <!-- Existing Assignments -->
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
      </template>

      <!-- ==================== MODE: AUTO ==================== -->
      <template v-if="mode === 'auto'">
        <!-- Step 1: Select Employees -->
        <div class="card">
          <h3 class="text-sm font-semibold text-navy mb-3">1. เลือกพนักงาน</h3>
          <div class="flex flex-col md:flex-row gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
              <select v-model="autoForm.companyFilter" class="input-field" @change="loadAutoEmployees">
                <option value="">ทุกบริษัท</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
              <input v-model="autoForm.search" type="text" class="input-field" placeholder="ชื่อ/รหัสพนักงาน..." />
            </div>
          </div>
          <div v-if="autoLoading" class="text-center py-4">
            <div class="w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>
          <div v-else class="max-h-64 overflow-y-auto border rounded-lg">
            <table class="w-full">
              <thead class="sticky top-0 bg-gray-50">
                <tr>
                  <th class="text-center px-3 py-2 w-10">
                    <input type="checkbox" :checked="isAutoAllSelected" @change="toggleAutoAll" class="rounded" />
                  </th>
                  <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">พนักงาน</th>
                  <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">เวลาเข้างาน</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="emp in autoFilteredEmployees" :key="emp.id"
                    class="hover:bg-gray-50 cursor-pointer"
                    :class="autoForm.emp_ids.includes(emp.id) ? 'bg-blue-50' : ''"
                    @click="toggleAutoEmp(emp.id)">
                  <td class="text-center px-3 py-2" @click.stop>
                    <input type="checkbox" :checked="autoForm.emp_ids.includes(emp.id)" @change="toggleAutoEmp(emp.id)" class="rounded" />
                  </td>
                  <td class="px-3 py-2">
                    <div class="text-sm font-medium text-navy">{{ emp.name }}</div>
                    <div class="text-[10px] text-gray-400">{{ emp.employee_code }}</div>
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-600">{{ emp.company_name }}</td>
                  <td class="px-3 py-2 text-sm text-gray-500">{{ emp.work_hours || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mt-2 text-xs text-gray-500">
            เลือกแล้ว {{ autoForm.emp_ids.length }} คน
          </div>
        </div>

        <!-- Step 2: Settings -->
        <div class="card">
          <h3 class="text-sm font-semibold text-navy mb-3">2. ตั้งค่าเวลา</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่ม *</label>
              <input v-model="autoForm.start_date" type="date" class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">วันที่สิ้นสุด *</label>
              <input v-model="autoForm.end_date" type="date" class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเริ่ม OT *</label>
              <input v-model="autoForm.start_time" type="time" class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เวลาสิ้นสุด *</label>
              <input v-model="autoForm.end_time" type="time" class="input-field" />
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
              <input v-model="autoForm.reason" type="text" class="input-field" placeholder="เหตุผล..." />
            </div>
            <div class="flex items-end gap-4">
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" v-model="autoForm.skip_sunday" class="rounded" />
                ข้ามวันอาทิตย์
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" v-model="autoForm.skip_holiday" class="rounded" />
                ข้ามวันหยุดบริษัท
              </label>
            </div>
          </div>
          <div class="mt-4 flex gap-2">
            <button @click="autoPreview" :disabled="autoPreviewing || autoForm.emp_ids.length === 0"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 disabled:opacity-50">
              {{ autoPreviewing ? 'กำลังคำนวณ...' : 'Preview วันที่' }}
            </button>
          </div>
        </div>

        <!-- Step 3: Calendar Preview -->
        <Transition name="slide-down">
          <div v-if="autoPreviewData" class="card">
            <h3 class="text-sm font-semibold text-navy mb-3">3. ผลลัพธ์</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
              <div class="p-3 bg-green-50 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">{{ autoPreviewData.total_days }}</div>
                <div class="text-xs text-gray-500">วันที่จะสร้าง</div>
              </div>
              <div class="p-3 bg-red-50 rounded-lg text-center">
                <div class="text-2xl font-bold text-red-500">{{ autoPreviewData.total_skipped }}</div>
                <div class="text-xs text-gray-500">วันที่ข้าม</div>
              </div>
              <div class="p-3 bg-blue-50 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600">{{ autoPreviewData.employee_count }}</div>
                <div class="text-xs text-gray-500">พนักงาน</div>
              </div>
              <div class="p-3 bg-yellow-50 rounded-lg text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ autoPreviewData.existing_count }}</div>
                <div class="text-xs text-gray-500">รายการเดิม (จะอัพเดท)</div>
              </div>
            </div>

            <!-- Calendar -->
            <div class="mb-4">
              <h4 class="text-xs font-semibold text-gray-600 mb-2">วันที่จะสร้าง OT (สีเขียว) / วันที่ข้าม (สีแดง)</h4>
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
                <!-- Empty cells for first week alignment -->
                <template v-for="n in autoFirstDayOfWeek" :key="'empty-'+n">
                  <div></div>
                </template>
                <template v-for="day in autoDaysInMonth" :key="day">
                  <div class="py-1 rounded"
                       :class="getAutoDayClass(day)">
                    {{ day }}
                  </div>
                </template>
              </div>
            </div>

            <!-- Skipped dates -->
            <div v-if="autoPreviewData.dates_skipped.length > 0" class="mb-4">
              <h4 class="text-xs font-semibold text-gray-600 mb-2">วันที่ข้าม ({{ autoPreviewData.dates_skipped.length }} วัน)</h4>
              <div class="flex flex-wrap gap-1">
                <span v-for="s in autoPreviewData.dates_skipped" :key="s.date"
                      class="px-2 py-0.5 bg-red-50 text-red-600 rounded text-[10px]">
                  {{ formatDateShort(s.date) }} — {{ s.reason }}
                </span>
              </div>
            </div>

            <div class="flex gap-2">
              <button @click="autoPreviewData = null" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                แก้ไข
              </button>
              <button @click="autoAssign" :disabled="saving" class="btn-primary text-sm">
                {{ saving ? 'กำลังมอบหมาย...' : 'ยืนยันมอบหมาย OT บังคับ' }}
              </button>
            </div>
          </div>
        </Transition>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const autoLoading = ref(false)
const autoPreviewing = ref(false)
const mode = ref('manual')
const employees = ref([])
const autoEmployees = ref([])
const assignments = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const selectedEmpIds = ref([])
const searchQuery = ref('')
const autoPreviewData = ref(null)

const batchForm = reactive({
  ot_date: new Date().toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  reason: '',
})

const autoForm = reactive({
  emp_ids: [],
  companyFilter: '',
  search: '',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  reason: '',
  skip_sunday: true,
  skip_holiday: true,
})

// Manual mode
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

// Auto mode
const autoFilteredEmployees = computed(() => {
  let list = autoEmployees.value
  if (autoForm.search) {
    const q = autoForm.search.toLowerCase()
    list = list.filter(e =>
      (e.name || '').toLowerCase().includes(q) ||
      (e.employee_code || '').toLowerCase().includes(q)
    )
  }
  return list
})

const isAutoAllSelected = computed(() => {
  return autoFilteredEmployees.value.length > 0 &&
    autoFilteredEmployees.value.every(e => autoForm.emp_ids.includes(e.id))
})

const autoFirstDayOfWeek = computed(() => {
  if (!autoForm.start_date) return 0
  const d = new Date(autoForm.start_date)
  return d.getDay()
})

const autoDaysInMonth = computed(() => {
  if (!autoForm.start_date || !autoForm.end_date) return 0
  const start = new Date(autoForm.start_date)
  const end = new Date(autoForm.end_date)
  const diffTime = Math.abs(end - start)
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
  const d = new Date(dateStr)
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear() + 543}`
}

function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getDate()}/${d.getMonth() + 1}`
}

function getAssignment(empId) {
  return assignments.value.find(a => a.emp_id === empId && a.ot_date === selectedDate.value)
}

function getAutoDayClass(day) {
  const date = new Date(autoForm.start_date)
  date.setDate(date.getDate() + day - 1)
  const dateStr = date.toISOString().slice(0, 10)

  if (autoPreviewData.value?.dates_to_create?.includes(dateStr)) {
    return 'bg-green-100 text-green-700 font-medium'
  }
  if (autoPreviewData.value?.dates_skipped?.find(s => s.date === dateStr)) {
    return 'bg-red-50 text-red-400'
  }
  return 'text-gray-600'
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

function toggleAutoEmp(empId) {
  const idx = autoForm.emp_ids.indexOf(empId)
  if (idx === -1) {
    autoForm.emp_ids = [...autoForm.emp_ids, empId]
  } else {
    autoForm.emp_ids = autoForm.emp_ids.filter(id => id !== empId)
  }
  autoPreviewData.value = null
}

function toggleAutoAll() {
  if (isAutoAllSelected.value) {
    autoForm.emp_ids = []
  } else {
    autoForm.emp_ids = autoFilteredEmployees.value.map(e => e.id)
  }
  autoPreviewData.value = null
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

async function loadAutoEmployees() {
  autoLoading.value = true
  try {
    const params = { per_page: 9999 }
    if (autoForm.companyFilter) params.company_id = autoForm.companyFilter
    const res = await api.get('/api/employees', { params })
    const allEmps = res.data.data?.data || res.data.data || []
    // Day employees = employees WITHOUT employee_shifts record
    autoEmployees.value = allEmps.filter(e => !e.work_shifts || e.work_shifts.length === 0)
      .map(e => ({
        ...e,
        work_hours: e.office_location?.work_start_time?.substring(0,5) + ' - ' + e.office_location?.work_end_time?.substring(0,5) || '-',
      }))
  } catch (e) {
    console.error(e)
  } finally {
    autoLoading.value = false
  }
}

async function autoPreview() {
  if (autoForm.emp_ids.length === 0) { alert('กรุณาเลือกพนักงาน'); return }
  if (!autoForm.start_date || !autoForm.end_date) { alert('กรุณากรอกวันที่'); return }
  autoPreviewing.value = true
  try {
    const res = await api.post('/api/mandatory-ot/auto-preview', {
      emp_ids: autoForm.emp_ids,
      start_date: autoForm.start_date,
      end_date: autoForm.end_date,
      skip_sunday: autoForm.skip_sunday,
      skip_holiday: autoForm.skip_holiday,
    })
    autoPreviewData.value = res.data.data
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    autoPreviewing.value = false
  }
}

async function autoAssign() {
  if (!autoForm.start_time || !autoForm.end_time) { alert('กรุณากรอกเวลา OT'); return }
  saving.value = true
  try {
    await api.post('/api/mandatory-ot/auto-assign', {
      emp_ids: autoForm.emp_ids,
      start_date: autoForm.start_date,
      end_date: autoForm.end_date,
      start_time: autoForm.start_time,
      end_time: autoForm.end_time,
      reason: autoForm.reason || null,
      skip_sunday: autoForm.skip_sunday,
      skip_holiday: autoForm.skip_holiday,
    })
    autoPreviewData.value = null
    autoForm.emp_ids = []
    alert('มอบหมาย OT บังคับเรียบร้อย')
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
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

onMounted(() => {
  loadData()
  loadAutoEmployees()
})
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
