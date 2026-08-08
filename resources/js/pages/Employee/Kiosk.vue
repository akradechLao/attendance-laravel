<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-100 via-indigo-50 to-purple-100 flex items-center justify-center p-3 sm:p-4 safe-area">
    <div class="w-full max-w-lg">
      <!-- Header -->
      <div class="text-center mb-6 sm:mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg shadow-blue-500/30 mb-3 sm:mb-4">
          <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-navy mb-1 sm:mb-2">ระบบเช็คเวลาเข้างาน</h1>
        <p class="text-sm sm:text-base text-blue-600 font-medium">บริษัทในเครือ</p>
      </div>

      <!-- Step 1: Company Selection -->
      <div v-if="step === 1" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <h2 class="text-lg sm:text-xl font-semibold text-navy mb-4 sm:mb-6 text-center">เลือกบริษัท</h2>
          <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <button
              v-for="company in companies"
              :key="company.id"
              @click="selectCompany(company)"
              class="company-btn p-4 sm:p-6 rounded-xl transition-all duration-200 text-center group touch-target"
              :style="companyStyles[company.name] || 'background: linear-gradient(135deg, #64748b, #334155); color: white; border: 2px solid rgba(100,116,139,0.5);'"
            >
              <div class="w-14 h-14 sm:w-20 sm:h-20 mx-auto mb-2 sm:mb-3 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110 group-active:scale-105 overflow-hidden">
                <img
                  v-if="company.logo_url"
                  :src="company.logo_url"
                  :alt="company.name"
                  class="w-full h-full object-contain p-1"
                />
                <span v-else class="text-white text-xl sm:text-2xl font-bold">{{ company.name.charAt(0) }}</span>
              </div>
              <p class="font-bold text-sm sm:text-base">{{ company.name }}</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Search Employee -->
      <div v-if="step === 2" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 1" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <h2 class="text-lg sm:text-xl font-semibold text-navy">{{ selectedCompany?.name }}</h2>
          </div>

          <div class="mb-4 sm:mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">ค้นหาพนักงาน</label>
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                inputmode="text"
                class="input-field text-base sm:text-lg py-3"
                placeholder="พิมพ์ชื่อหรือรหัสพนักงาน..."
                @focus="$event.target.select()"
                autofocus
              />
              <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <div v-if="!searchQuery" class="text-center py-6 sm:py-8 text-gray-400 text-sm sm:text-base">
            พิมพ์ชื่อหรือรหัสพนักงานเพื่อค้นหา
          </div>

          <div v-else-if="filteredEmployees.length > 0" class="space-y-2 max-h-[50vh] sm:max-h-80 overflow-y-auto custom-scrollbar">
            <button
              v-for="emp in filteredEmployees"
              :key="emp.id"
              @click="selectEmployee(emp)"
              class="w-full p-3 sm:p-4 rounded-xl border border-blue-100 bg-white hover:border-blue-400 hover:bg-blue-50 hover:shadow-md active:bg-blue-100 transition-all duration-200 flex items-center gap-3 sm:gap-4 text-left touch-target"
            >
              <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shrink-0 shadow-sm">
                <span class="text-white font-bold text-sm sm:text-base">{{ emp.name.charAt(0) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-navy text-sm sm:text-base truncate">{{ emp.name }}</p>
                <p class="text-xs sm:text-sm text-gray-500">{{ emp.employee_code }} | {{ emp.department }}</p>
              </div>
              <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div v-else-if="searchQuery && !loading" class="text-center py-6 sm:py-8 text-gray-500 text-sm sm:text-base">
            ไม่พบพนักงานที่ค้นหา
          </div>
        </div>
      </div>

      <!-- Step 2.5: Scan Type Selection (for remote employees) -->
      <div v-if="step === 2.5" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 2" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <h2 class="text-lg sm:text-xl font-semibold text-navy">เลือกวิธีสแกน</h2>
          </div>

          <div class="text-center mb-4 sm:mb-6">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-3 sm:mb-4 rounded-full bg-blue-100 flex items-center justify-center">
              <span class="text-blue-600 text-2xl sm:text-3xl font-bold">{{ selectedEmployee?.name?.charAt(0) }}</span>
            </div>
            <p class="text-base sm:text-lg font-semibold text-navy">{{ selectedEmployee?.name }}</p>
            <p class="text-sm text-gray-500">{{ selectedEmployee?.employee_code }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <button
              @click="startOfficeScan"
              class="p-4 sm:p-6 rounded-xl border-2 border-green-200 bg-gradient-to-b from-green-50 to-white hover:from-green-100 hover:border-green-400 active:from-green-200 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">🏢</div>
              <p class="font-bold text-navy text-sm sm:text-base">สแกนที่ออฟฟิศ</p>
              <p class="text-xs sm:text-sm text-green-600">Check-in ตามปกติ</p>
            </button>
            <button
              @click="startRemoteScan"
              class="p-4 sm:p-6 rounded-xl border-2 border-blue-200 bg-gradient-to-b from-blue-50 to-white hover:from-blue-100 hover:border-blue-400 active:from-blue-200 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">📍</div>
              <p class="font-bold text-navy text-sm sm:text-base">สแกนนอกสถานที่</p>
              <p class="text-xs sm:text-sm text-blue-600">ระหว่างเดินทาง</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Face Recognition -->
      <div v-if="step === 3" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 2" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <h2 class="text-lg sm:text-xl font-semibold text-navy">ยืนยันตัวตน</h2>
          </div>

          <div class="text-center mb-4 sm:mb-6">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-3 sm:mb-4 rounded-full bg-blue-100 flex items-center justify-center">
              <span class="text-blue-600 text-2xl sm:text-3xl font-bold">{{ selectedEmployee?.name?.charAt(0) }}</span>
            </div>
            <p class="text-base sm:text-lg font-semibold text-navy">{{ selectedEmployee?.name }}</p>
            <p class="text-sm text-gray-500">{{ selectedEmployee?.employee_code }}</p>
            <p v-if="scanType === 'remote_scan'" class="text-xs sm:text-sm text-blue-500 mt-1">📍 นอกสถานที่</p>
          </div>

          <!-- Location input for remote scan -->
          <div v-if="scanType === 'remote_scan'" class="mb-3 sm:mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานที่ (ไม่บังคับ)</label>
            <input v-model="customLocationName" type="text" inputmode="text" class="input-field text-base" placeholder="เช่น โรงแรมABC, สำนักงานลูกค้า" />
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

          <div v-if="scanningError" class="mt-3 sm:mt-4 p-3 sm:p-4 bg-red-50 rounded-lg text-center">
            <p class="text-red-600 text-sm sm:text-base">{{ scanningError }}</p>
            <button @click="retryScan" class="mt-2 text-blue-500 active:text-blue-600 font-medium text-sm sm:text-base touch-target">
              ลองใหม่
            </button>
          </div>
        </div>
      </div>

      <!-- Step 4: Result -->
      <div v-if="step === 4" class="animate-fadeIn">
        <div class="card p-4 sm:p-6 text-center py-8 sm:py-12">
          <div
            :class="[
              'w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-4 sm:mb-6 rounded-full flex items-center justify-center shadow-lg',
              result.success ? 'bg-gradient-to-br from-green-400 to-emerald-600 shadow-green-500/30' : 'bg-gradient-to-br from-red-400 to-rose-600 shadow-red-500/30'
            ]"
          >
            <svg
              v-if="result.success"
              class="w-10 h-10 sm:w-12 sm:h-12 text-green-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg
              v-else
              class="w-10 h-10 sm:w-12 sm:h-12 text-red-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>

          <h2
            :class="[
              'text-xl sm:text-2xl font-bold mb-2',
              result.success ? 'text-green-600' : 'text-red-600'
            ]"
          >
            {{ result.message }}
          </h2>

          <p class="text-gray-500 mb-2 text-sm sm:text-base">{{ selectedEmployee?.name }}</p>
          <p class="text-gray-400 text-sm mb-2">{{ result.time }}</p>
          <p v-if="result.location" class="text-blue-500 text-sm mb-6 sm:mb-8">📍 {{ result.location }}</p>
          <p v-else class="text-gray-400 text-sm mb-6 sm:mb-8">🏢 ออฟฟิศ</p>

          <button
            @click="reset"
            class="btn-primary text-base sm:text-lg px-6 sm:px-8 py-3 touch-target"
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
const companies = ref([])
const loading = ref(false)
const scanningError = ref('')
const result = ref({ success: false, message: '', time: '', location: '' })
const scanType = ref('office_scan')
const customLocationName = ref('')
const currentLatitude = ref(null)
const currentLongitude = ref(null)

const companyStyles = {
  'ETC1992': 'background: linear-gradient(135deg, #10b981, #047857); color: white; border: 2px solid rgba(52,211,153,0.5); box-shadow: 0 10px 25px rgba(16,185,129,0.25);',
  'STC': 'background: linear-gradient(135deg, #a855f7, #7e22ce); color: white; border: 2px solid rgba(168,85,247,0.5); box-shadow: 0 10px 25px rgba(168,85,247,0.25);',
  'ETECH': 'background: linear-gradient(135deg, #f97316, #c2410c); color: white; border: 2px solid rgba(251,146,60,0.5); box-shadow: 0 10px 25px rgba(249,115,22,0.25);',
  'NTC': 'background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: 2px solid rgba(96,165,250,0.5); box-shadow: 0 10px 25px rgba(59,130,246,0.25);',
}

const companyOrder = ['ETC1992', 'STC', 'ETECH', 'NTC']

const companyOrder = ['ETC1992', 'STC', 'ETECH', 'NTC']

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return []
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter(
    emp => emp.name.toLowerCase().includes(q) || (emp.employee_code && emp.employee_code.toLowerCase().includes(q))
  )
})

onMounted(async () => {
  getCurrentPosition()
  await fetchCompanies()
})

async function fetchCompanies() {
  try {
    const response = await axios.get('/api/companies')
    const all = response.data.data || []
    companies.value = companyOrder
      .map(name => all.find(c => c.name === name))
      .filter(Boolean)
  } catch (error) {
    console.error('Error fetching companies:', error)
    companies.value = [
      { id: 2, name: 'ETC1992' },
      { id: 4, name: 'STC' },
      { id: 3, name: 'ETECH' },
      { id: 1, name: 'NTC' }
    ]
  }
}

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

async function searchEmployees() {
  if (!selectedCompany.value) return
  loading.value = true
  try {
    const response = await axios.post('/api/employee/auth/search', {
      company_id: selectedCompany.value.id,
      query: ''
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