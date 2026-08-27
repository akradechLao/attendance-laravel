<template>
  <AppLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-800">อนุมัติร้องขอเข้ากะ</h1>

      <div v-if="loading" class="text-center py-8">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else-if="requests.length === 0" class="text-center py-12 text-gray-400">ไม่มีคำขอรออนุมัติ</div>

      <div v-else class="space-y-4">
        <div v-for="r in requests" :key="r.id" class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
          <div class="flex justify-between items-start mb-3">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span :class="typeBadgeClass(r.request_type)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ typeLabel(r.request_type) }}</span>
                <span class="text-sm font-bold text-gray-800">{{ r.employee?.name }}</span>
                <span class="text-xs text-gray-400">{{ r.employee?.employee_code }}</span>
              </div>
              <p class="text-sm text-gray-600">กะกลุ่ม {{ r.work_shift?.group_number }} ({{ r.work_shift?.start_time }}-{{ r.work_shift?.end_time }})</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mb-3">
            <div>วันที่: {{ r.start_date }}{{ r.end_date !== r.start_date ? ' - ' + r.end_date : '' }}</div>
            <div v-if="r.request_type === 'modify'">แก้เวลา: {{ r.new_start_time }} - {{ r.new_end_time }}</div>
            <div v-if="r.reason" class="col-span-2">เหตุผล: {{ r.reason }}</div>
          </div>

          <div class="flex gap-2">
            <button @click="approve(r.id)" class="flex-1 py-2 bg-emerald-500 text-white rounded-xl text-sm font-medium hover:bg-emerald-600 transition-colors">อนุมัติ</button>
            <button @click="showRejectModal(r)" class="flex-1 py-2 bg-red-500 text-white rounded-xl text-sm font-medium hover:bg-red-600 transition-colors">ปฏิเสธ</button>
          </div>
        </div>
      </div>

      <!-- Reject Modal -->
      <div v-if="rejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="rejectModal = null">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-800 mb-4">ปฏิเสธคำขอ</h3>
          <textarea v-model="rejectNote" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none text-sm resize-none" placeholder="กรุณาระบุเหตุผล"></textarea>
          <div class="flex gap-2 mt-4">
            <button @click="rejectModal = null" class="flex-1 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium">ยกเลิก</button>
            <button @click="reject(rejectModal)" :disabled="!rejectNote" class="flex-1 py-2 bg-red-500 text-white rounded-xl text-sm font-medium disabled:opacity-50">ยืนยัน</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import api from '@/services/api'

const loading = ref(true)
const requests = ref([])
const rejectModal = ref(null)
const rejectNote = ref('')

onMounted(() => loadRequests())

async function loadRequests() {
  loading.value = true
  try {
    const res = await api.get('/api/shift-requests/team-requests')
    if (res.data.success) requests.value = res.data.data.filter(r => r.status === 'pending')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function approve(id) {
  try {
    await api.put(`/api/shift-requests/${id}/approve`)
    alert('อนุมัติแล้ว')
    loadRequests()
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

function showRejectModal(r) {
  rejectModal.value = r.id
  rejectNote.value = ''
}

async function reject(id) {
  try {
    await api.put(`/api/shift-requests/${id}/reject`, { supervisor_note: rejectNote.value })
    alert('ปฏิเสธแล้ว')
    rejectModal.value = null
    loadRequests()
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

function typeLabel(t) {
  return { assign: 'ขอกะใหม่', modify: 'แก้เวลา', remove: 'ขอลบกะ' }[t] || t
}
function typeBadgeClass(t) {
  return { assign: 'bg-blue-100 text-blue-700', modify: 'bg-amber-100 text-amber-700', remove: 'bg-red-100 text-red-700' }[t] || 'bg-gray-100 text-gray-700'
}
</script>
