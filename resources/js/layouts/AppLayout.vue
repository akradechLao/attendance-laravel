<template>
  <div class="min-h-screen flex page-fresh">
    <!-- Mobile overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-40 lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white transform transition-transform duration-300 lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-200">
          <h1 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            Attendance System
          </h1>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto custom-scrollbar">
          <template v-for="item in filteredNavItems" :key="(item && (item.section || item.path)) || Math.random()">
            <!-- Section Header -->
            <div v-if="item.section" class="pt-4 pb-2">
              <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ item.section }}</p>
            </div>

            <!-- Nav Link -->
            <router-link
              v-else
              :to="item.path"
              class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-white transition-colors duration-200"
              :class="{ 'bg-blue-500 text-white': isActive(item.path) }"
              @click="sidebarOpen = false"
            >
              <span class="text-lg">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </router-link>
          </template>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200">
          <router-link
            to="/employee"
            class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-white transition-colors duration-200"
            @click="sidebarOpen = false"
          >
            <span class="text-lg">👁</span>
            <span>โหมด Kiosk</span>
          </router-link>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
      <!-- Header -->
      <header class="bg-white shadow-sm sticky top-0 z-30">
        <div class="flex items-center justify-between px-6 py-4">
          <!-- Mobile menu button -->
          <button
            class="lg:hidden p-2 rounded-lg hover:bg-gray-100"
            @click="sidebarOpen = true"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <button
            @click="goHome"
            class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-navy transition-colors"
            title="กลับหน้าแดชบอร์ด"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" />
            </svg>
          </button>

          <div class="hidden lg:block"></div>

          <!-- User info -->
          <div class="flex items-center gap-4">
            <div class="text-right">
              <p class="text-sm font-medium text-gray-700">{{ store.user?.name || 'Admin' }}</p>
              <p class="text-xs text-gray-500">{{ roleLabel }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
              {{ initials }}
            </div>
            <button
              @click="handleLogout"
              class="p-2 rounded-lg hover:bg-red-50 text-gray-500 hover:text-red-500 transition-colors"
              title="ออกจากระบบ"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import store, { logout } from '../store'

const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)

const userRole = computed(() => store.user?.role || 'employee')
const isAdmin = computed(() => ['admin', 'super_admin'].includes(userRole.value))
const isSuperAdmin = computed(() => userRole.value === 'super_admin')

