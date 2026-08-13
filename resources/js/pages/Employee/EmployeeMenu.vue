<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg shadow">
            {{ initials }}
          </div>
          <div>
            <p class="text-gray-800 font-semibold">{{ store.user?.name }}</p>
            <p class="text-blue-600 text-sm">{{ store.user?.company?.name }}</p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-red-50 text-gray-500 hover:text-red-500 text-sm font-medium transition-colors"
        >
          ออกจากระบบ
        </button>
      </div>
    </header>

    <!-- Main Menu -->
    <main class="max-w-4xl mx-auto px-4 py-10">
      <!-- Greeting -->
      <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          สวัสดีครับ {{ store.user?.nickname || store.user?.name }}
        </h1>
        <p class="text-gray-500 text-lg">เลือกเมนูที่ต้องการ</p>
      </div>

      <!-- Menu Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <!-- ขอลางาน -->
        <router-link to="/employee/leave" class="block group">
          <div class="bg-white rounded-2xl p-8 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
            <div class="w-16 h-16 rounded-2xl bg-blue-500 flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">ขอลางาน</h2>
            <p class="text-gray-500 text-sm">ขอลาพักผ่อน ลากิจ ลาป่วย</p>
            <div v-if="pendingCounts.leave > 0" class="mt-3 inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-medium border border-amber-200">
              <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.leave }}
            </div>
          </div>
        </router-link>

        <!-- ขอโอที -->
        <router-link to="/employee/ot" class="block group">
          <div class="bg-white rounded-2xl p-8 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
            <div class="w-16 h-16 rounded-2xl bg-amber-500 flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">ขอโอที</h2>
            <p class="text-gray-500 text-sm">ขอทำโอทีนอกเวลา</p>
            <div v-if="pendingCounts.ot > 0" class="mt-3 inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-medium border border-amber-200">
              <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.ot }}
            </div>
          </div>
        </router-link>

        <!-- ขอปฏิบัติงานนอกสถานที่ -->
        <router-link to="/employee/wfh" class="block group">
          <div class="bg-white rounded-2xl p-8 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500 flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">ขอปฏิบัติงานนอกสถานที่</h2>
            <p class="text-gray-500 text-sm">ขอทำงานนอกสำนักงาน</p>
            <div v-if="pendingCounts.wfh > 0" class="mt-3 inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-medium border border-amber-200">
              <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.wfh }}
            </div>
          </div>
        </router-link>
      </div>

      <!-- Pending Summary -->
      <div class="bg-white rounded-2xl p-8 border border-gray-200 shadow-md mb-8">
        <h3 class="text-lg font-bold text-gray-800 mb-6 text-center">รายการที่รอการอนุมัติ</h3>
        <div class="grid grid-cols-3 gap-6">
          <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-50 flex items-center justify-center mb-3 border border-blue-200">
              <span class="text-2xl font-bold text-blue-600">{{ pendingCounts.leave }}</span>
            </div>
            <p class="text-gray-600 text-sm font-medium">ลางาน</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 flex items-center justify-center mb-3 border border-amber-200">
              <span class="text-2xl font-bold text-amber-500">{{ pendingCounts.ot }}</span>
            </div>
            <p class="text-gray-600 text-sm font-medium">โอที</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-50 flex items-center justify-center mb-3 border border-emerald-200">
              <span class="text-2xl font-bold text-emerald-500">{{ pendingCounts.wfh }}</span>
            </div>
            <p class="text-gray-600 text-sm font-medium">ปฏิบัติงานนอกสถานที่</p>
          </div>
        </div>
      </div>

      <!-- Change Password -->
      <div class="text-center">
        <router-link to="/employee/change-password" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white hover:bg-gray-50 border border-gray-200 text-gray-500 hover:text-gray-700 text-sm font-medium transition-all shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
          เปลี่ยนรหัสผ่าน
        </router-link>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import store, { logout } from '../../store'
import axios from 'axios'

const router = useRouter()
const pendingCounts = ref({ leave: 0, ot: 0, wfh: 0 })

const initials = computed(() => {
  const name = store.user?.name || 'E'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function handleLogout() {
  logout()
  router.push('/login')
}

onMounted(async () => {
  const token = localStorage.getItem('token')
  if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  }
  try {
    const res = await axios.get('/api/employee/requests/pending-count')
    if (res.data.success) {
      pendingCounts.value = res.data.data
    }
  } catch (e) {
    // ignore
  }
})
</script>
