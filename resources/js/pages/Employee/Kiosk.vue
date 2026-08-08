<template>
  <div class="min-h-screen bg-gradient-to-br from-cream via-blue-50 to-sky-100 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl md:text-4xl font-bold text-navy mb-2">ระบบเช็คเวลาเข้างาน</h1>
        <p class="text-gray-500">ระบบเช็คเวลาเข้างาน บริษัทในเครือ</p>
      </div>

      <!-- Step 1: Company Selection -->
      <div v-if="step === 1" class="animate-fadeIn">
        <div class="card">
          <h2 class="text-xl font-semibold text-navy mb-6 text-center">เลือกบริษัท</h2>
          <div class="grid grid-cols-2 gap-4">
            <button
              v-for="company in companies"
              :key="company.id"
              @click="selectCompany(company)"
              class="p-6 rounded-xl border-2 border-gray-200 hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 text-center group"
            >
              <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-navy flex items-center justify-center group-hover:bg-blue-500 transition-colors">
                <span class="text-white text-xl font-bold">{{ company.name.charAt(0) }}</span>
              </div>
              <p class="font-semibold text-navy">{{ company.name }}</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Search Employee -->
      <div v-if="step === 2" class="animate-fadeIn">
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <button @click="step = 1" class="text-blue-500 hover:text-blue-600 flex items-center gap-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              กลับ
            </button>
            <h2 class="text-xl font-semibold text-navy">{{ selectedCompany?.name }}</h2>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">ค้นหาพนักงาน</label>
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                class="input-field pl-12 text-lg py-3"
                placeholder="พิมพ์ชื่อหรือรหัสพนักงาน..."
                @input="debouncedSearch"
                autofocus
              />
              <svg class="w-6 h-6 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <div v-if="loading && !employees.length" class="text-center py-8">
            <LoadingSpinner />
            <p class="text-gray-500 mt-2">กำลังค้นหา...</p>
          </div>

          <div v-else-if="filteredEmployees.length > 0" class="space-y-2 max-h-80 overflow-y-auto custom-scrollbar">
            <button
              v-for="emp in filteredEmployees"
              :key="emp.id"
              @click="selectEmployee(emp)"
              class="w-full p-4 rounded-lg border border-gray-200 hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 flex items-center gap-4 text-left"
            >
              <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-blue-600 font-semibold">{{ emp.name.charAt(0) }}</span>
              </div>
              <div class="flex-1">
                <p class="font-semibold text-navy">{{ emp.name }}</p>
                <p class="text-sm text-gray-500">{{ emp.employee_code }} | {{ emp.department }}</p>
              </div>
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div v-else-if="searchQuery && !loading" class="text-center py-8 text-gray-500">
            ไม่พบพนักงานที่ค้นหา
          </div>
        </div>
      </div>

      <!-- Step 2.5: Scan Type Selection (for remote employees) -->
      <div v-if="step === 2.5" class="animate-fadeIn">
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <button @click="step = 2" class="text-blue-500 hover:text-blue-600 flex items-center gap-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              กลับ
            </button>
            <h2 class="text-xl font-semibold text-navy">เลือกวิธีสแกน</h2>
          </div>

          <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
              <span class="text-blue-600 text-3xl font-bold">{{ selectedEmployee?.name?.charAt(0) }}</span>
            </div>
            <p class="text-lg font-semibold text-navy">{{ selectedEmployee?.name }}</p>
            <p class="text-gray-500">{{ selectedEmployee?.employee_code }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <button
              @click="startOfficeScan"
              class="p-6 rounded-xl border-2 border-green-200 hover:border-green-500 hover:bg-green-50 transition-all duration-200 text-center"
            >
              <div class="text-4xl mb-3">🏢</div>
              <p class="font-semibold text-navy">สแกนที่ออฟฟิศ</p>
              <p class="text-sm text-gray-500">Check-in ตามปกติ</p>
            </button>
            <button
              @click="startRemoteScan"
              class="p-6 rounded-xl border-2 border-blue-200 hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 text-center"
            >
              <div class="text-4xl mb-3">📍</div>
              <p class="font-semibold text-navy">สแกนนอกสถานที่</p>
              <p class="text-sm text-gray-500">ระหว่างเดินทาง</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Face Recognition -->
      <div v-if="step === 3" class="animate-fadeIn">
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <button @click="step = 2" class="text-blue-500 hover:text-blue-600 flex items-center gap-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              กลับ
            </button>
            <h2 class="text-xl font-semibold text-navy">ยืนยันตัวตน</h2>
          </div>

          <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
              <span class="text-blue-600 text-3xl font-bold">{{ selectedEmployee?.name?.charAt(0) }}</span>
            </div>
            <p class="text-lg font-semibold text-navy">{{ selectedEmployee?.name }}</p>
            <p class="text-gray-500">{{ selectedEmployee?.employee_code }}</p>
            <p v-if="scanType === 'remote_scan'" class="text-sm text-blue-500 mt-1">📍 นอกสถานที่</p>
          </div>

          <!-- Location input for remote scan -->
          <div v-if="scanType === 'remote_scan'" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานที่ (ไม่บังคับ)</label>
            <input v-model="customLocationName" type="text" class="input-field" placeholder="เช่น โรงแรมABC, สำนักงานลูกค้า" />
          </div>

          <FaceScanner
            :employee-id="selectedEmployee?.id"
            :scan-type="scanType"
            :latitude="currentLatitude"
            :longitude="currentLongitude"
            :custom-location-name="customLocationName"
            @verified="handleVerified"
            @failed="handleFailed"
            @error="handleError"
          />

          <div v-if="scanningError" class="mt-4 p-4 bg-red-50 rounded-lg text-center">
            <p class="text-red-600">{{ scanningError }}</p>
            <button @click="retryScan" class="mt-2 text-blue-500 hover:text-blue-600 font-medium">
              ลองใหม่
            </button>
          </div>
        </div>
      </div>

      <!-- Step 4: Result -->
      <div v-if="step === 4" class="animate-fadeIn">
        <div class="card text-center py-12">
          <div
            :class="[
              'w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center',
              result.success ? 'bg-green-100' : 'bg-red-100'
            ]"
          >
            <svg
              v-if="result.success"
              class="w-12 h-12 text-green-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg
              v-else
              class="w-12 h-12 text-red-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>

          <h2
            :class="[
              'text-2xl font-bold mb-2',
              result.success ? 'text-green-600' : 'text-red-600'
            ]"
          >
            {{ result.message }}
          </h2>

          <p class="text-gray-500 mb-2">{{ selectedEmployee?.name }}</p>
          <p class="text-gray-400 text-sm mb-2">{{ result.time }}</p>
          <p v-if="result.location" class="text-blue-500 text-sm mb-8">📍 {{ result.location }}</p>
          <p v-else class="text-gray-400 text-sm mb-8">🏢 ออฟฟิศ</p>

          <button
            @click="reset"
            class="btn-primary text-lg px-8 py-3"
          >
            เริ่มใหม่
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import FaceScanner from '../../components/FaceScanner.vue'

