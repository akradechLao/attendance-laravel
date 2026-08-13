<template>
  <div class="min-h-screen relative overflow-hidden flex items-center justify-center p-4" style="background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 25%, #6366f1 50%, #8b5cf6 75%, #a855f7 100%)">
    <div class="fixed inset-0 -z-10">
      <div class="absolute top-10 left-10 w-72 h-72 bg-white/10 rounded-full blur-3xl"></div>
      <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-300/10 rounded-full blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-300/5 rounded-full blur-3xl"></div>
    </div>
    <div class="w-full max-w-5xl">
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white drop-shadow-lg">ระบบเช็คเวลาเข้างาน</h1>
        <p class="text-blue-100 mt-2 text-lg">ETC1992 Attendance System</p>
      </div>
      <div class="flex flex-col md:flex-row gap-6">
        <div class="flex-1">
          <router-link to="/employee" class="block">
            <div class="bg-white/15 backdrop-blur-md rounded-3xl h-full hover:bg-white/25 transition-all duration-300 cursor-pointer border border-white/20 shadow-xl overflow-hidden relative">
              <div class="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
              <div class="relative text-center py-10 px-6">
                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/20 flex items-center justify-center">
                  <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2">เป็นพนักงาน?</h2>
                <p class="text-lg text-white mb-6">เช็คเวลาเข้างานที่นี่</p>
                <div class="bg-white/15 rounded-2xl p-4 mb-6 mx-auto max-w-xs border border-white/10">
                  <p class="text-sm text-blue-100 mb-1">เวลาปัจจุบัน</p>
                  <p class="text-4xl font-bold text-white tracking-wider">{{ currentTime }}</p>
                  <p class="text-sm text-blue-100 mt-1">{{ currentDate }}</p>
                </div>
                <div class="bg-white text-blue-600 py-4 px-8 rounded-xl font-bold text-lg inline-flex items-center gap-3 shadow-xl">
                  <span>เริ่มสแกนหน้า</span>
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                </div>
                <p class="text-sm text-blue-100 mt-6">สแกนหน้าเพื่อบันทึกเวลาเข้างาน</p>
              </div>
            </div>
          </router-link>
        </div>
        <div class="flex-1">
          <div class="bg-white rounded-3xl shadow-2xl h-full overflow-hidden">
            <div class="flex border-b border-gray-200">
              <button @click="loginMode = 'admin'" :class="['flex-1 py-4 text-center font-semibold text-sm transition-all', loginMode === 'admin' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700']">ผู้ดูแลระบบ</button>
              <button @click="loginMode = 'employee'" :class="['flex-1 py-4 text-center font-semibold text-sm transition-all', loginMode === 'employee' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700']">พนักงาน</button>
            </div>
            <div class="p-8">
              <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg"><svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg></div>
                <h2 class="text-2xl font-bold text-gray-800">{{ loginMode === 'admin' ? 'เข้าสู่ระบบ' : 'เข้าสู่ระบบพนักงาน' }}</h2>
                <p class="text-gray-500 mt-1">{{ loginMode === 'admin' ? 'สำหรับผู้ดูแลระบบ' : 'เข้าสู่ระบบเพื่อขอลา/โอที/ปฏิบัติงานนอกสถานที่' }}</p>
              </div>
              <div v-if="error" class="mb-4 p-4 bg-red-50 rounded-xl border border-red-200"><p class="text-red-600 text-sm font-medium">{{ error }}</p></div>
              <form v-if="loginMode === 'admin'" @submit.prevent="handleAdminLogin">
                <div class="mb-4"><label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อผู้ใช้</label><div class="relative"><input v-model="adminForm.username" type="text" class="input-theme pl-12" placeholder="กรอกชื่อผู้ใช้" required /><svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div></div>
                <button type="submit" :disabled="loading" class="btn-primary-new w-full py-3 text-lg flex items-center justify-center gap-2"><LoadingSpinner v-if="loading" size="sm" /><span>{{ loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}</span></button>
              </form>
              <form v-else @submit.prevent="handleEmployeeLogin">
                <div class="mb-4"><label class="block text-sm font-semibold text-gray-700 mb-2">บริษัท</label><select v-model="employeeForm.company_id" class="input-theme" required><option value="" disabled>เลือกบริษัท</option><option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div class="mb-4"><label class="block text-sm font-semibold text-gray-700 mb-2">รหัสพนักงาน</label><div class="relative"><input v-model="employeeForm.employee_code" type="text" class="input-theme pl-12" placeholder="กรอกรหัสพนักงาน" required /><svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" /></svg></div></div>
                <div class="mb-6"><label class="block text-sm font-semibold text-gray-700 mb-2">รหัสผ่าน</label><div class="relative"><input v-model="employeeForm.password" :type="showPassword ? 'text' : 'password'" class="input-theme pl-12" placeholder="กรอกรหัสผ่าน" required /><svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg></div></div>
                <button type="submit" :disabled="loading" class="btn-primary-new w-full py-3 text-lg flex items-center justify-center gap-2"><LoadingSpinner v-if="loading" size="sm" /><span>{{ loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}</span></button>
              </form>
            </div>
            <div class="px-8 pb-6 text-center"><p class="text-gray-400 text-sm">© 2024 ETC1992 Attendance System</p></div>
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
const adminForm = reactive({ username: '', password: '' })
const employeeForm = reactive({ company_id: '', employee_code: '', password: '' })
function updateTime() {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
  currentDate.value = now.toLocaleDateString('th-TH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}
onMounted(async () => {
  updateTime()
  timeInterval = setInterval(updateTime, 1000)
  try { const res = await axios.get('/api/companies'); if (res.data.success) companies.value = res.data.data } catch (e) {}
})
onUnmounted(() => { if (timeInterval) clearInterval(timeInterval) })
async function handleAdminLogin() {
  loading.value = true; error.value = ''
  try { const response = await axios.post('/api/auth/login', { username: adminForm.username, password: adminForm.password }); const { data } = response.data; setToken(data.token); setCurrentUser(data.user); axios.defaults.headers.common['Authorization'] = 'Bearer ' + data.token; router.push('/dashboard') } catch (err) { error.value = err.response?.data?.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' } finally { loading.value = false }
}
async function handleEmployeeLogin() {
  loading.value = true; error.value = ''
  try { const response = await axios.post('/api/employee/auth/login', { company_id: employeeForm.company_id, employee_code: employeeForm.employee_code, password: employeeForm.password }); const { data } = response.data; setToken(data.token); setCurrentUser(data.user); axios.defaults.headers.common['Authorization'] = 'Bearer ' + data.token; router.push('/employee/menu') } catch (err) { error.value = err.response?.data?.message || 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง' } finally { loading.value = false }
}
</script>
