<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">จุดเช็คอิน/เช็คเอาท์ สำหรับพนักงาน</h1>
          <p class="text-gray-500">กำหนดตำแหน่งจุดอ้างอิง (ระยะรัศมี 200 เมตร) และจัดกลุ่มพนักงาน</p>
        </div>
        <button @click="openCreateForm" class="btn-primary">+ เพิ่มสถานที่</button>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <div v-for="company in companies" :key="company.id" class="space-y-3">
          <h2 class="text-lg font-semibold text-navy flex items-center gap-2">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" :style="companyStyle(company.code_prefix)">{{ company.code_prefix?.charAt(0) }}</span>
            {{ company.name }}
          </h2>

          <div v-if="getLocationsByCompany(company.id).length === 0" class="card text-sm text-gray-400 py-4 text-center">ยังไม่มีสถานที่</div>

          <div v-for="loc in getLocationsByCompany(company.id)" :key="loc.id" class="card p-4">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="font-semibold text-navy">{{ loc.name }}</h3>
                  <span v-if="loc.is_active" class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">เปิดใช้งาน</span>
                  <span v-else class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">ปิดใช้งาน</span>
                </div>
                <p v-if="loc.address" class="text-sm text-gray-500 mt-1">{{ loc.address }}</p>
                <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
                  <span>📍 {{ Number(loc.latitude).toFixed(6) }}, {{ Number(loc.longitude).toFixed(6) }}</span>
                  <span>🔵 รัศมี {{ loc.radius_meters }} ม.</span>
                  <span v-if="loc.work_start_time">🕐 {{ loc.work_start_time }} - {{ loc.work_end_time }}</span>
                  <span class="text-blue-600 font-medium">{{ loc.assigned_employees_count || 0 }} คน</span>
                </div>
              </div>
              <div class="flex gap-2 ml-4">
                <button @click="openAssignModal(loc)" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">จัดกลุ่มพนักงาน</button>
                <button @click="openPatternModal(loc)" class="px-3 py-1.5 text-xs font-medium bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100">รูปแบบกะ</button>
                <button @click="openEditForm(loc)" class="px-3 py-1.5 text-xs font-medium bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100">แก้ไข</button>
                <button @click="deleteLocation(loc)" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100">ลบ</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-bold text-navy mb-4">{{ editingId ? 'แก้ไขสถานที่' : 'เพิ่มสถานที่ใหม่' }}</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท *</label>
              <select v-model="form.company_id" class="input-field w-full" :disabled="editingId">
                <option value="">เลือกบริษัท</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานที่ *</label>
              <input v-model="form.name" type="text" class="input-field w-full" placeholder="เช่น สำนักงานใหญ่" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
              <input v-model="form.address" type="text" class="input-field w-full" placeholder="ที่อยู่ละเอียด" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้างาน</label>
                <input v-model="form.work_start_time" type="time" class="input-field w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเลิกงาน</label>
                <input v-model="form.work_end_time" type="time" class="input-field w-full" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">รัศมีเช็คอิน (เมตร) *</label>
              <input v-model.number="form.radius_meters" type="number" class="input-field w-full" min="10" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded" />
              <label for="is_active" class="text-sm text-gray-700">เปิดใช้งาน</label>
            </div>

            <!-- Map -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เลือกพิกัดจากแผนที่ (คลิกเพื่อกำหนดตำแหน่ง)</label>
              <div ref="mapContainer" class="w-full h-64 rounded-lg border border-gray-300 z-0"></div>
              <div class="flex gap-4 mt-2">
                <div class="flex-1">
                  <label class="text-xs text-gray-500">ละติจูด</label>
                  <input v-model="form.latitude" type="text" class="input-field w-full text-sm" readonly />
                </div>
                <div class="flex-1">
                  <label class="text-xs text-gray-500">ลองจิจูด</label>
                  <input v-model="form.longitude" type="text" class="input-field w-full text-sm" readonly />
                </div>
              </div>
            </div>

            <div class="flex gap-3 justify-end pt-2 border-t">
              <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="saveLocation" :disabled="saving" class="btn-primary text-sm">
                {{ saving ? 'กำลังบันทึก...' : (editingId ? 'บันทึก' : 'เพิ่ม') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Employees Modal -->
    <div v-if="showAssign" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showAssign = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-navy">จัดกลุ่มพนักงาน — {{ assignLocation?.name }}</h3>
            <button @click="showAssign = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
          </div>

          <!-- Assigned employees -->
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <h4 class="text-sm font-semibold text-navy">พนักงานที่ assigned ({{ assignedEmployees.length }} คน)</h4>
            </div>
            <div v-if="assignedEmployees.length === 0" class="text-sm text-gray-400 py-3 text-center border rounded-lg">ยังไม่มีพนักงานที่ assigned</div>
            <div v-else class="border rounded-lg divide-y max-h-48 overflow-y-auto">
              <div v-for="emp in assignedEmployees" :key="emp.id" class="flex items-center justify-between px-4 py-2">
                <div>
                  <span class="text-sm font-medium">{{ emp.name }}</span>
                  <span class="text-xs text-gray-500 ml-2">{{ emp.employee_code }}</span>
                  <span v-if="emp.division" class="text-xs text-gray-400 ml-2">({{ emp.division }})</span>
                </div>
                <button @click="removeEmployee(emp)" class="text-xs text-red-500 hover:text-red-700">ลบ</button>
              </div>
            </div>
          </div>

          <!-- Search and add employees -->
          <div>
            <h4 class="text-sm font-semibold text-navy mb-2">เพิ่มพนักงาน</h4>
            <input v-model="assignSearch" @input="searchUnassigned" type="text" class="input-field w-full mb-2" placeholder="ค้นหาชื่อ, รหัส, แผนก..." />
            <div v-if="unassignedEmployees.length === 0" class="text-sm text-gray-400 py-3 text-center border rounded-lg">ไม่พบพนักงาน</div>
            <div v-else class="border rounded-lg divide-y max-h-64 overflow-y-auto">
              <div v-for="emp in unassignedEmployees" :key="emp.id" class="flex items-center justify-between px-4 py-2 hover:bg-gray-50">
                <div>
                  <span class="text-sm font-medium">{{ emp.name }}</span>
                  <span class="text-xs text-gray-500 ml-2">{{ emp.employee_code }}</span>
                  <span v-if="emp.division" class="text-xs text-gray-400 ml-2">({{ emp.division }})</span>
                </div>
                <button @click="assignEmployee(emp)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">+ เพิ่ม</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Shift Pattern Modal -->
    <div v-if="showPattern" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showPattern = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-1">
            <h3 class="text-lg font-bold text-navy">รูปแบบกะประจำพื้นที่ — {{ patternLocation?.name }}</h3>
            <button @click="showPattern = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
          </div>
          <p class="text-xs text-gray-500 mb-4">กำหนดกะและวันที่ต้องเข้างานของพื้นที่นี้ไว้ล่วงหน้า ระบบจะจดจำและใช้สร้างตารางกะให้อัตโนมัติทุกเดือน โดยจะไม่ทับวันที่มีการมอบหมาย/คำขอปรับเปลี่ยนอยู่แล้ว</p>

          <!-- Existing patterns -->
          <div v-if="patterns.length === 0" class="text-sm text-gray-400 py-3 text-center border rounded-lg mb-4">ยังไม่มีรูปแบบกะ</div>
          <div v-else class="border rounded-lg divide-y mb-4">
            <div v-for="p in patterns" :key="p.id" class="flex items-center justify-between px-4 py-2">
              <div class="text-sm">
                <span class="font-medium text-navy">กะ {{ p.work_shift?.group_number }}</span>
                <span class="text-gray-500 ml-1">({{ p.work_shift?.start_time?.substring(0,5) }}-{{ p.work_shift?.end_time?.substring(0,5) }})</span>
                <span class="text-gray-400 ml-2">{{ dayLabels(p.days_of_week) }}</span>
                <span v-if="p.effective_start_date" class="text-gray-400 ml-2 text-xs">
                  ตั้งแต่ {{ p.effective_start_date }}{{ p.effective_end_date ? ' ถึง ' + p.effective_end_date : '' }}
                </span>
                <span v-if="!p.is_active" class="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-500">ปิดใช้งาน</span>
              </div>
              <button @click="deletePattern(p)" class="text-xs text-red-500 hover:text-red-700">ลบ</button>
            </div>
          </div>

          <!-- Add new pattern -->
          <div class="border-t pt-4">
            <h4 class="text-sm font-semibold text-navy mb-2">เพิ่มรูปแบบกะ</h4>
            <div class="space-y-3">
              <div>
                <label class="block text-xs text-gray-500 mb-1">กะ</label>
                <select v-model="patternForm.work_shift_id" class="input-field w-full text-sm">
                  <option value="">เลือกกะ</option>
                  <option v-for="s in workShifts" :key="s.id" :value="s.id">
                    กะ {{ s.group_number }} ({{ s.start_time }}-{{ s.end_time }}) {{ s.is_overnight ? 'ข้ามวัน' : '' }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">วันที่ต้องเข้างาน</label>
                <div class="flex flex-wrap gap-2">
                  <label v-for="d in weekDays" :key="d.value" class="flex items-center gap-1 text-xs border rounded-lg px-2 py-1 cursor-pointer"
                    :class="patternForm.days_of_week.includes(d.value) ? 'bg-purple-50 border-purple-300 text-purple-700' : 'text-gray-600'">
                    <input type="checkbox" class="rounded" :value="d.value" v-model="patternForm.days_of_week" />
                    {{ d.label }}
                  </label>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">มีผลตั้งแต่ (ไม่ระบุ = ทันที)</label>
                  <input v-model="patternForm.effective_start_date" type="date" class="input-field w-full text-sm" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">มีผลถึง (ไม่ระบุ = ไม่สิ้นสุด)</label>
                  <input v-model="patternForm.effective_end_date" type="date" class="input-field w-full text-sm" />
                </div>
              </div>
              <button @click="savePattern" :disabled="savingPattern" class="btn-primary text-sm">
                {{ savingPattern ? 'กำลังบันทึก...' : '+ เพิ่มรูปแบบกะ' }}
              </button>
            </div>
          </div>

          <!-- Generate schedule for a month -->
          <div class="border-t pt-4 mt-4">
            <h4 class="text-sm font-semibold text-navy mb-2">สร้างตารางกะจากรูปแบบ</h4>
            <div class="flex items-end gap-3">
              <div>
                <label class="block text-xs text-gray-500 mb-1">เดือน</label>
                <input v-model="generateMonth" type="month" class="input-field text-sm" />
              </div>
              <button @click="generateSchedule" :disabled="generating || patterns.length === 0" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-50">
                {{ generating ? 'กำลังสร้าง...' : 'สร้างตารางกะเดือนนี้' }}
              </button>
            </div>
            <p v-if="generateResult" class="text-xs text-gray-500 mt-2">{{ generateResult }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
})

const loading = ref(true)
const saving = ref(false)
const locations = ref([])
const companies = ref([])

const showForm = ref(false)
const editingId = ref(null)
const form = reactive({
  company_id: '',
  name: '',
  address: '',
  latitude: '',
  longitude: '',
  radius_meters: 200,
  work_start_time: '',
  work_end_time: '',
  is_active: true,
})

const showAssign = ref(false)
const assignLocation = ref(null)
const assignedEmployees = ref([])
const unassignedEmployees = ref([])
const assignSearch = ref('')

const showPattern = ref(false)
const patternLocation = ref(null)
const patterns = ref([])
const workShifts = ref([])
const savingPattern = ref(false)
const generating = ref(false)
const generateMonth = ref(new Date().toISOString().slice(0, 7))
const generateResult = ref('')
const patternForm = reactive({
  work_shift_id: '',
  days_of_week: [],
  effective_start_date: '',
  effective_end_date: '',
})

const weekDays = [
  { value: 1, label: 'จันทร์' },
  { value: 2, label: 'อังคาร' },
  { value: 3, label: 'พุธ' },
  { value: 4, label: 'พฤหัสฯ' },
  { value: 5, label: 'ศุกร์' },
  { value: 6, label: 'เสาร์' },
  { value: 0, label: 'อาทิตย์' },
]

function dayLabels(days) {
  if (!days || days.length === 0) return '-'
  return weekDays.filter(d => days.includes(d.value)).map(d => d.label).join(', ')
}

const mapContainer = ref(null)
let map = null
let marker = null

const companyColors = {
  ETC1992: 'linear-gradient(135deg, #10b981, #047857)',
  STC: 'linear-gradient(135deg, #a855f7, #7e22ce)',
  ETECH: 'linear-gradient(135deg, #f97316, #c2410c)',
  NTC: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
}

function companyStyle(code) {
  return companyColors[code] || 'background: linear-gradient(135deg, #64748b, #334155)'
}

function getLocationsByCompany(companyId) {
  return locations.value.filter(l => l.company_id === companyId)
}

async function loadData() {
  loading.value = true
  try {
    const [locRes, compRes] = await Promise.all([
      api.get('/api/office-locations'),
      api.get('/api/companies'),
    ])
    locations.value = locRes.data.data || []
    companies.value = compRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openCreateForm() {
  editingId.value = null
  Object.assign(form, {
    company_id: '',
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    radius_meters: 200,
    work_start_time: '',
    work_end_time: '',
    is_active: true,
  })
  showForm.value = true
  nextTick(() => initMap())
}

function openEditForm(loc) {
  editingId.value = loc.id
  Object.assign(form, {
    company_id: loc.company_id,
    name: loc.name,
    address: loc.address || '',
    latitude: loc.latitude,
    longitude: loc.longitude,
    radius_meters: loc.radius_meters,
    work_start_time: loc.work_start_time || '',
    work_end_time: loc.work_end_time || '',
    is_active: loc.is_active,
  })
  showForm.value = true
  nextTick(() => initMap())
}

async function saveLocation() {
  if (!form.company_id || !form.name || !form.latitude || !form.longitude) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/api/office-locations/${editingId.value}`, form)
    } else {
      await api.post('/api/office-locations', form)
    }
    showForm.value = false
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function deleteLocation(loc) {
  if (!confirm(`ต้องการลบ "${loc.name}" ใช่หรือไม่?`)) return
  try {
    await api.delete(`/api/office-locations/${loc.id}`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function openAssignModal(loc) {
  assignLocation.value = loc
  assignSearch.value = ''
  showAssign.value = true
  await loadAssignedEmployees(loc.id)
  await searchUnassigned()
}

async function loadAssignedEmployees(locId) {
  try {
    const res = await api.get(`/api/office-locations/${locId}/employees`)
    assignedEmployees.value = res.data.data || []
  } catch (e) {
    assignedEmployees.value = []
  }
}

async function searchUnassigned() {
  if (!assignLocation.value) return
  try {
    const params = assignSearch.value ? { search: assignSearch.value } : {}
    const res = await api.get(`/api/office-locations/${assignLocation.value.id}/unassigned`, { params })
    unassignedEmployees.value = res.data.data || []
  } catch (e) {
    unassignedEmployees.value = []
  }
}

async function assignEmployee(emp) {
  try {
    await api.post(`/api/office-locations/${assignLocation.value.id}/assign`, {
      employee_ids: [emp.id],
    })
    await loadAssignedEmployees(assignLocation.value.id)
    await searchUnassigned()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function removeEmployee(emp) {
  try {
    await api.post(`/api/office-locations/${assignLocation.value.id}/remove`, {
      employee_ids: [emp.id],
    })
    await loadAssignedEmployees(assignLocation.value.id)
    await searchUnassigned()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function openPatternModal(loc) {
  patternLocation.value = loc
  generateResult.value = ''
  Object.assign(patternForm, { work_shift_id: '', days_of_week: [], effective_start_date: '', effective_end_date: '' })
  showPattern.value = true
  await Promise.all([loadPatterns(loc.id), loadWorkShifts()])
}

async function loadPatterns(locId) {
  try {
    const res = await api.get(`/api/office-locations/${locId}/shift-patterns`)
    patterns.value = res.data.data || []
  } catch (e) {
    patterns.value = []
  }
}

async function loadWorkShifts() {
  if (workShifts.value.length > 0) return
  try {
    const res = await api.get('/api/shift-schedules', { params: { month: generateMonth.value } })
    workShifts.value = res.data.work_shifts || []
  } catch (e) {
    workShifts.value = []
  }
}

async function savePattern() {
  if (!patternForm.work_shift_id || patternForm.days_of_week.length === 0) {
    alert('กรุณาเลือกกะและวันที่ต้องเข้างานอย่างน้อย 1 วัน')
    return
  }
  savingPattern.value = true
  try {
    await api.post(`/api/office-locations/${patternLocation.value.id}/shift-patterns`, {
      work_shift_id: patternForm.work_shift_id,
      days_of_week: patternForm.days_of_week,
      effective_start_date: patternForm.effective_start_date || null,
      effective_end_date: patternForm.effective_end_date || null,
    })
    Object.assign(patternForm, { work_shift_id: '', days_of_week: [], effective_start_date: '', effective_end_date: '' })
    await loadPatterns(patternLocation.value.id)
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    savingPattern.value = false
  }
}

async function deletePattern(p) {
  if (!confirm('ต้องการลบรูปแบบกะนี้ใช่หรือไม่?')) return
  try {
    await api.delete(`/api/office-locations/${patternLocation.value.id}/shift-patterns/${p.id}`)
    await loadPatterns(patternLocation.value.id)
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function generateSchedule() {
  generating.value = true
  generateResult.value = ''
  try {
    const res = await api.post(`/api/office-locations/${patternLocation.value.id}/shift-patterns/generate`, {
      month: generateMonth.value,
    })
    generateResult.value = res.data.message
  } catch (e) {
    generateResult.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    generating.value = false
  }
}

function initMap() {
  if (!mapContainer.value) return
  if (map) {
    map.remove()
    map = null
    marker = null
  }

  const lat = form.latitude || 13.7563
  const lng = form.longitude || 100.5018

  map = L.map(mapContainer.value).setView([lat, lng], 12)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map)

  if (form.latitude && form.longitude) {
    marker = L.marker([form.latitude, form.longitude], { draggable: true }).addTo(map)
    marker.on('dragend', (e) => {
      const pos = e.target.getLatLng()
      form.latitude = pos.lat.toFixed(8)
      form.longitude = pos.lng.toFixed(8)
    })
  }

  map.on('click', (e) => {
    const { lat, lng } = e.latlng
    form.latitude = lat.toFixed(8)
    form.longitude = lng.toFixed(8)
    if (marker) {
      marker.setLatLng([lat, lng])
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map)
      marker.on('dragend', (e) => {
        const pos = e.target.getLatLng()
        form.latitude = pos.lat.toFixed(8)
        form.longitude = pos.lng.toFixed(8)
      })
    }
  })

  setTimeout(() => map.invalidateSize(), 100)
}

onMounted(loadData)
</script>
