<template>
  <div class="min-h-screen bg-gradient-to-br from-navy via-slate-800 to-blue-900">
    <!-- Header -->
    <header class="bg-white/10 backdrop-blur-sm border-b border-white/10">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
            {{ initials }}
          </div>
          <div>
            <p class="text-white font-semibold">{{ store.user?.name }}</p>
            <p class="text-blue-200 text-sm">{{ store.user?.company?.name }}</p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm transition-colors"
        >
          ออกจากระบบ
        </button>
      </div>
    </header>

    <!-- Main Menu -->
    <main class="max-w-4xl mx-auto px-4 py-12">
      <h1 class="text-3xl font-bold text-white text-center mb-2">สวัสดีครับ {{ store.user?.nickname || store.user?.name }}</h1>
      <p class="text-blue-200 text-center mb-12">เลือกเมนูที่ต้องการ</p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- ขอลางาน -->
        <router-link to="/employee/leave" class="block">
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20 hover:bg-white/20 hover:border-white/30 transition-all duration-300 cursor-pointer group">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">ขอลางาน</h2>
            <p class="text-blue-200 text-sm">ขอลาพักผ่อน ลากิจ ลาป่วย</p>
          </div>
        </router-link>

        <!-- ขอโอที -->
        <router-link to="/employee/ot" class="block">
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20 hover:bg-white/20 hover:border-white/30 transition-all duration-300 cursor-pointer group">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">ขอโอที</h2>
            <p class="text-blue-200 text-sm">ขอทำโอทีนอกเวลา</p>
          </div>
        </router-link>

        <!-- ขอ WFH -->
        <router-link to="/employee/wfh" class="block">
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20 hover:bg-white/20 hover:border-white/30 transition-all duration-300 cursor-pointer group">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">ขอ WFH</h2>
            <p class="text-blue-200 text-sm">ขอทำงานที่บ้าน</p>
          </div>
        </router-link>
      </div>

      <!-- Info -->
      <div class="mt-12 text-center">
        <p class="text-blue-300 text-sm">รายการที่รอการอนุมัติ</p>
        <div class="flex justify-center gap-8 mt-4">
          <div class="text-center">
            <p class="text-3xl font-bold text-white">{{ pendingCounts.leave }}</p>
            <p class="text-blue-200 text-sm">ลางาน</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-white">{{ pendingCounts.ot }}</p>
            <p class="text-blue-200 text-sm">โอที</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-white">{{ pendingCounts.wfh }}</p>
            <p class="text-blue-200 text-sm">WFH</p>
          </div>
        </div>

        <router-link to="/employee/change-password" class="mt-8 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm transition-colors">
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
