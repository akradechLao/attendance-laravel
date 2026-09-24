<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">เตือน & แจ้งเตือน</h1>
        <span
          v-if="unreadCount > 0 && tab !== 'notifications'"
          class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full"
        >
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 pb-28 sm:pb-6">
      <!-- Tab Bar -->
      <div class="flex bg-white rounded-xl p-1 border border-gray-200 shadow-sm mb-4">
        <button
          @click="setTab('history')"
          :class="tab === 'history' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
          class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
        >ประวัติ</button>
        <button
          @click="setTab('warnings')"
          :class="tab === 'warnings' ? 'bg-red-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
          class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
        >
          คำเตือน
          <span
            v-if="warnings.length"
            class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[10px] bg-white text-red-500 rounded-full font-bold"
          >{{ warnings.length }}</span>
        </button>
        <button
          @click="setTab('notifications')"
          :class="tab === 'notifications' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
          class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
        >
          แจ้งเตือน
          <span
            v-if="unreadCount > 0"
            class="ml-1 inline-flex items-center justify-center min-w-[18px] h-5 px-1 text-[10px] bg-white text-blue-600 rounded-full font-bold"
          >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </button>
      </div>

      <!-- History Tab -->
      <div v-if="tab === 'history'">
        <div class="flex bg-white rounded-xl p-1 border border-gray-200 shadow-sm mb-4">
          <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
            class="flex-1 py-1.5 rounded-lg text-xs font-medium transition-all">รายการ</button>
          <button @click="viewMode = 'calendar'" :class="viewMode === 'calendar' ? 'bg-blue-500 text-white shadow' : 'text-gray-600 hover:bg-gray-50'"
            class="flex-1 py-1.5 rounded-lg text-xs font-medium transition-all">ปฏิทิน</button>
        </div>

        <template v-if="viewMode === 'list'">
          <div v-if="loadingHistory" class="text-center py-12">
            <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>

          <div v-else-if="history.length === 0" class="text-center py-12">
            <p class="text-gray-400 text-sm">ไม่มีข้อมูลเข้างาน</p>
          </div>

          <div v-else class="space-y-2">
            <div v-for="record in history" :key="record.id" class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-800 text-sm">{{ formatDate(record.date) }}</p>
                  <p class="text-gray-400 text-xs mt-0.5">
                    {{ record.check_in ? record.check_in.substring(11, 16) : '-' }}
                    {{ record.check_out ? ' → ' + record.check_out.substring(11, 16) : '' }}
                  </p>
                </div>
                <span :class="record.status === 'on-time' ? 'bg-emerald-50 text-emerald-600' : record.status === 'late' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'"
                  class="text-[10px] font-bold px-2 py-1 rounded-full">
                  {{ statusLabel(record.status) }}
                </span>
              </div>
              <p v-if="record.note" class="text-gray-400 text-xs mt-2 italic">{{ record.note }}</p>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
            <MonthCalendar :year="calYear" :month="calMonth" @prev="calPrevMonth" @next="calNextMonth">
              <template #cell="{ cell }">
                <button
                  v-if="cell"
                  @click="selectCalDay(cell)"
                  class="w-full h-full rounded-lg flex flex-col items-center justify-center text-[11px] transition-colors"
                  :class="[calCellClass(cell.date), cell.isToday ? 'ring-2 ring-blue-400' : '', calCellInfo(cell.date).hasData ? 'cursor-pointer' : 'cursor-default']"
                >
                  <span>{{ cell.day }}</span>
                  <span v-if="calCellInfo(cell.date).hasData" class="w-1 h-1 rounded-full mt-0.5" :class="calCellInfo(cell.date).dotClass"></span>
                </button>
              </template>
            </MonthCalendar>

            <div v-if="calLoading" class="text-center py-6 text-gray-400 text-xs">กำลังโหลด...</div>

            <div class="flex flex-wrap gap-3 mt-4 pt-3 border-t border-gray-100 text-[11px] text-gray-500">
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>ปกติ</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>สาย</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>ลา</div>
              <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-200"></span>ไม่มีข้อมูล</div>
            </div>

            <div v-if="selectedCalDay" class="mt-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
              <p class="font-medium text-gray-800 text-sm mb-1">{{ formatDate(selectedCalDay.date) }}</p>
              <template v-if="selectedCalDay.attendance">
                <p class="text-gray-500 text-xs">
                  เข้า {{ selectedCalDay.attendance.check_in || '-' }}
                  {{ selectedCalDay.attendance.check_out ? ' → ออก ' + selectedCalDay.attendance.check_out : '' }}
                </p>
                <p class="text-gray-500 text-xs mt-0.5">
                  สถานะ: {{ selectedCalDay.attendance.status === 'late' ? `สาย ${selectedCalDay.attendance.late_minutes} นาที` : 'ปกติ' }}
                  <span v-if="selectedCalDay.attendance.worked_hours">• ทำงาน {{ selectedCalDay.attendance.worked_hours }} ชม.</span>
                </p>
              </template>
              <template v-else-if="selectedCalDay.leave">
                <p class="text-gray-500 text-xs">ลา: {{ selectedCalDay.leave.leave_type }} ({{ selectedCalDay.leave.total_days }} วัน)</p>
                <p class="text-gray-500 text-xs mt-0.5">
                  {{ selectedCalDay.leave.start_date }} - {{ selectedCalDay.leave.end_date }} •
                  {{ selectedCalDay.leave.status === 'approved' ? 'อนุมัติแล้ว' : selectedCalDay.leave.status === 'pending' ? 'รออนุมัติ' : 'ปฏิเสธ' }}
                </p>
                <p v-if="selectedCalDay.leave.reason" class="text-gray-400 text-xs mt-0.5 italic">{{ selectedCalDay.leave.reason }}</p>
              </template>
            </div>
          </div>
        </template>
      </div>

      <!-- Warnings Tab -->
      <div v-if="tab === 'warnings'">
        <div v-if="loadingWarnings" class="text-center py-12">
          <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>

        <div v-else-if="warnings.length === 0" class="text-center py-12">
          <p class="text-gray-400 text-sm">ไม่มีคำเตือน</p>
        </div>

        <div v-else class="space-y-3">
          <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-amber-500">{{ summary.late || 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">สายเดือนนี้</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-red-500">{{ summary.absent || 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">ขาดเดือนนี้</p>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">
              <span class="text-2xl font-bold text-purple-500">{{ summary.early || 0 }}</span>
              <p class="text-gray-400 text-[10px] mt-1 font-medium">กลับก่อนเดือนนี้</p>
            </div>
          </div>

          <div v-for="warn in warnings" :key="warn.id"
            :class="warn.severity === 'high' ? 'border-red-300 bg-red-50' : warn.severity === 'medium' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white'"
            class="rounded-xl p-4 border shadow-sm">
            <div class="flex items-start gap-3">
              <div :class="warn.severity === 'high' ? 'bg-red-100 text-red-600' : warn.severity === 'medium' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-600'"
                class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div class="flex-1">
                <p class="font-medium text-gray-800 text-sm">{{ warn.message }}</p>
                <p class="text-gray-400 text-xs mt-0.5">{{ formatDate(warn.created_at) }}</p>
                <span v-if="warn.severity === 'high'" class="inline-block mt-1 text-[10px] font-bold bg-red-500 text-white px-2 py-0.5 rounded-full">ระดับสูง</span>
                <span v-else-if="warn.severity === 'medium'" class="inline-block mt-1 text-[10px] font-bold bg-amber-500 text-white px-2 py-0.5 rounded-full">ระดับกลาง</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notifications Tab -->
      <div v-if="tab === 'notifications'">
        <div class="flex justify-end mb-3" v-if="unreadCount > 0">
          <button
            @click="markAllAsRead"
            class="text-blue-600 text-sm font-medium hover:text-blue-800 transition-colors"
          >อ่านทั้งหมด</button>
        </div>

        <div v-if="loadingNotifications" class="text-center py-12">
          <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-gray-500 text-sm">กำลังโหลด...</p>
        </div>

        <div v-else-if="notifications.length === 0" class="text-center py-16">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
          <p class="text-gray-500 font-medium">ไม่มีการแจ้งเตือน</p>
          <p class="text-gray-400 text-sm mt-1">เมื่อมีรายการใหม่จะปรากฏที่นี่</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="n in notifications"
            :key="n.id"
            @click="handleNotificationClick(n)"
            :class="[
              'rounded-xl border p-4 cursor-pointer transition-all duration-200',
              n.is_read
                ? 'bg-white border-gray-200 hover:border-gray-300'
                : 'bg-blue-50 border-blue-200 hover:border-blue-300 shadow-sm'
            ]"
          >
            <div class="flex items-start gap-3">
              <div
                :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                  getNotificationTypeStyle(n.type).bg
                ]"
              >
                <svg class="w-5 h-5" :class="getNotificationTypeStyle(n.type).text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getNotificationTypeStyle(n.type).icon" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <h3 :class="['text-sm font-semibold', n.is_read ? 'text-gray-700' : 'text-gray-900']">{{ n.title }}</h3>
                  <span v-if="!n.is_read" class="w-2 h-2 bg-blue-500 rounded-full shrink-0"></span>
                </div>
                <p :class="['text-sm', n.is_read ? 'text-gray-500' : 'text-gray-700']">{{ n.message }}</p>
                <p class="text-xs text-gray-400 mt-2">{{ formatNotificationTime(n.created_at) }}</p>
              </div>
              <div :class="['px-2 py-1 rounded-lg text-xs font-medium shrink-0', getNotificationBadgeStyle(n.type)]">
                {{ getNotificationBadgeText(n.type) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MonthCalendar from '../../components/MonthCalendar.vue'

const route = useRoute()
const router = useRouter()

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const tabFromQuery = () => {
  const t = route.query.tab
  if (t === 'warnings' || t === 'notifications' || t === 'history') return t
  return 'history'
}

const tab = ref(tabFromQuery())
const loadingHistory = ref(true)
const loadingWarnings = ref(true)
const loadingNotifications = ref(true)
const history = ref([])
const warnings = ref([])
const summary = ref({ late: 0, absent: 0, early: 0 })

// ─── Notifications ───
const APPROVAL_NEEDED_TYPES = ['leave_request', 'ot_request', 'wfh_request']
const notifications = ref([])
const unreadCount = ref(0)

const notificationTypeStyles = {
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

const notificationBadgeStyles = {
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

const notificationBadgeTexts = {
  leave_request: 'ลา', leave_approved: 'ลา', leave_rejected: 'ลา',
  ot_request: 'โอที', ot_approved: 'โอที', ot_rejected: 'โอที',
  wfh_request: 'WFH', wfh_approved: 'WFH', wfh_rejected: 'WFH',
}

function getNotificationTypeStyle(type) {
  return notificationTypeStyles[type] || { bg: 'bg-gray-100', text: 'text-gray-600', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }
}
function getNotificationBadgeStyle(type) {
  return notificationBadgeStyles[type] || 'bg-gray-100 text-gray-700'
}
function getNotificationBadgeText(type) {
  return notificationBadgeTexts[type] || 'ทั่วไป'
}

function formatNotificationTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMins = Math.floor((now - date) / 60000)
  const diffHours = Math.floor((now - date) / 3600000)
  const diffDays = Math.floor((now - date) / 86400000)
  if (diffMins < 1) return 'เมื่อสักครู่'
  if (diffMins < 60) return diffMins + ' นาทีที่แล้ว'
  if (diffHours < 24) return diffHours + ' ชั่วโมงที่แล้ว'
  if (diffDays < 7) return diffDays + ' วันที่แล้ว'
  return `${date.getDate()} ${['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][date.getMonth()]} ${date.getFullYear() + 543}`
}

function setTab(next) {
  tab.value = next
  router.replace({ query: { ...route.query, tab: next } })
  if (next === 'notifications' && loadingNotifications.value) {
    loadNotifications()
  }
}

async function loadNotifications() {
  loadingNotifications.value = true
  try {
    const res = await api.get('/api/employee/notifications')
    if (res.data.success) {
      notifications.value = res.data.data
      unreadCount.value = res.data.unread_count
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e)
  } finally {
    loadingNotifications.value = false
  }
}

async function markNotificationAsRead(n) {
  if (!n.is_read) {
    try {
      await api.put(`/api/employee/notifications/${n.id}/read`)
      n.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) {
      console.error('Failed to mark as read', e)
    }
  }
}

function handleNotificationClick(n) {
  markNotificationAsRead(n)
  if (APPROVAL_NEEDED_TYPES.includes(n.type)) {
    router.push('/pending-approvals')
  }
}

async function markAllAsRead() {
  try {
    await api.put('/api/employee/notifications/read-all')
    notifications.value.forEach(n => n.is_read = true)
    unreadCount.value = 0
  } catch (e) {
    console.error('Failed to mark all as read', e)
  }
}

function statusLabel(s) {
  return s === 'on-time' ? 'ปกติ' : s === 'late' ? 'สาย' : s === 'early' ? 'กลับก่อน' : s === 'absent' ? 'ขาด' : s
}

// ─── Calendar view (History tab) ───
const viewMode = ref('list')
const today = new Date()
const calYear = ref(today.getFullYear())
const calMonth = ref(today.getMonth() + 1)
const calLoading = ref(false)
const calAttendance = ref([])
const calLeave = ref([])
const selectedCalDay = ref(null)

function calCellInfo(dateStr) {
  const att = calAttendance.value.find(a => a.date === dateStr) || null
  const leave = calLeave.value.find(l => dateStr >= l.start_date && dateStr <= l.end_date) || null
  const dotClass = att
    ? (att.status === 'late' ? 'bg-amber-400' : 'bg-emerald-400')
    : leave
      ? 'bg-blue-400'
      : ''
  return { attendance: att, leave, hasData: !!(att || leave), dotClass }
}

function calCellClass(dateStr) {
  const info = calCellInfo(dateStr)
  if (info.attendance) {
    return info.attendance.status === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'
  }
  if (info.leave) {
    return info.leave.status === 'approved' ? 'bg-blue-50 text-blue-700' : 'bg-blue-50/50 text-blue-400'
  }
  return 'text-gray-400 hover:bg-gray-50'
}

function selectCalDay(cell) {
  const info = calCellInfo(cell.date)
  if (!info.hasData) return
  selectedCalDay.value = { date: cell.date, attendance: info.attendance, leave: info.leave }
}

async function loadCalendarMonth() {
  calLoading.value = true
  selectedCalDay.value = null
  try {
    const res = await api.get('/api/employee/attendance/history', { params: { month: calMonth.value, year: calYear.value } })
    if (res.data.success) {
      calAttendance.value = res.data.data.attendance || []
      calLeave.value = res.data.data.leave || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    calLoading.value = false
  }
}

function calPrevMonth() {
  calMonth.value--
  if (calMonth.value < 1) { calMonth.value = 12; calYear.value-- }
  loadCalendarMonth()
}

function calNextMonth() {
  calMonth.value++
  if (calMonth.value > 12) { calMonth.value = 1; calYear.value++ }
  loadCalendarMonth()
}

let calLoaded = false
watch(viewMode, (mode) => {
  if (mode === 'calendar' && !calLoaded) {
    calLoaded = true
    loadCalendarMonth()
  }
})

function formatDate(d) {
  const dt = new Date(d)
  return `${dt.getDate()} ${thMonths[dt.getMonth()]} ${dt.getFullYear() + 543}`
}

onMounted(async () => {
  // Load unread badge even if default tab is history
  try {
    const countRes = await api.get('/api/employee/notifications/unread-count')
    if (countRes.data.success) {
      unreadCount.value = countRes.data.data.count
    }
  } catch { /* silent */ }

  if (tab.value === 'notifications') {
    loadNotifications()
  } else {
    loadingNotifications.value = false
  }

  try {
    const [histRes, warnRes] = await Promise.allSettled([
      api.get('/api/employee/attendance/history', { params: { limit: 30 } }),
      api.get('/api/employee/warnings'),
    ])
    if (histRes.status === 'fulfilled' && histRes.value.data.success) {
      history.value = histRes.value.data.data
    }
    if (warnRes.status === 'fulfilled' && warnRes.value.data.success) {
      warnings.value = warnRes.value.data.data.warnings || []
      summary.value = warnRes.value.data.data.summary || { late: 0, absent: 0, early: 0 }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingHistory.value = false
    loadingWarnings.value = false
  }
})
</script>