const initials = computed(() => {
  const name = store.user?.name || 'A'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

const roleLabels = {
  employee: 'พนักงาน',
  admin: 'HR Admin',
  super_admin: 'ผู้ดูแลระบบ'
}
const roleLabel = computed(() => roleLabels[userRole.value] || userRole.value)

const navItems = [
  { section: 'ภาพรวม' },
  { path: '/dashboard', label: 'แดชบอร์ด', icon: '📊', minRole: 'admin' },
  { path: '/employees', label: 'พนักงาน', icon: '👥', minRole: 'admin' },
  { path: '/reports', label: 'รายงาน', icon: '📋', minRole: 'admin' },
  { section: 'จัดการเข้างาน', minRole: 'admin' },
  { path: '/leave', label: 'ลางาน', icon: '📅', minRole: 'admin' },
  { path: '/ot', label: 'OT', icon: '⏰', minRole: 'admin' },
  { path: '/wfh', label: 'ปฏิบัติงานนอกสถานที่', icon: '🏠', minRole: 'admin' },
  { path: '/holidays', label: 'วันหยุด', icon: '🎌', minRole: 'admin' },
  { path: '/attendance-adjustment', label: 'ปรับแก้สถานะเข้างาน', icon: '✏️', minRole: 'admin' },
  { path: '/attendance-verification', label: 'ยืนยันสถานะเข้างาน', icon: '✅', minRole: 'admin' },
  { path: '/manual-entry', label: 'บันทึกข้อมูลด้วยมือ', icon: '📝', minRole: 'admin' },
  { section: 'จัดกะ & OT', minRole: 'admin' },
  { path: '/shifts', label: 'กะทำงาน', icon: '🔄', minRole: 'admin' },
  { path: '/shift-assignments', label: 'มอบหมายกะรายเดือน', icon: '📅', minRole: 'admin' },
  { path: '/mandatory-ot', label: 'มอบหมาย OT บังคับ', icon: '⏰', minRole: 'admin' },
  { path: '/auto-ot', label: 'คำนวณ OT อัตโนมัติ', icon: '🤖', minRole: 'admin' },
  { path: '/ot-summary', label: 'สรุป OT', icon: '📊', minRole: 'admin' },
  { section: 'อนุมัติ', minRole: 'admin' },
  { path: '/leave-approval', label: 'อนุมัติลางาน', icon: '✅', minRole: 'admin' },
  { path: '/wfh-approval', label: 'อนุมัติ WFH', icon: '✅', minRole: 'admin' },
  { path: '/shift-swap-approval', label: 'อนุมัติสลับเวร', icon: '✅', minRole: 'admin' },
  { section: 'เงินเดือน', minRole: 'admin' },
  { path: '/payslip-entry', label: 'กรอกสลิปเงินเดือน', icon: '💰', minRole: 'admin' },
  { section: 'ดูแลทีม', minRole: 'admin' },
  { path: '/supervisor/leave-approval', label: 'อนุมัติลางาน (หัวหน้า)', icon: '👤', minRole: 'admin' },
  { path: '/supervisor/ot-approval', label: 'อนุมัติ OT (หัวหน้า)', icon: '👤', minRole: 'admin' },
  { path: '/supervisor/team-calendar', label: 'ปฏิทินทีม', icon: '📆', minRole: 'admin' },
  { path: '/manager/leave-approval', label: 'อนุมัติลางาน (ผู้จัดการ)', icon: '👤', minRole: 'admin' },
  { path: '/manager/ot-approval', label: 'อนุมัติ OT (ผู้จัดการ)', icon: '👤', minRole: 'admin' },
  { path: '/manager/team-report', label: 'รายงานทีม', icon: '📊', minRole: 'admin' },
  { section: 'Remote', minRole: 'admin' },
  { path: '/remote-assignments', label: 'ปฏิบัติงานต่างจังหวัด', icon: '📍', minRole: 'admin' },
  { path: '/location-history', label: 'แผนที่', icon: '🗺', minRole: 'admin' },
  { section: 'ตั้งค่า' },
  { path: '/audit-log', label: 'ประวัติการแก้ไขข้อมูล', icon: '📜', minRole: 'admin' },
  { path: '/permission', label: 'จัดการสิทธิ์', icon: '🔑', minRole: 'super_admin' },
  { path: '/settings', label: 'ตั้งค่าพนักงาน', icon: '⚙', minRole: 'super_admin' },
  { path: '/admin/company-settings', label: 'ตั้งค่าบริษัท', icon: '🏢', minRole: 'super_admin' },
  { path: '/admin/system-settings', label: 'ตั้งค่าระบบ', icon: '🔧', minRole: 'super_admin' },
  { path: '/admin/location-settings', label: 'จุดเช็คอิน/เช็คเอาท์', icon: '📍', minRole: 'super_admin' },
  { path: '/photos', label: 'สถานะลงทะเบียนใบหน้า', icon: '🧑', minRole: 'super_admin' },
  { path: '/photo-import', label: 'นำเข้ารูปใบหน้า', icon: '📸', minRole: 'super_admin' },
  { path: '/telegram-settings', label: 'Telegram', icon: '✈', minRole: 'super_admin' },
]

const roleHierarchy = { employee: 0, admin: 1, super_admin: 2 }

const filteredNavItems = computed(() => {
  return navItems.filter(item => {
    if (!item.minRole) return true
    return (roleHierarchy[userRole.value] || 0) >= (roleHierarchy[item.minRole] || 0)
  })
})

function isActive(path) {
  return route.path === path || route.path.startsWith(path + '/')
}

function handleLogout() {
  logout()
  router.push('/login')
}

function goHome() {
  if (userRole.value === 'employee') {
    router.push('/employee/menu')
  } else {
    router.push('/dashboard')
  }
}
</script>
