<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-navy">ตั้งค่า</h1>
        <p class="text-gray-500">จัดการตำแหน่งสำนักงาน, วันหยุด, และตารางเวร</p>
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
                    <img v-if="companyForm.logoPreview" :src="companyForm.logoPreview" class="w-full h-full object-contain p-2" />
                    <div v-else class="text-center text-gray-400">
                      <svg class="w-8 h-8 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <p class="text-xs">คลิกหรือลากไฟล์มาวาง</p>
                    </div>
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl">
                      <span class="text-white text-sm font-medium">เปลี่ยนโลโก้</span>
                    </div>
                  </div>
                  <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="handleLogoSelect" />
                  <p class="text-xs text-gray-400 mt-2">PNG, JPG, SVG<br/>ขนาดไม่เกิน 2MB</p>
                  <button
                    v-if="companyForm.logoPreview && companyForm.logoId"
                    type="button"
                    @click="removeLogo"
                    class="mt-2 text-xs text-red-500 hover:text-red-600"
                  >
                    ลบโลโก้
                  </button>
                </div>

                <!-- Company Fields -->
                <div class="flex-1 space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อบริษัท</label>
                    <input v-model="companyForm.name" type="text" class="input-field" />
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                      <input v-model="companyForm.phone" type="tel" class="input-field" placeholder="0XX-XXX-XXXX" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                      <input v-model="companyForm.email" type="email" class="input-field" placeholder="info@company.com" />
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                    <textarea v-model="companyForm.address" class="input-field" rows="2" placeholder="ที่อยู่สำนักงาน"></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เว็บไซต์</label>
                    <input v-model="companyForm.website" type="url" class="input-field" placeholder="https://www.company.com" />
                  </div>
                </div>
              </div>

              <div class="flex justify-end pt-4 border-t">
                <button type="submit" :disabled="saving" class="btn-primary">
                  {{ saving ? 'กำลังบันทึก...' : 'บันทึกข้อมูลบริษัท' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Office Locations -->
        <div v-if="activeTab === 'locations'" class="space-y-4">
          <div class="flex justify-end">
            <button @click="showLocationModal = true" class="btn-primary">
              เพิ่มตำแหน่ง
            </button>
          </div>

          <div v-for="location in locations" :key="location.id" class="card">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-navy">{{ location.name }}</h3>
                <p class="text-sm text-gray-500">{{ location.address }}</p>
                <p class="text-sm text-gray-400 mt-1">
                 ละติจูด: {{ location.latitude }}, ลองจิจูด: {{ location.longitude }} | รัศมี: {{ location.radius }} เมตร
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="editLocation(location)"
                  class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="deleteLocation(location)"
                  class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Location Modal -->
          <Modal :show="showLocationModal" @close="closeLocationModal" title="จัดการตำแหน่งสำนักงาน">
            <form @submit.prevent="saveLocation" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อตำแหน่ง</label>
                <input v-model="locationForm.name" type="text" class="input-field" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                <textarea v-model="locationForm.address" class="input-field" rows="2"></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">ละติจูด</label>
                  <input v-model="locationForm.latitude" type="number" step="any" class="input-field" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">ลองจิจูด</label>
                  <input v-model="locationForm.longitude" type="number" step="any" class="input-field" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รัศมี (เมตร)</label>
                <input v-model="locationForm.radius" type="number" class="input-field" value="100" />
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="closeLocationModal" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                  ยกเลิก
                </button>
                <button type="submit" :disabled="saving" class="btn-primary">
                  {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </Modal>
        </div>

        <!-- Holidays -->
        <div v-if="activeTab === 'holidays'" class="space-y-4">
          <div class="flex justify-end">
            <button @click="showHolidayModal = true" class="btn-primary">
              เพิ่มวันหยุด
            </button>
          </div>

          <div class="card overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่</th>
                    <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อวันหยุด</th>
                    <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">การดำเนินการ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="holiday in holidays" :key="holiday.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-600">{{ formatDate(holiday.date) }}</td>
                    <td class="px-6 py-4 font-medium text-navy">{{ holiday.name }}</td>
                    <td class="px-6 py-4 text-center">
                      <button
                        @click="deleteHoliday(holiday)"
                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Holiday Modal -->
          <Modal :show="showHolidayModal" @close="showHolidayModal = false" title="เพิ่มวันหยุด">
            <form @submit.prevent="saveHoliday" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
                <input v-model="holidayForm.date" type="date" class="input-field" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อวันหยุด</label>
                <input v-model="holidayForm.name" type="text" class="input-field" required />
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showHolidayModal = false" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                  ยกเลิก
                </button>
                <button type="submit" :disabled="saving" class="btn-primary">
                  {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </Modal>
        </div>

        <!-- Shifts -->
        <div v-if="activeTab === 'shifts'" class="space-y-4">
          <div class="flex justify-end">
            <button @click="showShiftModal = true" class="btn-primary">
              เพิ่มตารางเวร
            </button>
          </div>

          <div v-for="shift in shifts" :key="shift.id" class="card">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-navy">{{ shift.name }}</h3>
                <p class="text-sm text-gray-500">
                  {{ shift.start_time }} - {{ shift.end_time }} | หยุด: {{ shift.days_off }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="editShift(shift)"
                  class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="deleteShift(shift)"
                  class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Shift Modal -->
          <Modal :show="showShiftModal" @close="closeShiftModal" title="จัดการตารางเวร">
            <form @submit.prevent="saveShift" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อเวร</label>
                <input v-model="shiftForm.name" type="text" class="input-field" required />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้า</label>
                  <input v-model="shiftForm.start_time" type="time" class="input-field" required />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาออก</label>
                  <input v-model="shiftForm.end_time" type="time" class="input-field" required />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันหยุด</label>
                <input v-model="shiftForm.days_off" type="text" class="input-field" placeholder="เช่น เสาร์-อาทิตย์" />
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="closeShiftModal" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                  ยกเลิก
                </button>
                <button type="submit" :disabled="saving" class="btn-primary">
                  {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </Modal>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Modal from '../../components/Modal.vue'

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('locations')

const tabs = [
  { key: 'company', label: 'ข้อมูลบริษัท' },
  { key: 'locations', label: 'ตำแหน่งสำนักงาน' },
  { key: 'holidays', label: 'วันหยุด' },
  { key: 'shifts', label: 'ตารางเวร' }
]

const locations = ref([])
const holidays = ref([])
const shifts = ref([])

const showLocationModal = ref(false)
const showHolidayModal = ref(false)
const showShiftModal = ref(false)

const editLocationId = ref(null)
const editShiftId = ref(null)

const locationForm = reactive({
  name: '',
  address: '',
  latitude: '',
  longitude: '',
  radius: 100
})

const companyForm = reactive({
  name: '',
  phone: '',
  email: '',
  address: '',
  website: '',
  logoPreview: null,
  logoFile: null,
  logoId: null,
})

const holidayForm = reactive({
  date: '',
  name: ''
})

const shiftForm = reactive({
  name: '',
  start_time: '',
  end_time: '',
  days_off: ''
})

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('th-TH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

async function fetchLocations() {
  try {
    const response = await axios.get('/api/settings/locations')
    locations.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching locations:', error)
  }
}

async function fetchCompany() {
  try {
    const response = await axios.get('/api/company-settings')
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
    await axios.delete('/api/company-settings/logo')
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
    await axios.put('/api/company-settings', {
      name: companyForm.name,
      phone: companyForm.phone,
      email: companyForm.email,
      address: companyForm.address,
      website: companyForm.website,
    })

    if (companyForm.logoFile) {
      const formData = new FormData()
      formData.append('logo', companyForm.logoFile)
      const logoRes = await axios.post('/api/company-settings/logo', formData, {
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

async function fetchHolidays() {
  try {
    const response = await axios.get('/api/settings/holidays')
    holidays.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching holidays:', error)
  }
}

async function fetchShifts() {
  try {
    const response = await axios.get('/api/settings/shifts')
    shifts.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching shifts:', error)
  }
}

function editLocation(location) {
  editLocationId.value = location.id
  Object.assign(locationForm, {
    name: location.name,
    address: location.address,
    latitude: location.latitude,
    longitude: location.longitude,
    radius: location.radius
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
    if (editLocationId.value) {
      await axios.put(`/api/settings/locations/${editLocationId.value}`, locationForm)
    } else {
      await axios.post('/api/settings/locations', locationForm)
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
    await axios.delete(`/api/settings/locations/${location.id}`)
    fetchLocations()
  } catch (error) {
    console.error('Error deleting location:', error)
  }
}

async function saveHoliday() {
  saving.value = true
  try {
    await axios.post('/api/settings/holidays', holidayForm)
    showHolidayModal.value = false
    Object.assign(holidayForm, { date: '', name: '' })
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
    await axios.delete(`/api/settings/holidays/${holiday.id}`)
    fetchHolidays()
  } catch (error) {
    console.error('Error deleting holiday:', error)
  }
}

function editShift(shift) {
  editShiftId.value = shift.id
  Object.assign(shiftForm, {
    name: shift.name,
    start_time: shift.start_time,
    end_time: shift.end_time,
    days_off: shift.days_off
  })
  showShiftModal.value = true
}

function closeShiftModal() {
  showShiftModal.value = false
  editShiftId.value = null
  Object.assign(shiftForm, { name: '', start_time: '', end_time: '', days_off: '' })
}

async function saveShift() {
  saving.value = true
  try {
    if (editShiftId.value) {
      await axios.put(`/api/settings/shifts/${editShiftId.value}`, shiftForm)
    } else {
      await axios.post('/api/settings/shifts', shiftForm)
    }
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
  if (!confirm(`ยืนยันการลบ "${shift.name}"?`)) return
  try {
    await axios.delete(`/api/settings/shifts/${shift.id}`)
    fetchShifts()
  } catch (error) {
    console.error('Error deleting shift:', error)
  }
}

onMounted(async () => {
  await Promise.all([fetchCompany(), fetchLocations(), fetchHolidays(), fetchShifts()])
  loading.value = false
})
</script>
