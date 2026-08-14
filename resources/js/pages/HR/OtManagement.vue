<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-navy">จัดการ OT</h1>
        <p class="text-gray-500">รายการขอทำ OT และอนุมัติ</p>
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
        <!-- Pending Manager Approval -->
        <div v-if="activeTab === 'pending_manager'" class="space-y-4">
          <div v-if="pendingManagerOts.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการ OT ที่รออนุมัติจากผู้จัดการ
          </div>

          <div
            v-for="ot in pendingManagerOts"
            :key="ot.id"
            class="card animate-fadeIn"
          >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-blue-600 font-semibold">{{ ot.employee?.name?.charAt(0) }}</span>
                </div>
                <div>
                  <p class="font-semibold text-navy">{{ ot.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ ot.employee?.code }} | {{ ot.employee?.company?.name }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">
                  รออนุมัติผู้จัดการ
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(ot.date) }} | {{ ot.start_time }} - {{ ot.end_time }}
                </p>
                <p class="text-sm text-gray-500">{{ ot.hours }} ชั่วโมง</p>
              </div>
            </div>

            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-600">{{ ot.reason }}</p>
            </div>

            <div class="flex justify-end gap-3 mt-4">
              <button
                @click="rejectOt(ot)"
                :disabled="processing"
                class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
              >
                ปฏิเสธ
              </button>
              <button
                @click="managerApprove(ot)"
                :disabled="processing"
                class="btn-success"
              >
                อนุมัติ (ผู้จัดการ)
              </button>
            </div>
          </div>
        </div>

        <!-- Pending HR Approval -->
        <div v-if="activeTab === 'pending_hr'" class="space-y-4">
          <div v-if="pendingHrOts.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการ OT ที่รออนุมัติจาก HR
          </div>

          <div
            v-for="ot in pendingHrOts"
            :key="ot.id"
            class="card animate-fadeIn"
          >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                  <span class="text-blue-600 font-semibold">{{ ot.employee?.name?.charAt(0) }}</span>
                </div>
                <div>
                  <p class="font-semibold text-navy">{{ ot.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ ot.employee?.code }} | {{ ot.employee?.company?.name }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                  รออนุมัติ HR
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(ot.date) }} | {{ ot.start_time }} - {{ ot.end_time }}
                </p>
                <p class="text-sm text-gray-500">{{ ot.hours }} ชั่วโมง</p>
              </div>
            </div>

            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-600">{{ ot.reason }}</p>
            </div>

            <div class="flex justify-end gap-3 mt-4">
              <button
                @click="rejectOt(ot)"
                :disabled="processing"
                class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
              >
                ปฏิเสธ
              </button>
              <button
                @click="hrApprove(ot)"
                :disabled="processing"
                class="btn-success"
              >
                อนุมัติ (HR)
              </button>
            </div>
          </div>
        </div>

        <!-- Approved -->
        <div v-if="activeTab === 'approved'" class="space-y-4">
          <div v-if="approvedOts.length === 0" class="card text-center py-8 text-gray-500">
            ไม่มีรายการ OT ที่อนุมัติแล้ว
          </div>

          <div
            v-for="ot in approvedOts"
            :key="ot.id"
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
                  <p class="font-semibold text-navy">{{ ot.employee?.name }}</p>
                  <p class="text-sm text-gray-500">{{ ot.employee?.code }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                  อนุมัติแล้ว
                </span>
                <p class="text-sm text-gray-500 mt-1">
                  {{ formatDate(ot.date) }} | {{ ot.hours }} ชั่วโมง
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
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
const ots = ref([])
const activeTab = ref('pending_manager')

const tabs = computed(() => [
  { key: 'pending_manager', label: 'รออนุมัติผู้จัดการ', count: pendingManagerOts.value.length },
  { key: 'pending_hr', label: 'รออนุมัติ HR', count: pendingHrOts.value.length },
  { key: 'approved', label: 'อนุมัติแล้ว', count: 0 }
])

const pendingManagerOts = computed(() => ots.value.filter(o => o.status === 'pending_manager'))
const pendingHrOts = computed(() => ots.value.filter(o => o.status === 'pending_hr'))
const approvedOts = computed(() => ots.value.filter(o => o.status === 'approved'))

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('th-TH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

async function fetchOts() {
  loading.value = true
  try {
    const response = await api.get('/api/ots')
    ots.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error fetching OTs:', error)
  } finally {
    loading.value = false
  }
}

async function managerApprove(ot) {
  if (!confirm('ยืนยันการอนุมัติ OT?')) return
  processing.value = true
  try {
    await api.put(`/api/ots/${ot.id}/manager-approve`)
    fetchOts()
  } catch (error) {
    console.error('Error approving OT:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    processing.value = false
  }
}

async function hrApprove(ot) {
  if (!confirm('ยืนยันการอนุมัติ OT?')) return
  processing.value = true
  try {
    await api.put(`/api/ots/${ot.id}/hr-approve`)
    fetchOts()
  } catch (error) {
    console.error('Error approving OT:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    processing.value = false
  }
}

async function rejectOt(ot) {
  if (!confirm('ยืนยันการปฏิเสธ OT?')) return
  processing.value = true
  try {
    await api.put(`/api/ots/${ot.id}/reject`)
    fetchOts()
  } catch (error) {
    console.error('Error rejecting OT:', error)
    alert('เกิดข้อผิดพลาด')
  } finally {
    processing.value = false
  }
}

onMounted(fetchOts)
</script>
