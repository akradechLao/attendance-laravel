<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">ปรับแก้สถานะเข้างาน</h1>
          <p class="text-gray-500">ตรวจสอบและปรับแก้สถานะเข้างาน / อนุมัติลากิจบังคับ</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 border-b border-gray-200">
        <button
          @click="activeTab = 'adjustment'"
          :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === 'adjustment' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
        >
          ปรับแก้สถานะเข้างาน
        </button>
        <button
          @click="activeTab = 'forced-leave'"
          :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === 'forced-leave' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
        >
          ลากิจบังคับ (สายเกิน 30 น.)
          <span v-if="pendingForcedLeaves > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-red-100 text-red-600">{{ pendingForcedLeaves }}</span>
        </button>
      </div>

      <!-- Filters -->
      <div class="card">
        <div class="flex flex-col md:flex-row gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
            <input v-model="selectedDate" type="date" class="input-field" @change="loadData" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="selectedCompany" class="input-field" @change="loadData">
              <option value="">ทุกบริษัท</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div v-if="activeTab === 'forced-leave'">
            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
            <select v-model="selectedStatus" class="input-field" @change="loadData">
              <option value="">ทุกสถานะ</option>
              <option value="pending">รออนุมัติ</option>
              <option value="approved">อนุมัติแล้ว</option>
              <option value="rejected">ไม่อนุมัติ</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <!-- Tab: Attendance Adjustment -->
      <template v-if="activeTab === 'adjustment' && !loading">
        <div v-if="records.length === 0" class="card text-center py-8 text-gray-400">ไม่มีรายการเข้างานวันนี้</div>
        <div v-else class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาเข้า</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สาย (น.)</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะเดิม</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะปัจจุบัน</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ปรับแก้โดย</th>
                  <th class="text-center px-4 py-3 text-xs font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-navy">{{ record.employee_name }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.company_name }}</td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.original_status === 'late' ? 'text-yellow-600' : 'text-green-600'">{{ record.check_in || '-' }}</td>
                  <td class="px-4 py-3 text-sm" :class="record.late_minutes > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">{{ record.late_minutes || '-' }}</td>
                  <td class="px-4 py-3">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', record.original_status === 'late' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700']">
                      {{ record.original_status === 'late' ? 'สาย' : 'ปกติ' }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', record.final_status === 'late' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700']">
                      {{ record.final_status === 'late' ? 'สาย' : 'ปกติ' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs text-gray-500">
                    <span v-if="record.adjusted_by">{{ record.adjusted_by }}<br/>{{ record.adjusted_at }}</span>
                    <span v-else>-</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      @click="openAdjustModal(record)"
                      class="px-3 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100"
                    >
                      ปรับแก้
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- Tab: Forced Leave -->
      <template v-if="activeTab === 'forced-leave' && !loading">
        <div v-if="forcedLeaves.length === 0" class="card text-center py-8 text-gray-400">ไม่มีรายการลากิจบังคับวันนี้</div>
        <div v-else class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สาย (น.)</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ประเภทลา</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เหตุผล</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-center px-4 py-3 text-xs font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="leave in forcedLeaves" :key="leave.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-navy">{{ leave.employee_name }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ leave.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ leave.company_name }}</td>
                  <td class="px-4 py-3 text-sm text-red-600 font-medium">{{ leave.late_minutes }} นาที</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ leave.leave_type === 'personal' ? 'ลากิจ 1 ชม.' : leave.leave_type }}</td>
                  <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px] truncate" :title="leave.reason">{{ leave.reason || '-' }}</td>
                  <td class="px-4 py-3">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      leave.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                      leave.status === 'approved' ? 'bg-green-100 text-green-700' :
                      'bg-red-100 text-red-700'
                    ]">{{ leave.status_label }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <div v-if="leave.status === 'pending'" class="flex gap-2 justify-center">
                      <button @click="approveLeave(leave)" class="px-3 py-1 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100">อนุมัติ</button>
                      <button @click="openRejectModal(leave)" class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100">ไม่อนุมัติ</button>
                    </div>
                    <span v-else-if="leave.status === 'approved'" class="text-xs text-green-600">อนุมัติโดย {{ leave.approved_by }}</span>
                    <span v-else class="text-xs text-red-600">ไม่อนุมัติ</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Adjust Modal -->
    <div v-if="showAdjustModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showAdjustModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-navy mb-4">ปรับแก้สถานะเข้างาน</h3>
        <div class="space-y-3 mb-4">
          <div class="text-sm"><span class="font-medium text-gray-600">พนักงาน:</span> {{ adjustingRecord?.employee_name }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">วันที่:</span> {{ adjustingRecord?.date }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">เวลาเข้า:</span> {{ adjustingRecord?.check_in }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">สาย:</span> {{ adjustingRecord?.late_minutes || 0 }} นาที</div>
          <div class="text-sm"><span class="font-medium text-gray-600">สถานะเดิม:</span>
            <span :class="adjustingRecord?.original_status === 'late' ? 'text-yellow-600' : 'text-green-600'">
              {{ adjustingRecord?.original_status === 'late' ? 'สาย' : 'ปกติ' }}
            </span>
          </div>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะใหม่ *</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2">
                <input type="radio" v-model="adjustForm.final_status" value="on_time" class="text-blue-600" />
                <span class="text-sm">ปกติ</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="radio" v-model="adjustForm.final_status" value="late" class="text-yellow-600" />
                <span class="text-sm">สาย</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
            <input v-model="adjustForm.adjustment_note" type="text" class="input-field w-full" placeholder="เหตุผลที่ปรับแก้ (ถ้ามี)" />
          </div>
        </div>
        <div class="flex gap-3 justify-end mt-6 pt-4 border-t">
          <button @click="showAdjustModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
          <button @click="saveAdjust" :disabled="saving" class="btn-primary text-sm">
            {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-navy mb-4">ไม่อนุมัติลากิจบังคับ</h3>
        <div class="space-y-3 mb-4">
          <div class="text-sm"><span class="font-medium text-gray-600">พนักงาน:</span> {{ rejectingLeave?.employee_name }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">สาย:</span> {{ rejectingLeave?.late_minutes }} นาที</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผลที่ไม่อนุมัติ *</label>
          <input v-model="rejectForm.rejection_reason" type="text" class="input-field w-full" placeholder="กรอกเหตุผล" />
        </div>
        <div class="flex gap-3 justify-end mt-6 pt-4 border-t">
          <button @click="showRejectModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
          <button @click="confirmReject" :disabled="saving || !rejectForm.rejection_reason" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ saving ? 'กำลังบันทึก...' : 'ไม่อนุมัติ' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('adjustment')
const records = ref([])
const forcedLeaves = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const selectedStatus = ref('')

const showAdjustModal = ref(false)
const adjustingRecord = ref(null)
const adjustForm = reactive({ final_status: 'on_time', adjustment_note: '' })

const showRejectModal = ref(false)
const rejectingLeave = ref(null)
const rejectForm = reactive({ rejection_reason: '' })

const pendingForcedLeaves = computed(() => forcedLeaves.value.filter(l => l.status === 'pending').length)

async function loadData() {
  loading.value = true
  try {
    const params = { date: selectedDate.value, company_id: selectedCompany.value }
    const companiesRes = await api.get('/api/companies')
    companies.value = companiesRes.data?.data || []

    if (activeTab.value === 'adjustment') {
      const res = await api.get('/api/attendance-adjustment', { params })
      records.value = res.data?.data || []
    } else {
      const fParams = { ...params }
      if (selectedStatus.value) fParams.status = selectedStatus.value
      const res = await api.get('/api/attendance-adjustment/forced-leaves', { params: fParams })
      forcedLeaves.value = res.data?.data || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openAdjustModal(record) {
  adjustingRecord.value = record
  adjustForm.final_status = record.final_status || record.original_status || 'on_time'
  adjustForm.adjustment_note = record.adjustment_note || ''
  showAdjustModal.value = true
}

async function saveAdjust() {
  saving.value = true
  try {
    await api.put(`/api/attendance-adjustment/${adjustingRecord.value.id}/adjust`, adjustForm)
    showAdjustModal.value = false
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function approveLeave(leave) {
  if (!confirm(`อนุมัติลากิจบังคับของ ${leave.employee_name} ใช่หรือไม่?`)) return
  try {
    await api.put(`/api/attendance-adjustment/forced-leaves/${leave.id}/approve`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

function openRejectModal(leave) {
  rejectingLeave.value = leave
  rejectForm.rejection_reason = ''
  showRejectModal.value = true
}

async function confirmReject() {
  saving.value = true
  try {
    await api.put(`/api/attendance-adjustment/forced-leaves/${rejectingLeave.value.id}/reject`, rejectForm)
    showRejectModal.value = false
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>
