<template>
  <div class="p-4 space-y-6">
    <h1 class="text-2xl font-bold text-[#0f172a]">ขอสลับกะทำงาน</h1>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">สร้างคำขอสลับกะ</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">พนักงานที่ต้องการสลับ</label>
          <select v-model="form.target_id" class="w-full border rounded-lg p-2">
            <option value="">เลือกพนักงาน</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
              {{ emp.name }} ({{ emp.employee_code }})
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">วันที่ต้องการสลับ</label>
          <input type="date" v-model="form.swap_date" class="w-full border rounded-lg p-2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">กะของฉัน</label>
            <select v-model="form.requester_shift" class="w-full border rounded-lg p-2">
              <option value="">เลือกกะ</option>
              <option value="เช้า">เช้า (06:00-14:00)</option>
              <option value="กลางวัน">กลางวัน (09:00-17:00)</option>
              <option value="เย็น">เย็น (14:00-22:00)</option>
              <option value="ดึก">ดึก (22:00-06:00)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">กะของพนักงานอีกคน</label>
            <select v-model="form.target_shift" class="w-full border rounded-lg p-2">
              <option value="">เลือกกะ</option>
              <option value="เช้า">เช้า (06:00-14:00)</option>
              <option value="กลางวัน">กลางวัน (09:00-17:00)</option>
              <option value="เย็น">เย็น (14:00-22:00)</option>
              <option value="ดึก">ดึก (22:00-06:00)</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">เหตุผล (ไม่บังคับ)</label>
          <textarea v-model="form.reason" rows="2" class="w-full border rounded-lg p-2" placeholder="เหตุผลการสลับกะ..." />
        </div>
        <button @click="submitSwap" :disabled="submitting"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอสลับกะ' }}
        </button>
      </div>
    </div>

    <!-- My Requests -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">คำขอของฉัน</h2>
      <div v-if="mySwaps.length === 0" class="text-center py-4 text-gray-500">ยังไม่มีคำขอ</div>
      <div v-else class="space-y-3">
        <div v-for="swap in mySwaps" :key="swap.id"
             class="p-3 rounded-lg flex justify-between items-center"
             :class="{'bg-yellow-50': swap.status==='pending', 'bg-green-50': swap.status==='approved', 'bg-red-50': swap.status==='rejected'}">
          <div>
            <div class="font-semibold">{{ swap.requester?.name }} ⇄ {{ swap.target?.name }}</div>
            <div class="text-xs text-gray-500">
              {{ formatDate(swap.swap_date) }} | {{ swap.requester_shift }} → {{ swap.target_shift }}
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="{'bg-yellow-100 text-yellow-700': swap.status==='pending', 'bg-green-100 text-green-700': swap.status==='approved', 'bg-red-100 text-red-700': swap.status==='rejected'}">
            {{ statusText(swap.status) }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type==='success' ? 'bg-green-600' : 'bg-red-600'">{{ toast.message }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const employees = ref([])
const mySwaps = ref([])
const submitting = ref(false)
const toast = ref(null)
const employeeId = computed(() => state.user?.id)

const form = ref({ target_id: '', swap_date: '', requester_shift: '', target_shift: '', reason: '' })

const loadData = async () => {
  try {
    const [empRes, swapRes] = await Promise.all([
      api.get('/api/employees', { params: { per_page: 200 } }),
      api.get('/api/shift-swaps/my-requests', { params: { emp_id: employeeId.value } })
    ])
    employees.value = (empRes.data.data?.data || empRes.data.data || []).filter(e => e.id !== employeeId.value)
    mySwaps.value = swapRes.data.data || []
  } catch (err) { console.error(err) }
}

const submitSwap = async () => {
  submitting.value = true
  try {
    await api.post('/api/shift-swaps', { ...form.value, requester_id: employeeId.value })
    showToast('success', 'ส่งคำขอสลับกะสำเร็จ')
    form.value = { target_id: '', swap_date: '', requester_shift: '', target_shift: '', reason: '' }
    loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  submitting.value = false
}

const formatDate = (d) => new Date(d).toLocaleDateString('th-TH', { year:'numeric', month:'long', day:'numeric' })
const statusText = (s) => ({ pending:'รอหัวหน้าอนุมัติ', approved:'อนุมัติแล้ว', rejected:'ปฏิเสธ' })[s] || s
const showToast = (type, message) => { toast.value = {type, message}; setTimeout(() => toast.value = null, 3000) }

onMounted(loadData)
</script>
