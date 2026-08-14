<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">ตั้งค่า</h1>
          <p class="text-gray-500">จัดการข้อมูลบริษัท, ตำแหน่งสำนักงาน, วันหยุด และตารางเวร</p>
        </div>
        <!-- Company selector (super admin only) -->
        <div v-if="isSuperAdmin" class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">บริษัท:</label>
          <select v-model="selectedCompanyId" @change="onCompanyChange" class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 sm:gap-4 border-b overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-3 sm:px-4 py-2 font-medium border-b-2 transition-colors whitespace-nowrap text-sm sm:text-base',
            activeTab === tab.key
              ? 'border-blue-500 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Company Info -->
        <div v-if="activeTab === 'company'" class="space-y-6">
          <div class="card">
            <h3 class="text-lg font-semibold text-navy mb-4">ข้อมูลบริษัท</h3>
            <form @submit.prevent="saveCompanyInfo" class="space-y-4">
              <div class="flex flex-col sm:flex-row gap-6">
                <!-- Logo Upload -->
                <div class="shrink-0">
                  <label class="block text-sm font-medium text-gray-700 mb-2">โลโก้บริษัท</label>
                  <div
                    class="w-32 h-32 sm:w-40 sm:h-40 rounded-2xl border-2 border-dashed border-gray-300 hover:border-blue-400 transition-colors flex items-center justify-center overflow-hidden bg-gray-50 cursor-pointer relative group"
                    @click="$refs.logoInput.click()"
                    @dragover.prevent
                    @drop.prevent="handleLogoDrop"
                  >
                    <template v-if="companyForm.logoPreview">
                      <img :src="companyForm.logoPreview" class="w-full h-full object-contain p-2" />
                      <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="text-white text-sm">เปลี่ยนโลโก้</span>
                      </div>
                    </template>
                    <div v-else class="text-center p-4">
                      <div class="text-3xl mb-1">📷</div>
                      <span class="text-xs text-gray-500">อัปโหลดโลโก้</span>
                    </div>
                    <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="handleLogoSelect" />
                  </div>
                  <button
                    v-if="companyForm.logoPreview && companyForm.logoId"
                    @click="removeLogo"
                    type="button"
                    class="mt-2 text-xs text-red-600 hover:text-red-800"
                  >
                    ลบโลโก้
                  </button>
                </div>

                <!-- Company Fields -->
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อบริษัท</label>
                    <input v-model="companyForm.name" type="text" class="input-field w-full" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">โทรศัพท์</label>
                    <input v-model="companyForm.phone" type="tel" class="input-field w-full" placeholder="0XX-XXX-XXXX" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                    <input v-model="companyForm.email" type="email" class="input-field w-full" placeholder="info@company.com" />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                    <textarea v-model="companyForm.address" class="input-field w-full" rows="2" placeholder="ที่อยู่สำนักงาน"></textarea>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">เว็บไซต์</label>
                    <input v-model="companyForm.website" type="url" class="input-field w-full" placeholder="https://www.company.com" />
                  </div>
                </div>
              </div>
              <div class="flex justify-end">
                <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                  {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Office Locations -->
        <div v-if="activeTab === 'locations'" class="space-y-6">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-navy">ตำแหน่งสำนักงาน</h3>
            <button @click="openLocationModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ เพิ่มตำแหน่ง</button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="location in locations" :key="location.id" class="card">
              <h4 class="font-semibold text-navy">{{ location.name }}</h4>
              <p class="text-sm text-gray-500 mt-1">{{ location.address || 'ไม่มีที่อยู่' }}</p>
              <p class="text-sm text-gray-500 mt-1">รัศมี: {{ location.radius_meters }} ม.</p>
              <div class="flex gap-3 mt-3">
                <button @click="openLocationModal(location)" class="text-blue-600 text-sm hover:underline">แก้ไข</button>
                <button @click="deleteLocation(location)" class="text-red-600 text-sm hover:underline">ลบ</button>
              </div>
            </div>
          </div>
          <p v-if="!locations.length" class="text-gray-500 text-sm py-8 text-center">ไม่พบตำแหน่งสำนักงาน</p>
        </div>

        <!-- Holidays -->
        <div v-if="activeTab === 'holidays'" class="space-y-6">
          <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
              <h3 class="text-lg font-semibold text-navy">วันหยุด</h3>
              <p class="text-sm text-gray-500">จัดการวันหยุดราชการสำหรับบริษัทนี้</p>
            </div>
            <div class="flex gap-2">
              <select v-model="holidayYear" @change="fetchHolidays" class="border rounded-lg px-3 py-2 text-sm">
                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
              </select>
              <button @click="openHolidayModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ เพิ่มวันหยุด</button>
            </div>
          </div>
          <table class="w-full bg-white rounded-xl shadow-sm border text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th class="px-6 py-3 text-left">วันที่</th>
                <th class="px-6 py-3 text-left">ชื่อวันหยุด</th>
                <th class="px-6 py-3 text-left">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="h in holidays" :key="h.id">
                <td class="px-6 py-3">{{ formatDate(h.date) }}</td>
                <td class="px-6 py-3">{{ h.name }}</td>
                <td class="px-6 py-3">
                  <button @click="deleteHoliday(h)" class="text-red-600 text-sm hover:underline">ลบ</button>
                </td>
              </tr>
              <tr v-if="!holidays.length"><td colspan="3" class="px-6 py-8 text-center text-gray-500">ไม่พบวันหยุด</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Shifts -->
        <div v-if="activeTab === 'shifts'" class="space-y-6">
          <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
              <h3 class="text-lg font-semibold text-navy">ตารางเวร</h3>
              <p class="text-sm text-gray-500">กำหนดกะรายวันให้พนักงาน (หัวหน้าดำเนินการมอบหมาย)</p>
            </div>
            <div class="flex gap-2">
              <input type="month" v-model="shiftMonth" @change="fetchShifts" class="border rounded-lg px-3 py-2 text-sm" />
              <button @click="openShiftModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ เพิ่มกะ</button>
            </div>
          </div>
          <table class="w-full bg-white rounded-xl shadow-sm border text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th class="px-6 py-3 text-left">พนักงาน</th>
                <th class="px-6 py-3 text-left">วันที่</th>
                <th class="px-6 py-3 text-left">เวลา</th>
                <th class="px-6 py-3 text-left">รหัสกะ</th>
                <th class="px-6 py-3 text-left">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="s in shifts" :key="s.id">
                <td class="px-6 py-3">{{ s.employee?.name || '-' }}</td>
                <td class="px-6 py-3">{{ formatDate(s.date) }}</td>
                <td class="px-6 py-3">{{ s.start_time }} - {{ s.end_time }}</td>
                <td class="px-6 py-3">{{ s.shift_code }}</td>
                <td class="px-6 py-3">
                  <button @click="deleteShift(s)" class="text-red-600 text-sm hover:underline">ลบ</button>
                </td>
              </tr>
              <tr v-if="!shifts.length"><td colspan="5" class="px-6 py-8 text-center text-gray-500">ไม่พบตารางเวร</td></tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Location Modal -->
    <Modal :show="showLocationModal" :title="editLocationId ? 'แก้ไขตำแหน่งสำนักงาน' : 'เพิ่มตำแหน่งสำนักงาน'" @close="closeLocationModal">
      <form @submit.prevent="saveLocation" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ</label>
          <input v-model="locationForm.name" required class="input-field w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
          <textarea v-model="locationForm.address" class="input-field w-full" rows="2"></textarea>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ละติจูด</label>
            <input v-model="locationForm.latitude" type="number" step="any" class="input-field w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ลองจิจูด</label>
            <input v-model="locationForm.longitude" type="number" step="any" class="input-field w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รัศมี (ม.)</label>
            <input v-model="locationForm.radius" type="number" class="input-field w-full" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeLocationModal" class="px-4 py-2 border rounded-lg">ยกเลิก</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50">{{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Holiday Modal -->
    <Modal :show="showHolidayModal" title="เพิ่มวันหยุด" @close="closeHolidayModal">
      <form @submit.prevent="saveHoliday" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อวันหยุด</label>
          <input v-model="holidayForm.name" required class="input-field w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
          <input v-model="holidayForm.date" type="date" required class="input-field w-full" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeHolidayModal" class="px-4 py-2 border rounded-lg">ยกเลิก</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50">{{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Shift Modal -->
    <Modal :show="showShiftModal" title="เพิ่มกะ" @close="closeShiftModal">
      <form @submit.prevent="saveShift" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
          <select v-model="shiftForm.emp_id" required class="input-field w-full">
            <option value="" disabled>เลือกพนักงาน</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
          <input v-model="shiftForm.date" type="date" required class="input-field w-full" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเริ่ม</label>
            <input v-model="shiftForm.start_time" type="time" required class="input-field w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เวลาจบ</label>
            <input v-model="shiftForm.end_time" type="time" required class="input-field w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">รหัสกะ</label>
          <select v-model="shiftForm.shift_code" required class="input-field w-full">
            <option value="Full Day">Full Day</option>
            <option value="Morning">Morning</option>
            <option value="Afternoon">Afternoon</option>
            <option value="Night">Night</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeShiftModal" class="px-4 py-2 border rounded-lg">ยกเลิก</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50">{{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Modal from '../../components/Modal.vue'

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('company')

const tabs = [
  { key: 'company', label: 'ข้อมูลบริษัท' },
  { key: 'locations', label: 'ตำแหน่งสำนักงาน' },
  { key: 'holidays', label: 'วันหยุด' },
  { key: 'shifts', label: 'ตารางเวร' }
]

const locations = ref([])
const holidays = ref([])
const shifts = ref([])
const employees = ref([])
const companies = ref([])
const selectedCompanyId = ref(null)
const holidayYear = ref(new Date().getFullYear())
const shiftMonth = ref(new Date().toISOString().slice(0, 7))
const isSuperAdmin = computed(() => {
  const uid = (storeUser.company_id ?? 0)
  return !uid
})

const storeUser = JSON.parse(localStorage.getItem('user') || 'null') || {}

const showLocationModal = ref(false)
const showHolidayModal = ref(false)
const showShiftModal = ref(false)

const editLocationId = ref(null)

const locationForm = reactive({ name: '', address: '', latitude: '', longitude: '', radius: 100 })
const companyForm = reactive({ name: '', phone: '', email: '', address: '', website: '', logoPreview: null, logoFile: null, logoId: null })
const holidayForm = reactive({ date: '', name: '' })
const shiftForm = reactive({ emp_id: '', date: '', start_time: '08:00', end_time: '17:00', shift_code: 'Full Day' })

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1, y + 2]
})

function formatDate(d) {
  if (!d) return ''
  const parts = String(d).split('-')
  return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : d
}

function companyParams(extra = {}) {
  const params = { ...extra }
  if (isSuperAdmin.value && selectedCompanyId.value) params.company_id = selectedCompanyId.value
  return params
}

async function loadCompanies() {
  try {
    const res = await api.get('/api/companies')
    companies.value = res.data.data?.data || res.data.data || []
    if (isSuperAdmin.value) {
      if (companies.value.length) selectedCompanyId.value = companies.value[0].id
    } else {
      selectedCompanyId.value = storeUser.company_id || companies.value[0]?.id || null
    }
  } catch (e) {
    console.error('Error loading companies:', e)
  }
}

async function fetchLocations() {
  try {
    const response = await api.get('/api/office-locations', { params: companyParams() })
    locations.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching locations:', error)
  }
}

async function fetchCompany() {
  try {
    const response = await api.get('/api/company-settings', { params: companyParams() })
    const company = response.data.company || {}
    companyForm.name = company.name || ''
    companyForm.phone = company.phone || ''
    companyForm.email = company.email || ''
    companyForm.address = company.address || ''
    companyForm.website = company.website || ''
    companyForm.logoPreview = company.logo_url || null
    companyForm.logoId = company.logo || null
  } catch (error) {
    console.error('Error fetching company:', error)
  }
}

async function fetchHolidays() {
  try {
    const response = await api.get('/api/holidays', { params: companyParams({ year: holidayYear.value }) })
    holidays.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching holidays:', error)
  }
}

async function fetchShifts() {
  try {
    const response = await api.get('/api/shift-schedules', { params: companyParams({ month: shiftMonth.value }) })
    shifts.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching shifts:', error)
  }
}

async function fetchEmployees() {
  try {
    const response = await api.get('/api/employees', { params: companyParams({ per_page: 500 }) })
    employees.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching employees:', error)
  }
}

async function onCompanyChange() {
  loading.value = true
  await Promise.all([fetchCompany(), fetchLocations(), fetchHolidays(), fetchShifts(), fetchEmployees()])
  loading.value = false
}

function handleLogoSelect(event) {
  const file = event.target.files[0]
  if (file) processLogoFile(file)
}

function handleLogoDrop(event) {
  const file = event.dataTransfer.files[0]
  if (file && file.type.startsWith('image/')) processLogoFile(file)
}

function processLogoFile(file) {
  if (file.size > 2 * 1024 * 1024) {
    alert('ไฟล์มีขนาดใหญ่เกิน 2MB')
    return
  }
  companyForm.logoFile = file
  companyForm.logoPreview = URL.createObjectURL(file)
}

async function removeLogo() {
  try {
    await api.delete('/api/company-settings/logo', { params: companyParams() })
    companyForm.logoPreview = null
    companyForm.logoFile = null
    companyForm.logoId = null
  } catch (error) {
    console.error('Error removing logo:', error)
  }
}

async function saveCompanyInfo() {
  saving.value = true
  try {
    await api.put('/api/company-settings', companyParams({
      name: companyForm.name,
      phone: companyForm.phone,
      email: companyForm.email,
      address: companyForm.address,
      website: companyForm.website,
    }))

    if (companyForm.logoFile) {
      const formData = new FormData()
      formData.append('logo', companyForm.logoFile)
      const logoRes = await api.post('/api/company-settings/logo', formData, {
        params: companyParams(),
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      companyForm.logoPreview = logoRes.data.logo_url
      companyForm.logoFile = null
    }

    alert('บันทึกสำเร็จ')
  } catch (error) {
    console.error('Error saving company:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    saving.value = false
  }
}

function openLocationModal(location) {
  editLocationId.value = location?.id || null
  Object.assign(locationForm, {
    name: location?.name || '',
    address: location?.address || '',
    latitude: location?.latitude || '',
    longitude: location?.longitude || '',
    radius: location?.radius_meters ?? 100
  })
  showLocationModal.value = true
}

function closeLocationModal() {
  showLocationModal.value = false
  editLocationId.value = null
  Object.assign(locationForm, { name: '', address: '', latitude: '', longitude: '', radius: 100 })
}

async function saveLocation() {
  saving.value = true
  try {
    const payload = companyParams({
      name: locationForm.name,
      address: locationForm.address,
      latitude: locationForm.latitude,
      longitude: locationForm.longitude,
      radius_meters: locationForm.radius,
    })
    if (editLocationId.value) {
      await api.put(`/api/office-locations/${editLocationId.value}`, payload)
    } else {
      await api.post('/api/office-locations', payload)
    }
    closeLocationModal()
    fetchLocations()
  } catch (error) {
    console.error('Error saving location:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    saving.value = false
  }
}

async function deleteLocation(location) {
  if (!confirm(`ยืนยันการลบ "${location.name}"?`)) return
  try {
    await api.delete(`/api/office-locations/${location.id}`)
    fetchLocations()
  } catch (error) {
    console.error('Error deleting location:', error)
  }
}

function openHolidayModal() {
  Object.assign(holidayForm, { date: '', name: '' })
  showHolidayModal.value = true
}

function closeHolidayModal() {
  showHolidayModal.value = false
  Object.assign(holidayForm, { date: '', name: '' })
}

async function saveHoliday() {
  saving.value = true
  try {
    await api.post('/api/holidays', companyParams({
      name: holidayForm.name,
      date: holidayForm.date,
    }))
    closeHolidayModal()
    fetchHolidays()
  } catch (error) {
    console.error('Error saving holiday:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    saving.value = false
  }
}

async function deleteHoliday(holiday) {
  if (!confirm(`ยืนยันการลบ "${holiday.name}"?`)) return
  try {
    await api.delete(`/api/holidays/${holiday.id}`)
    fetchHolidays()
  } catch (error) {
    console.error('Error deleting holiday:', error)
  }
}

function openShiftModal() {
  Object.assign(shiftForm, { emp_id: '', date: '', start_time: '08:00', end_time: '17:00', shift_code: 'Full Day' })
  showShiftModal.value = true
}

function closeShiftModal() {
  showShiftModal.value = false
  Object.assign(shiftForm, { emp_id: '', date: '', start_time: '08:00', end_time: '17:00', shift_code: 'Full Day' })
}

async function saveShift() {
  saving.value = true
  try {
    await api.post('/api/shift-schedules', companyParams({
      emp_id: shiftForm.emp_id,
      date: shiftForm.date,
      start_time: shiftForm.start_time,
      end_time: shiftForm.end_time,
      shift_code: shiftForm.shift_code,
    }))
    closeShiftModal()
    fetchShifts()
  } catch (error) {
    console.error('Error saving shift:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    saving.value = false
  }
}

async function deleteShift(shift) {
  if (!confirm(`ยืนยันการลบกะวันที่ ${formatDate(shift.date)}?`)) return
  try {
    await api.delete(`/api/shift-schedules/${shift.id}`)
    fetchShifts()
  } catch (error) {
    console.error('Error deleting shift:', error)
  }
}

onMounted(async () => {
  await loadCompanies()
  await Promise.all([fetchCompany(), fetchLocations(), fetchHolidays(), fetchShifts(), fetchEmployees()])
  loading.value = false
})
</script>
