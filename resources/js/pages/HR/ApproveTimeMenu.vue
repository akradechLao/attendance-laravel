<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold text-navy">อนุมัติเพิ่มเวลา</h1>
      <span v-if="totalCount > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
        {{ totalCount }}
      </span>
    </div>

    <p class="text-gray-500 text-sm -mt-2">
      รวมคำขอที่เกี่ยวกับเวลาเข้า-ออกงาน โอที เปลี่ยนกะ และย้ายกะ
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- Checkout -->
      <router-link v-if="isAdmin" to="/estimated-checkouts" class="block group">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500 flex items-center justify-center shrink-0 shadow">
              <span class="text-xl">🕐</span>
            </div>
            <div class="min-w-0">
              <h2 class="font-bold text-gray-800">Checkout รออนุมัติ</h2>
              <p class="text-gray-400 text-xs mt-0.5">ออกงานประมาณการ</p>
              <div v-if="counts.estimated_checkout > 0" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                ค้าง {{ counts.estimated_checkout }}
              </div>
            </div>
          </div>
        </div>
      </router-link>

      <!-- OT -->
      <router-link :to="otPath" class="block group">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500 flex items-center justify-center shrink-0 shadow">
              <span class="text-xl">⏰</span>
            </div>
            <div class="min-w-0">
              <h2 class="font-bold text-gray-800">อนุมัติโอที</h2>
              <p class="text-gray-400 text-xs mt-0.5">คำขอทำโอที</p>
              <div v-if="counts.ot > 0" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                ค้าง {{ counts.ot }}
              </div>
            </div>
          </div>
        </div>
      </router-link>

      <!-- Shift request (เปลี่ยนกะ) — ซ่อนถ้าไม่มีสิทธิ์อนุมัติจริง (chain/delegated/super_admin) -->
      <router-link v-if="caps?.shift_request" to="/shift-request-approval" class="block group">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0 shadow">
              <span class="text-xl">📋</span>
            </div>
            <div class="min-w-0">
              <h2 class="font-bold text-gray-800">อนุมัติเปลี่ยนกะ</h2>
              <p class="text-gray-400 text-xs mt-0.5">ขอ/แก้/ลบกะ</p>
              <div v-if="counts.shift_request > 0" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                ค้าง {{ counts.shift_request }}
              </div>
            </div>
          </div>
        </div>
      </router-link>

      <!-- Shift swap (ย้ายกะ) — ซ่อนถ้าไม่มีสิทธิ์อนุมัติจริง -->
      <router-link v-if="caps?.shift_swap" to="/shift-swap-approval" class="block group">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-pink-500 flex items-center justify-center shrink-0 shadow">
              <span class="text-xl">🔁</span>
            </div>
            <div class="min-w-0">
              <h2 class="font-bold text-gray-800">อนุมัติย้ายกะ</h2>
              <p class="text-gray-400 text-xs mt-0.5">สลับเวรกับเพื่อน</p>
              <div v-if="counts.shift_swap > 0" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                ค้าง {{ counts.shift_swap }}
              </div>
            </div>
          </div>
        </div>
      </router-link>
    </div>

    <!-- Quick link to full pending list -->
    <router-link to="/pending-approvals" class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
      ดูรายการอนุมัติทั้งหมด (ลางาน WFH ฯลฯ)
    </router-link>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import store from '../../store'
import api from '../../services/api'

const isAdmin = computed(() => ['admin', 'super_admin'].includes(store.user?.role))
const isManager = computed(() => store.user?.role === 'manager' || store.user?.position_level === 'manager')
const role = computed(() => store.user?.role || 'employee')
const caps = ref(null)

const counts = ref({
  ot: 0,
  shift_swap: 0,
  shift_request: 0,
  estimated_checkout: 0,
})

const otPath = computed(() => {
  if (role.value === 'manager') return '/manager/ot-approval'
  if (role.value === 'admin' || role.value === 'super_admin') return '/supervisor/ot-approval'
  return '/supervisor/ot-approval'
})

const totalCount = computed(() =>
  counts.value.ot + counts.value.shift_swap + counts.value.shift_request + counts.value.estimated_checkout
)

onMounted(async () => {
  try {
    const capRes = await api.get('/api/auth/approval-capabilities')
    if (capRes.data.success) caps.value = capRes.data.data
  } catch { /* ignore */ }
  try {
    const res = await api.get('/api/pending-approvals', { params: { counts_only: 1 } })
    if (res.data.success) {
      const c = res.data.data.counts || {}
      counts.value = {
        ot: c.ot || 0,
        shift_swap: c.shift_swap || 0,
        shift_request: c.shift_request || 0,
        estimated_checkout: c.estimated_checkout || 0,
      }
    }
  } catch {
    // ignore
  }
})
</script>
