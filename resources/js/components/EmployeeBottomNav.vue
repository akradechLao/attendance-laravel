<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-40 sm:hidden bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(15,23,42,0.08)]"
    style="padding-bottom: env(safe-area-inset-bottom, 0)"
    aria-label="เมนูพนักงาน"
  >
    <div ref="scroller" class="flex overflow-x-auto no-scrollbar px-1 py-1.5 gap-0.5">
      <router-link
        v-for="item in visibleItems"
        :key="item.path"
        :to="item.path"
        class="relative flex flex-col items-center justify-center shrink-0 min-w-[64px] max-w-[76px] px-1.5 py-1.5 rounded-xl transition-colors touch-target"
        :class="isActive(item.path)
          ? 'bg-blue-50 text-blue-600'
          : 'text-gray-500 active:bg-gray-50'"
      >
        <span class="relative text-lg leading-none">
          <span>{{ item.icon }}</span>
          <span
            v-if="badgeFor(item) > 0"
            class="absolute -top-1.5 -right-2.5 bg-red-500 text-white text-[9px] font-bold min-w-[16px] h-4 px-0.5 rounded-full flex items-center justify-center leading-none"
          >
            {{ badgeFor(item) > 99 ? '99+' : badgeFor(item) }}
          </span>
        </span>
        <span
          class="mt-1 text-[10px] leading-tight text-center font-medium w-full"
          style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"
        >
          {{ item.short || item.label }}
        </span>
      </router-link>
    </div>
  </nav>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import store from '../store'
import { isTopManagement } from '../constants/position'

const props = defineProps({
  pendingCounts: {
    type: Object,
    default: () => ({ leave: 0, ot: 0, wfh: 0 }),
  },
  announcementCount: { type: Number, default: 0 },
  unreadCount: { type: Number, default: 0 },
})

const route = useRoute()
const scroller = ref(null)

const allItems = [
  { path: '/employee/dashboard', label: 'สรุปวันนี้', short: 'หน้าแรก', icon: '🏠' },
  { path: '/employee/history', label: 'ประวัติเข้างาน', short: 'ประวัติ', icon: '🕑' },
  { path: '/employee/stats', label: 'ประวัติการเตือน', short: 'เตือน', icon: '⚠️' },
  { path: '/employee/leave', label: 'ขอลางาน', short: 'ขอลางาน', icon: '📅', badgeKey: 'leave' },
  { path: '/employee/ot', label: 'ขอโอที', short: 'โอที', icon: '⏰', badgeKey: 'ot', requireOt: true },
  { path: '/employee/wfh', label: 'ขอ WFH', short: 'WFH', icon: '🏡', badgeKey: 'wfh' },
  { path: '/employee/shift-swap', label: 'ขอย้ายเวร', short: 'ย้ายเวร', icon: '🔁', requireShifts: true },
  { path: '/employee/shift-request', label: 'ร้องขอเข้ากะ', short: 'เข้ากะ', icon: '🕒', requireShifts: true },
  { path: '/employee/schedule', label: 'ตารางเวร', short: 'ตารางเวร', icon: '🗓️', requireShifts: true },
  { path: '/employee/holidays', label: 'ปฏิทินวันหยุด', short: 'วันหยุด', icon: '🎌' },
  { path: '/employee/announcements', label: 'ประกาศ', short: 'ประกาศ', icon: '📢', badgeCount: 'announcements' },
  { path: '/employee/notifications', label: 'การแจ้งเตือน', short: 'แจ้งเตือน', icon: '🔔', badgeCount: 'unread' },
  { path: '/employee/profile', label: 'ข้อมูลส่วนตัว', short: 'โปรไฟล์', icon: '👤' },
  { path: '/employee/payslip', label: 'สลิปเงินเดือน', short: 'สลิป', icon: '💵' },
  { path: '/employee/change-password', label: 'เปลี่ยนรหัสผ่าน', short: 'รหัสผ่าน', icon: '🔒' },
]

const visibleItems = computed(() => {
  const user = store.user
  const isExec = isTopManagement(user?.position_level)
  const hasOt = user?.has_ot === true || user?.has_ot === 1
  const shifts = user?.work_shifts || []
  const hasShifts = Array.isArray(shifts) && shifts.length > 0

  return allItems.filter((item) => {
    if (item.requireOt && (isExec || !hasOt)) return false
    if (item.requireShifts && (isExec || !hasShifts)) return false
    return true
  })
})

function isActive(path) {
  return route.path === path || route.path.startsWith(path + '/')
}

function badgeFor(item) {
  if (item.badgeKey) return props.pendingCounts?.[item.badgeKey] || 0
  if (item.badgeCount === 'announcements') return props.announcementCount
  if (item.badgeCount === 'unread') return props.unreadCount
  return 0
}

async function scrollActiveIntoView() {
  await nextTick()
  const el = scroller.value?.querySelector('.bg-blue-50')
  if (el && el.scrollIntoView) {
    el.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
  }
}

watch(() => route.path, scrollActiveIntoView)
onMounted(scrollActiveIntoView)
</script>
