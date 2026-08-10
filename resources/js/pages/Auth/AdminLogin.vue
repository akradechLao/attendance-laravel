<template>
  <div class="min-h-screen bg-cream flex items-center justify-center p-4">
    <div class="w-full max-w-5xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-navy">ระบบเช็คเวลาเข้างาน</h1>
        <p class="text-gray-500 mt-2">ETC1992 Attendance System</p>
      </div>

      <div class="flex flex-col md:flex-row gap-6">
        <!-- ซ้าย: เช็คอินพนักงาน -->
        <div class="flex-1">
          <router-link to="/employee" class="block">
            <div class="card bg-gradient-to-br from-green-500 via-emerald-500 to-teal-600 text-white h-full hover:shadow-2xl transform hover:scale-[1.02] transition-all duration-300 cursor-pointer">
              <!-- Content -->
              <div class="text-center py-8">
                <!-- Icon -->
                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                  <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>

                <!-- Title -->
                <h2 class="text-3xl font-bold mb-3">เป็นพนักงาน?</h2>
                <p class="text-xl text-green-100 mb-6">เช็คเวลาเข้างานที่นี่</p>

                <!-- Current Time -->
                <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 mb-6 mx-auto max-w-xs">
                  <p class="text-sm text-green-100 mb-1">เวลาปัจจุบัน</p>
                  <p class="text-4xl font-bold text-white tracking-wider">{{ currentTime }}</p>
                  <p class="text-sm text-green-100 mt-1">{{ currentDate }}</p>
                </div>

                <!-- Button -->
                <div class="bg-white text-green-600 py-4 px-8 rounded-xl font-bold text-lg inline-flex items-center gap-3 shadow-lg">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>เริ่มสแกนหน้า</span>
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                </div>

                <!-- Info -->
                <p class="text-sm text-green-100 mt-6">สแกนหน้าเพื่อบันทึกเวลาเข้างาน</p>
              </div>
            </div>
          </router-link>
        </div>

        <!-- ขวา: เข้าสู่ระบบ -->
        <div class="flex-1">
          <div class="card h-full">
            <!-- Tabs -->
            <div class="flex border-b border-gray-200">
              <button
                @click="loginMode = 'admin'"
                :class="['flex-1 py-3 text-center font-semibold text-sm transition-colors', loginMode === 'admin' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700']"
              >
                ผู้ดูแลระบบ
              </button>
              <button
                @click="loginMode = 'employee'"
                :class="['flex-1 py-3 text-center font-semibold text-sm transition-colors', loginMode === 'employee' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700']"
              >
                พนักงาน
              </button>
            </div>

            <div class="p-6">
              <div class="text-center mb-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-navy flex items-center justify-center">
                  <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <h2 class="text-2xl font-bold text-navy">{{ loginMode === 'admin' ? 'เข้าสู่ระบบ' : 'เข้าสู่ระบบพนักงาน' }}</h2>
                <p class="text-gray-500 mt-1">{{ loginMode === 'admin' ? 'สำหรับผู้ดูแลระบบ' : 'เข้าสู่ระบบเพื่อขอลา/โอที/ปฏิบัติงานนอกสถานที่' }}</p>
              </div>

              <!-- Error message -->
              <div v-if="error" class="mb-4 p-4 bg-red-50 rounded-lg border border-red-200">
                <p class="text-red-600 text-sm">{{ error }}</p>
              </div>

              <!-- Admin Login -->
              <form v-if="loginMode === 'admin'" @submit.prevent="handleAdminLogin">
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อผู้ใช้</label>
                  <div class="relative">
                    <input v-model="adminForm.username" type="text" class="input-field pl-12" placeholder="กรอกชื่อผู้ใช้" required />
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                </div>
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่าน</label>
                  <div class="relative">
                    <input v-model="adminForm.password" :type="showPassword ? 'text' : 'password'" class="input-field pl-12 pr-12" placeholder="กรอกรหัสผ่าน" required />
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                      <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    </button>
                  </div>
                </div>
                <button type="submit" :disabled="loading" class="w-full btn-primary py-3 text-lg font-semibold flex items-center justify-center gap-2">
                  <LoadingSpinner v-if="loading" size="sm" />
                  <span>{{ loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}</span>
                </button>
              </form>

              <!-- Employee Login -->
              <form v-else @submit.prevent="handleEmployeeLogin">
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">บริษัท</label>
                  <select v-model="employeeForm.company_id" class="input-field" required>
                    <option value="" disabled>เลือกบริษัท</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">รหัสพนักงาน</label>
                  <div class="relative">
                    <input v-model="employeeForm.employee_code" type="text" class="input-field pl-12" placeholder="กรอกรหัสพนักงาน" required />
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                    </svg>
                  </div>
                </div>
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่าน</label>
                  <div class="relative">
                    <input v-model="employeeForm.password" :type="showPassword ? 'text' : 'password'" class="input-field pl-12" placeholder="กรอกรหัสผ่าน" required />
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                </div>
                <button type="submit" :disabled="loading" class="w-full btn-primary py-3 text-lg font-semibold flex items-center justify-center gap-2">
                  <LoadingSpinner v-if="loading" size="sm" />
                  <span>{{ loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}</span>
                </button>
              </form>
            </div>

            <!-- Footer -->
            <div class="mt-6 text-center">
              <p class="text-gray-400 text-sm">© 2024 ETC1992 Attendance System</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { setCurrentUser, setToken } from '../../store'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const loginMode = ref('admin')
const currentTime = ref('')
const currentDate = ref('')
const companies = ref([])
let timeInterval = null

const adminForm = reactive({
  username: '',
  password: ''
})

const employeeForm = reactive({
  company_id: '',
  employee_code: '',
  password: ''
})

function updateTime() {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('th-TH', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
  currentDate.value = now.toLocaleDateString('th-TH', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

onMounted(async () => {
  updateTime()
  timeInterval = setInterval(updateTime, 1000)
  try {
    const res = await axios.get('/api/companies')
    if (res.data.success) companies.value = res.data.data
  } catch (e) {
    // ignore
  }
})

onUnmounted(() => {
  if (timeInterval) {
    clearInterval(timeInterval)
  }
})

async function handleAdminLogin() {
  loading.value = true
  error.value = ''

  try {
    const response = await axios.post('/api/auth/login', {
      username: adminForm.username,
      password: adminForm.password
    })

    const { data } = response.data
    setToken(data.token)
    setCurrentUser(data.user)

    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`

    router.push('/dashboard')
  } catch (err) {
    error.value = err.response?.data?.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
  } finally {
    loading.value = false
  }
}

async function handleEmployeeLogin() {
  loading.value = true
  error.value = ''

  try {
    const response = await axios.post('/api/employee/auth/login', {
      company_id: employeeForm.company_id,
      employee_code: employeeForm.employee_code,
      password: employeeForm.password
    })

    const { data } = response.data
    setToken(data.token)
    setCurrentUser(data.user)

    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`

    router.push('/employee/menu')
  } catch (err) {
    error.value = err.response?.data?.message || 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง'
  } finally {
    loading.value = false
  }
}
</script>
