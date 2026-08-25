<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ข้อมูลส่วนตัว</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-3 text-sm">กำลังโหลด...</p>
      </div>

      <div v-else-if="profile" class="space-y-4">
        <!-- Profile Card -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm text-center">
          <div class="w-20 h-20 mx-auto rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl font-bold mb-3 shadow-lg">
            {{ initials }}
          </div>
          <h2 class="text-xl font-bold text-gray-800">{{ profile.name }}</h2>
          <p class="text-blue-600 text-sm">{{ profile.nickname || '-' }}</p>
          <p class="text-gray-400 text-xs mt-1">{{ profile.employee_code }}</p>
        </div>

        <!-- Info Cards -->
        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wide">ข้อมูลการทำงาน</h3>
          <div class="space-y-3">
            <InfoRow label="บริษัท" :value="profile.company" />
            <InfoRow label="แผนก" :value="profile.department" />
            <InfoRow label="ตำแหน่ง" :value="profile.position" />
            <InfoRow label="สถานะ" :value="profile.status === 'active' ? 'ปกติ' : 'ไม่ active'" />
            <InfoRow label="วันที่เริ่มงาน" :value="profile.start_date" />
            <InfoRow label="มี OT" :value="profile.has_ot ? 'ใช่' : 'ไม่'" />
          </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wide">ข้อมูลติดต่อ</h3>
          <div class="space-y-3">
            <InfoRow label="เบอร์โทร" :value="profile.phone || '-'" />
            <InfoRow label="อีเมล" :value="profile.email || '-'" />
          </div>
        </div>

        <div v-if="profile.work_shifts?.length" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wide">เวรปัจจุบัน</h3>
          <div class="space-y-3">
            <div v-for="(shift, i) in profile.work_shifts" :key="i" class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
              <div>
                <p class="font-medium text-gray-800 text-sm">กลุ่ม {{ shift.group_number }}</p>
                <p class="text-gray-400 text-xs">{{ shift.work_hours }} ชม.</p>
              </div>
              <p class="text-blue-600 text-sm font-medium">{{ shift.start_time }} - {{ shift.end_time }}</p>
            </div>
          </div>
        </div>

        <div v-if="profile.office_location" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wide">สถานที่เข้างาน</h3>
          <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
            <span class="text-lg">📍</span>
            <span class="text-gray-800 text-sm font-medium">{{ profile.office_location }}</span>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wide">ข้อมูลใบหน้า</h3>
          <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
            <div :class="profile.face_data_count > 0 ? 'bg-emerald-100' : 'bg-amber-100'" class="w-10 h-10 rounded-full flex items-center justify-center">
              <svg v-if="profile.face_data_count > 0" class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <svg v-else class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div>
              <p class="text-gray-800 text-sm font-medium">
                {{ profile.face_data_count > 0 ? 'ลงทะเบียนแล้ว' : 'ยังไม่ได้ลงทะเบียน' }}
              </p>
              <p class="text-gray-400 text-xs">{{ profile.face_data_count }} / 5 รูป</p>
            </div>
          </div>
          <p v-if="profile.face_data_count === 0" class="mt-2 text-amber-600 text-xs">
            กรุณาลงทะเบียนใบหน้าที่หน้าสแกนเข้างาน เพื่อใช้ Face Scan
          </p>
        </div>
      </div>

      <div v-else class="text-center py-12 text-gray-400 text-sm">ไม่พบข้อมูล</div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import store from '../../store'

const loading = ref(true)
const profile = ref(null)

const initials = computed(() => {
  const name = profile.value?.name || 'E'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function InfoRow({ label, value }) {
  return { label, value }
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/employee/profile')
    if (res.data.success) {
      profile.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
