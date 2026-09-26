<template>
  <AppLayout>
    <div class="space-y-6">
    <h1 class="text-2xl font-bold text-navy">อนุมัติ WFH ลูกทีม</h1>

    <!-- Month Selector -->
    <div class="card">
      <label class="block text-sm font-medium text-gray-700 mb-2">เลือกเดือน</label>
      <input type="month" v-model="selectedMonth" @change="loadData" 
             class="w-full border rounded-lg p-2" />
    </div>

    <!-- Pending Requests -->
    <div class="card">
      <h2 class="font-semibold text-navy mb-3">รอพิจารณา ({{ pending.length }})</h2>
      <div v-if="pending.length === 0" class="text-center py-8 text-gray-500">
        ไม่มีคำขอรอดำเนินการ
      </div>
      <div v-else class="space-y-4">
        <div v-for="req in pending" :key="req.id" class="border rounded-xl p-4"
             :class="{ 'border-orange-300 bg-orange-50/40': req.status === 'cancel_requested',
                       'border-purple-300 bg-purple-50/40': req.status === 'change_requested' }">
          <div class="flex justify-between items-start">
            <div>
              <div class="font-semibold text-navy">{{ req.employee?.name || req.employee?.first_name }}</div>
              <div class="text-sm text-gray-500">วันที่: {{ formatDate(req.date) }}</div>

              <!-- คำขอพิเศษ -->
              <div v-if="req.status === 'cancel_requested'" class="mt-1 text-sm">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700 mr-1">ขอยกเลิก</span>
                <span class="text-gray-500">พนักงานขอยกเลิก WFH วันนี้</span>
                <div class="text-xs text-orange-600 mt-1">เหตุผล: {{ req.cancel_reason || '-' }}</div>
              </div>
              <div v-else-if="req.status === 'change_requested'" class="mt-1 text-sm">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700 mr-1">ขอเปลี่ยน</span>
                <span class="text-gray-500">
                  {{ req.requested_date && req.requested_date !== req.date
                     ? formatDate(req.date) + ' → ' + formatDate(req.requested_date)
                     : 'แก้ไขเหตุผล' }}
                </span>
                <div v-if="req.requested_reason" class="text-xs text-purple-600 mt-1">เหตุผลใหม่: {{ req.requested_reason }}</div>
              </div>

              <div v-if="req.status === 'pending'" class="text-sm text-gray-500">เหตุผล: {{ req.reason || '-' }}</div>
            </div>
          </div>

          <!-- Change Date Option (เฉพาะคำขอ pending เท่านั้น) -->
          <div v-if="req.status === 'pending'" class="mt-3 p-3 bg-blue-50 rounded-lg">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
              <input type="checkbox" v-model="req.changeDate" class="rounded" />
              เปลี่ยนวันเสาร์
            </label>
            <div v-if="req.changeDate" class="flex gap-2">
              <select v-model="req.newDate" class="flex-1 border rounded-lg p-2 text-sm">
                <option value="">เลือกวันเสาร์ใหม่</option>
                <option v-for="sat in availableSaturdays" :key="sat.date" :value="sat.date"
                        :disabled="sat.occupied">
                  {{ formatDate(sat.date) }} {{ sat.occupied ? '(ไม่ว่าง)' : '' }}
                </option>
              </select>
            </div>
          </div>

          <!-- Approve/Reject Buttons -->
          <div class="mt-3 flex gap-2">
            <button @click="approve(req)" :disabled="approving"
                    class="flex-1 py-2 rounded-lg font-semibold text-white disabled:opacity-50"
                    :class="req.status === 'cancel_requested' ? 'bg-orange-600 hover:bg-orange-700'
                          : req.status === 'change_requested' ? 'bg-purple-600 hover:bg-purple-700'
                          : 'bg-green-600 hover:bg-green-700'">
              {{ approveLabel(req) }}
            </button>
            <button @click="reject(req)" :disabled="approving"
                    class="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-50">
              {{ rejectLabel(req) }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Approved/Rejected This Month -->
    <div class="card">
      <h2 class="font-semibold text-navy mb-3">ดำเนินการแล้ว</h2>
      <div v-if="processed.length === 0" class="text-center py-4 text-gray-500">
        ยังไม่มีรายการ
      </div>
      <div v-else class="space-y-3">
        <div v-for="req in processed" :key="req.id"
             class="flex items-center justify-between p-3 rounded-lg"
             :class="{
                'bg-green-50': req.status === 'approved',
                'bg-red-50': req.status === 'rejected'
             }">
          <div>
            <div class="font-semibold">{{ req.employee?.name || req.employee?.first_name }}</div>
            <div class="text-xs text-gray-500">
              {{ formatDate(req.date) }} | {{ req.status === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ' }}
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="{
                   'bg-green-100 text-green-700': req.status === 'approved',
                   'bg-red-100 text-red-700': req.status === 'rejected'
                 }">
            {{ req.status === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'">
      {{ toast.message }}
    </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import state from '@/store'
import AppLayout from '@/layouts/AppLayout.vue'

const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const loading = ref(true)
const approving = ref(false)
const requests = ref([])
const availableSaturdays = ref([])
const toast = ref(null)

const user = computed(() => state.user)
const supervisorId = computed(() => user.value?.id)

const ACTIONABLE = ['pending', 'cancel_requested', 'change_requested']
const pending = computed(() => requests.value.filter(r => ACTIONABLE.includes(r.status)))
const processed = computed(() => requests.value.filter(r => !ACTIONABLE.includes(r.status)))

const approveLabel = (req) => ({
  pending: req.changeDate && req.newDate ? 'อนุมัติ (เปลี่ยนวัน)' : 'อนุมัติ',
  cancel_requested: 'อนุมัติการยกเลิก',
  change_requested: 'อนุมัติการเปลี่ยน',
}[req.status] || 'อนุมัติ')

const rejectLabel = (req) => ({
  pending: 'ปฏิเสธ',
  cancel_requested: 'ไม่อนุมัติ (คงไว้)',
  change_requested: 'ไม่อนุมัติ (คงวันเดิม)',
}[req.status] || 'ปฏิเสธ')

const loadData = async () => {
  loading.value = true
  try {
    const [teamRes, satRes] = await Promise.all([
      api.get('/api/wfh/team-requests', { params: { supervisor_id: supervisorId.value, month: selectedMonth.value } }),
      api.get('/api/wfh/available-saturdays', { params: { month: selectedMonth.value } })
    ])
    requests.value = teamRes.data.data.map(r => ({ ...r, changeDate: false, newDate: '' }))
    availableSaturdays.value = satRes.data.data
  } catch (err) {
    console.error(err)
  }
  loading.value = false
}

const approve = async (req) => {
  approving.value = true
  try {
    const payload = { supervisor_id: supervisorId.value }
    if (req.changeDate && req.newDate) {
      payload.approved_date = req.newDate
    }
    await api.put(`/api/wfh/${req.id}/approve`, payload)
    showToast('success', 'อนุมัติสำเร็จ')
    loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  approving.value = false
}

const reject = async (req) => {
  approving.value = true
  try {
    await api.put(`/api/wfh/${req.id}/reject`, { supervisor_id: supervisorId.value })
    showToast('success', 'ปฏิเสธสำเร็จ')
    loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  approving.value = false
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('th-TH', { 
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
  })
}

const showToast = (type, message) => {
  toast.value = { type, message }
  setTimeout(() => toast.value = null, 3000)
}

onMounted(loadData)
</script>
