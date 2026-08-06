<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">แดชบอร์ด</h1>
          <p class="text-gray-500">ภาพรวมการเข้างานวันนี้</p>
        </div>
        <div class="flex items-center gap-4">
          <select v-model="selectedCompany" class="input-field w-auto">
            <option value="">ทุกบริษัท</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
          <button @click="refreshData" class="btn-secondary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            รีเฟรช
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="card animate-fadeIn">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <div>
                <p class="text-sm text-gray-500">พนักงานทั้งหมด</p>
                <p class="text-2xl font-bold text-navy">{{ stats.total }}</p>
              </div>
            </div>
          </div>

          <div class="card animate-fadeIn" style="animation-delay: 0.1s">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="text-sm text-gray-500">เข้างานวันนี้</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.present }}</p>
              </div>
            </div>
          </div>

          <div class="card animate-fadeIn" style="animation-delay: 0.2s">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="text-sm text-gray-500">สาย</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats.late }}</p>
              </div>
            </div>
          </div>

          <div class="card animate-fadeIn" style="animation-delay: 0.3s">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="text-sm text-gray-500">ไม่เข้างาน</p>
                <p class="text-2xl font-bold text-red-600">{{ stats.absent }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Today's Attendance Table -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-navy">รายการเข้างานวันนี้</h2>
            <span class="text-sm text-gray-500">{{ todayDate }}</span>
          </div>

          <div v-if="attendances.length === 0" class="text-center py-8 text-gray-500">
            ยังไม่มีรายการเข้างานวันนี้
          </div>

          <AttendanceTable v-else :attendances="attendances" />
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import AttendanceTable from '../../components/AttendanceTable.vue'

const loading = ref(true)
const selectedCompany = ref('')
const companies = ref([])
const attendances = ref([])
const stats = reactive({
  total: 0,
  present: 0,
  late: 0,
  absent: 0
})

const todayDate = new Date().toLocaleDateString('th-TH', {
  year: 'numeric',
  month: 'long',
  day: 'numeric'
})

let refreshInterval = null

async function fetchData() {
  try {
    const params = selectedCompany.value ? { company_id: selectedCompany.value } : {}
    const [statsRes, attendanceRes, companiesRes] = await Promise.all([
      axios.get('/api/dashboard/stats', { params }),
      axios.get('/api/dashboard/attendance-today', { params }),
      axios.get('/api/companies')
    ])

    Object.assign(stats, statsRes.data)
    attendances.value = attendanceRes.data.data || attendanceRes.data
    companies.value = companiesRes.data.data || companiesRes.data
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  } finally {
    loading.value = false
  }
}

function refreshData() {
  loading.value = true
  fetchData()
}

onMounted(() => {
  fetchData()
  refreshInterval = setInterval(fetchData, 30000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>
