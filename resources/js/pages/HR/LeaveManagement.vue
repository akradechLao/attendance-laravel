<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-navy">จัดการลางาน</h1>
        <p class="text-gray-500">รายการขอลาพักร้อนและอนุมัติ</p>
      </div>

      <!-- Tabs -->
      <div class="flex gap-4 border-b">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-4 py-2 font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-blue-500 text-blue-500'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ tab.label }}
          <span
            v-if="tab.count > 0"
            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-600"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Pending Requests -->
        <div v-if="activeTab === 'pending'" class="space-y-4">
          <div v-if="pendingLeaves.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการขอลาที่รออนุมัติ
          </div>

          <div
            v-for="leave in pendingLeaves"
            :key="leave.id"
            class="card animate-fadeIn"
          >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-blue-600 font-semibold">{{ leave.employee?.name?.charAt(0) }}</span>
                </div>
                <div>
                  <p class="font-semibold text-navy">{{ leave.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ leave.employee?.code }} | {{ leave.employee?.company?.name }}</p>
                </div>
              </div>
              <div class="text-right">
                <span :class="leaveTypeClass(leave.type)" class="px-3 py-1 rounded-full text-sm font-medium">
                  {{ leaveTypeName(leave.type) }}
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}
                </p>
                <p class="text-sm text-gray-500">{{ leave.days }} วัน</p>
              </div>
            </div>

            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-600">{{ leave.reason }}</p>
            </div>

            <div class="flex justify-end gap-3 mt-4">
              <button
                @click="rejectLeave(leave)"
                :disabled="processing"
                class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
              >
                ปฏิเสธ
              </button>
              <button
                @click="approveLeave(leave)"
                :disabled="processing"
                class="btn-success"
              >
                อนุมัติ
              </button>
            </div>
          </div>
        </div>

        <!-- Approved -->
        <div v-if="activeTab === 'approved'" class="space-y-4">
          <div v-if="approvedLeaves.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการลาที่อนุมัติแล้ว
          </div>

          <div
            v-for="leave in approvedLeaves"
            :key="leave.id"
            class="card opacity-75"
          >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                  <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div>
                  <p class="font-semibold text-navy">{{ leave.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ leave.employee?.code }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                  อนุมัติแล้ว
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Rejected -->
        <div v-if="activeTab === 'rejected'" class="space-y-4">
          <div v-if="rejectedLeaves.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการลาที่ถูกปฏิเสธ
          </div>

          <div
            v-for="leave in rejectedLeaves"
            :key="leave.id"
            class="card opacity-75"
          >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                  <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </div>
                <div>
                  <p class="font-semibold text-navy">{{ leave.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ leave.employee?.code }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-700">
                  ปฏิเสธ
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-navy mb-4">ปฏิเสธคำขอลา</h3>
        <p class="text-sm text-gray-600 mb-2">พนักงาน: <strong>{{ rejectTarget?.employee?.name }}</strong></p>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผลการปฏิเสธ <span class="text-red-500">*</span></label>
          <textarea v-model="rejectReason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none" placeholder="กรอกเหตุผลการปฏิเสธ..."></textarea>
        </div>
        <div class="flex gap-3 justify-end">
          <button @click="showRejectModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
          <button @click="confirmReject" :disabled="processing" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50">
            {{ processing ? 'กำลังดำเนินการ...' : 'ยืนยันปฏิเสธ' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(true)
const processing = ref(false)
const leaves = ref([])
const activeTab = ref('pending')
const showRejectModal = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')

const tabs = computed(() => [
  { key: 'pending', label: 'รออนุมัติ', count: pendingLeaves.value.length },
  { key: 'approved', label: 'อนุมัติแล้ว', count: 0 },
  { key: 'rejected', label: 'ปฏิเสธ', count: 0 }
])

const pendingLeaves = computed(() => leaves.value.filter(l => l.status === 'pending'))
const approvedLeaves = computed(() => leaves.value.filter(l => l.status === 'approved'))
const rejectedLeaves = computed(() => leaves.value.filter(l => l.status === 'rejected'))

function leaveTypeName(type) {
  const names = {
    sick: 'ลาป่วย',
    personal: 'ลากิจ',
    vacation: 'พักร้อน',
    other: 'อื่นๆ'
  }
  return names[type] || type
}

function leaveTypeClass(type) {
  const classes = {
    sick: 'bg-red-100 text-red-700',
    personal: 'bg-yellow-100 text-yellow-700',
    vacation: 'bg-blue-100 text-blue-700',
    other: 'bg-gray-100 text-gray-700'
  }
  return classes[type] || 'bg-gray-100 text-gray-700'
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('th-TH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

async function fetchLeaves() {
  loading.value = true
  try {
    const response = await api.get('/api/leave')
    leaves.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching leaves:', error)
  } finally {
    loading.value = false
  }
}

async function approveLeave(leave) {
  if (!confirm('ยืนยันการอนุมัติลา?')) return
  processing.value = true
  try {
    await api.put(`/api/leave/${leave.id}/approve`)
    fetchLeaves()
  } catch (error) {
    console.error('Error approving leave:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    processing.value = false
  }
}

async function rejectLeave(leave) {
  rejectTarget.value = leave
  rejectReason.value = ''
  showRejectModal.value = true
}

async function confirmReject() {
  if (!rejectReason.value.trim()) {
    alert('กรุณากรอกเหตุผลการปฏิเสธ')
    return
  }
  processing.value = true
  try {
    await api.put(`/api/leave/${rejectTarget.value.id}/reject`, {
      rejection_reason: rejectReason.value
    })
    showRejectModal.value = false
    fetchLeaves()
  } catch (error) {
    console.error('Error rejecting leave:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    processing.value = false
  }
}

onMounted(fetchLeaves)
</script>