const step = ref(1)
const selectedCompany = ref(null)
const selectedEmployee = ref(null)
const searchQuery = ref('')
const employees = ref([])
const loading = ref(false)
const scanningError = ref('')
const result = ref({ success: false, message: '', time: '', location: '' })
const scanType = ref('office_scan')
const customLocationName = ref('')
const currentLatitude = ref(null)
const currentLongitude = ref(null)

const companies = [
  { id: 1, name: 'NTC', code: 'NTC' },
  { id: 2, name: 'ETC1992', code: 'ETC1992' },
  { id: 3, name: 'ETECH', code: 'ETECH' },
  { id: 4, name: 'STC', code: 'STC' }
]

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return []
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter(
    emp => emp.name.toLowerCase().includes(q) || (emp.employee_code && emp.employee_code.toLowerCase().includes(q))
  )
})

onMounted(() => {
  getCurrentPosition()
})

function getCurrentPosition() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        currentLatitude.value = pos.coords.latitude
        currentLongitude.value = pos.coords.longitude
      },
      (err) => {
        console.error('Geolocation error:', err)
      },
      { enableHighAccuracy: true }
    )
  }
}

function selectCompany(company) {
  selectedCompany.value = company
  step.value = 2
  searchEmployees()
}

let searchTimeout = null

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    searchEmployees()
  }, 500)
}

async function searchEmployees() {
  if (!selectedCompany.value) return
  loading.value = true
  try {
    const response = await axios.post('/api/employee/auth/search', {
      company_id: selectedCompany.value.id,
      query: searchQuery.value
    })
    employees.value = response.data.data || []
  } catch (error) {
    console.error('Error searching employees:', error)
  } finally {
    loading.value = false
  }
}

async function selectEmployee(employee) {
  selectedEmployee.value = employee

  // Check if employee has remote assignment
  try {
    const res = await axios.post('/api/remote/check-active', {
      employee_id: employee.id
    })
    if (res.data.data?.has_remote_assignment) {
      step.value = 2.5
    } else {
      scanType.value = 'office_scan'
      step.value = 3
    }
  } catch {
    scanType.value = 'office_scan'
    step.value = 3
  }
}

function startOfficeScan() {
  scanType.value = 'office_scan'
  step.value = 3
}

function startRemoteScan() {
  scanType.value = 'remote_scan'
  step.value = 3
}

function handleVerified(data) {
  const now = new Date()
  const timeStr = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
  const isCheckIn = !data.is_checked_out

  result.value = {
    success: true,
    message: isCheckIn ? '✓ เช็คอินสำเร็จ' : '✓ เช็คเอาท์สำเร็จ',
    time: timeStr,
    location: scanType.value === 'remote_scan' ? customLocationName.value : null
  }
  step.value = 4
}

function handleFailed() {
  scanningError.value = 'ไม่สามารถยืนยันตัวตนได้ กรุณาลองใหม่'
}

function handleError(message) {
  scanningError.value = message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'
}

function retryScan() {
  scanningError.value = ''
  step.value = 3
}

function reset() {
  step.value = 1
  selectedCompany.value = null
  selectedEmployee.value = null
  searchQuery.value = ''
  employees.value = []
  scanningError.value = ''
  result.value = { success: false, message: '', time: '', location: '' }
  scanType.value = 'office_scan'
  customLocationName.value = ''
}
</script>