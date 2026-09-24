<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ขอเพิ่มเวลา</h1>
        <span
          v-if="pendingCounts.ot > 0"
          class="px-2 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full"
        >
          โอที {{ pendingCounts.ot }}
        </span>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 pb-28 sm:pb-6">
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <!-- เข้างาน / ออกงาน -->
        <router-link to="/employee" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-indigo-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">เข้างาน / ออกงาน</h2>
            <p class="text-gray-400 text-xs mt-1">สแกนใบหน้าเช็คเวลา</p>
          </div>
        </router-link>

        <!-- ขอโอที -->
        <router-link v-if="hasOt" to="/employee/ot" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-amber-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอโอที</h2>
            <p class="text-gray-400 text-xs mt-1">ทำโอทีนอกเวลา</p>
            <div v-if="pendingCounts.ot > 0" class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
              <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.ot }}
            </div>
          </div>
        </router-link>

        <!-- เช็คอินโอที -->
        <router-link v-if="hasOt" to="/employee" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-orange-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">เช็คอินโอที</h2>
            <p class="text-gray-400 text-xs mt-1">สแกนหน้าเริ่มทำโอที</p>
          </div>
        </router-link>

        <!-- เช็คเอาท์โอที -->
        <router-link v-if="hasOt" to="/employee" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-purple-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">เช็คเอาท์โอที</h2>
            <p class="text-gray-400 text-xs mt-1">สแกนหน้าจบโอที</p>
          </div>
        </router-link>

        <!-- ขอเปลี่ยนกะ -->
        <router-link v-if="hasShifts" to="/employee/shift-request" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-indigo-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอเปลี่ยนกะ</h2>
            <p class="text-gray-400 text-xs mt-1">ขอ/แก้/ลบกะ</p>
          </div>
        </router-link>

        <!-- ย้ายกะ -->
        <router-link v-if="hasShifts" to="/employee/shift-swap" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center h-full">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-pink-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ย้ายกะ</h2>
            <p class="text-gray-400 text-xs mt-1">สลับเวรกับเพื่อน</p>
          </div>
        </router-link>
      </div>

      <p v-if="!hasOt && !hasShifts" class="text-center text-gray-400 text-xs mt-6">
        สิทธิ์โอทีและเปลี่ยน/ย้ายกะจะแสดงเมื่อตั้งค่าให้กับบัญชีของคุณ
      </p>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import store from '../../store'
import api from '../../services/api'
import { isTopManagement } from '../../constants/position'

const pendingCounts = ref({ leave: 0, ot: 0, wfh: 0 })

const isExec = computed(() => isTopManagement(store.user?.position_level))
const hasOt = computed(() => !isExec.value && (store.user?.has_ot === true || store.user?.has_ot === 1))
const hasShifts = computed(() => {
  if (isExec.value) return false
  const shifts = store.user?.work_shifts
  return Array.isArray(shifts) && shifts.length > 0
})

onMounted(async () => {
  try {
    const res = await api.get('/api/employee/requests/pending-count')
    if (res.data.success) {
      pendingCounts.value = res.data.data
    }
  } catch {
    // ignore
  }
})
</script>
