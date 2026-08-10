<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">ตั้งค่าตำแหน่งจุดเช็คอิน</h1>
          <p class="text-gray-500">กำหนดตำแหน่งที่ตั้งสำนักงานและจัดกลุ่มพนักงาน</p>
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
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, watch } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

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
