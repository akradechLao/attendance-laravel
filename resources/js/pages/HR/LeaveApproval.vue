<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <h1 class="text-2xl font-bold text-[#0f172a]">อนุมัติลา</h1>

    <!-- Pending -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">รอพิจารณา ({{ pending.length }})</h2>
      <div v-if="pending.length===0" class="text-center py-8 text-gray-500">ไม่มีคำขอรอดำเนินการ</div>
      <div v-else class="space-y-4">
        <div v-for="leave in pending" :key="leave.id" class="border rounded-xl p-4">
          <div class="flex justify-between items-start">
            <div>
              <div class="font-semibold text-[#0f172a]">{{ leave.employee?.name }}</div>
              <div class="text-sm text-gray-500">{{ leave.leave_type?.name }}</div>
              <div class="text-sm text-gray-500">{{ leave.start_date }} - {{ leave.end_date }} ({{ leave.total_days }} วัน)</div>
              <div v-if="leave.reason" class="text-xs text-gray-400 mt-1">เหตุผล: {{ leave.reason }}</div>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <button @click="approve(leave)" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">อนุมัติ</button>
            <button @click="reject(leave)" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">ปฏิเสธ</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Processed -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ดำเนินการแล้ว</h2>
      <div v-if="processed.length===0" class="text-center py-4 text-gray-500">ยังไม่มีรายการ</div>
      <div v-else class="space-y-3">
        <div v-for="leave in processed" :key="leave.id"
             class="p-3 rounded-lg flex justify-between items-center"
             :class="{'bg-green-50': leave.status==='approved', 'bg-red-50': leave.status==='rejected'}">
          <div>
            <div class="font-semibold text-sm">{{ leave.employee?.name }} - {{ leave.leave_type?.name }}</div>
            <div class="text-xs text-gray-500">{{ leave.start_date }} - {{ leave.end_date }} ({{ leave.total_days }} วัน)</div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="{'bg-green-100 text-green-700': leave.status==='approved', 'bg-red-100 text-red-700': leave.status==='rejected'}">
            {{ leave.status==='approved' ? 'อนุมัติ' : 'ปฏิเสธ' }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type==='success' ? 'bg-green-600' : 'bg-red-600'">{{ toast.message }}</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import state from '@/store'
import AppLayout from '@/layouts/AppLayout.vue'

const leaves = ref([])
const toast = ref(null)
const supervisorId = computed(() => state.user?.id)
const pending = computed(() => leaves.value.filter(l => l.status === 'pending'))
const processed = computed(() => leaves.value.filter(l => l.status !== 'pending'))

const loadData = async () => {
  try {
    const res = await api.get('/api/leave/team-leaves', { params: { supervisor_id: supervisorId.value } })
    leaves.value = res.data.data || []
  } catch (err) { console.error(err) }
}

const approve = async (leave) => {
  try {
    await api.put(`/api/leave/${leave.id}/approve`, { supervisor_id: supervisorId.value })
    showToast('success', 'อนุมัติสำเร็จ')
    loadData()
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
}

const reject = async (leave) => {
  try {
    await api.put(`/api/leave/${leave.id}/reject`, { supervisor_id: supervisorId.value })
    showToast('success', 'ปฏิเสธสำเร็จ')
    loadData()
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
}

const showToast = (type, message) => { toast.value = {type, message}; setTimeout(() => toast.value = null, 3000) }

onMounted(loadData)
</script>
