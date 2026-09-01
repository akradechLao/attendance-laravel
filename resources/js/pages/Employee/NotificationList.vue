<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button @click="$router.back()" class="p-2 hover:bg-gray-100 rounded-xl transition-colors">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 class="text-lg font-bold text-gray-800">การแจ้งเตือน</h1>
          <span v-if="unreadCount > 0" class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">{{ unreadCount }}</span>
        </div>
        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          class="text-blue-600 text-sm font-medium hover:text-blue-800 transition-colors"
        >
          อ่านทั้งหมด
        </button>
      </div>
    </header>

    <!-- Content -->
    <main class="max-w-4xl mx-auto px-4 py-6">
      <!-- Loading -->
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-3 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-gray-500 text-sm">กำลังโหลด...</p>
      </div>

      <!-- Empty -->
      <div v-else-if="notifications.length === 0" class="text-center py-16">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <p class="text-gray-500 font-medium">ไม่มีการแจ้งเตือน</p>
        <p class="text-gray-400 text-sm mt-1">เมื่อมีรายการใหม่จะปรากฏที่นี่</p>
      </div>

      <!-- Notification List -->
      <div v-else class="space-y-2">
        <div
          v-for="n in notifications"
          :key="n.id"
          @click="markAsRead(n)"
          :class="[
            'rounded-xl border p-4 cursor-pointer transition-all duration-200',
            n.is_read
              ? 'bg-white border-gray-200 hover:border-gray-300'
              : 'bg-blue-50 border-blue-200 hover:border-blue-300 shadow-sm'
          ]"
        >
          <div class="flex items-start gap-3">
            <!-- Icon -->
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                getTypeStyle(n.type).bg
              ]"
            >
              <svg class="w-5 h-5" :class="getTypeStyle(n.type).text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTypeStyle(n.type).icon" />
              </svg>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <h3 :class="['text-sm font-semibold', n.is_read ? 'text-gray-700' : 'text-gray-900']">{{ n.title }}</h3>
                <span v-if="!n.is_read" class="w-2 h-2 bg-blue-500 rounded-full shrink-0"></span>
              </div>
              <p :class="['text-sm', n.is_read ? 'text-gray-500' : 'text-gray-700']">{{ n.message }}</p>
              <p class="text-xs text-gray-400 mt-2">{{ formatTime(n.created_at) }}</p>
            </div>

            <!-- Status Badge -->
            <div :class="['px-2 py-1 rounded-lg text-xs font-medium shrink-0', getBadgeStyle(n.type)]">
              {{ getBadgeText(n.type) }}
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'

const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(true)

const typeStyles = {
  leave_request: { bg: 'bg-blue-100', text: 'text-blue-600', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  leave_approved: { bg: 'bg-green-100', text: 'text-green-600', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  leave_rejected: { bg: 'bg-red-100', text: 'text-red-600', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
  ot_request: { bg: 'bg-amber-100', text: 'text-amber-600', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  ot_approved: { bg: 'bg-green-100', text: 'text-green-600', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  ot_rejected: { bg: 'bg-red-100', text: 'text-red-600', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
  wfh_request: { bg: 'bg-emerald-100', text: 'text-emerald-600', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  wfh_approved: { bg: 'bg-green-100', text: 'text-green-600', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  wfh_rejected: { bg: 'bg-red-100', text: 'text-red-600', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
}

const badgeStyles = {
  leave_request: 'bg-blue-100 text-blue-700',
  leave_approved: 'bg-green-100 text-green-700',
  leave_rejected: 'bg-red-100 text-red-700',
  ot_request: 'bg-amber-100 text-amber-700',
  ot_approved: 'bg-green-100 text-green-700',
  ot_rejected: 'bg-red-100 text-red-700',
  wfh_request: 'bg-emerald-100 text-emerald-700',
  wfh_approved: 'bg-green-100 text-green-700',
  wfh_rejected: 'bg-red-100 text-red-700',
}

const badgeTexts = {
  leave_request: 'ลา',
  leave_approved: 'ลา',
  leave_rejected: 'ลา',
  ot_request: 'โอที',
  ot_approved: 'โอที',
  ot_rejected: 'โอที',
  wfh_request: 'WFH',
  wfh_approved: 'WFH',
  wfh_rejected: 'WFH',
}

function getTypeStyle(type) {
  return typeStyles[type] || { bg: 'bg-gray-100', text: 'text-gray-600', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }
}

function getBadgeStyle(type) {
  return badgeStyles[type] || 'bg-gray-100 text-gray-700'
}

function getBadgeText(type) {
  return badgeTexts[type] || 'ทั่วไป'
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'เมื่อสักครู่'
  if (diffMins < 60) return diffMins + ' นาทีที่แล้ว'
  if (diffHours < 24) return diffHours + ' ชั่วโมงที่แล้ว'
  if (diffDays < 7) return diffDays + ' วันที่แล้ว'

  const day = date.getDate()
  const month = date.getMonth() + 1
  const year = date.getFullYear() + 543
  return `${day} ก.ย. ${year}`
}

async function fetchNotifications() {
  try {
    const res = await api.get('/employee/notifications')
    if (res.data.success) {
      notifications.value = res.data.data
      unreadCount.value = res.data.unread_count
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e)
  } finally {
    loading.value = false
  }
}

async function markAsRead(n) {
  if (!n.is_read) {
    try {
      await api.put(`/employee/notifications/${n.id}/read`)
      n.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) {
      console.error('Failed to mark as read', e)
    }
  }
}

async function markAllAsRead() {
  try {
    await api.put('/employee/notifications/read-all')
    notifications.value.forEach(n => n.is_read = true)
    unreadCount.value = 0
  } catch (e) {
    console.error('Failed to mark all as read', e)
  }
}

onMounted(fetchNotifications)
</script>
