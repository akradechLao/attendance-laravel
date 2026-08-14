<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">OT อัตโนมัติ</h1>
          <p class="text-gray-500">ตรวจสอบและอนุมัติ OT ที่ระบบตรวจจับอัตโนมัติ (มาเร็ว/กลับช้า ≥ 1 ชม.)</p>
        </div>
        <button
          v-if="stats.pending_count > 0"
          @click="approveAll"
          class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 flex items-center gap-2"
        >
          <span>✅</span> อนุมัติทั้งหมด ({{ stats.pending_count }})
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
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท OT</label>
            <select v-model="selectedType" class="input-field" @change="loadData">
              <option value="">ทั้งหมด</option>
              <option value="before_shift">มาเร็ว (ก่อนเวลาเริ่มงาน)</option>
              <option value="after_shift">กลับช้า (หลังเวลาเลิกงาน)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
            <select v-model="selectedStatus" class="input-field" @change="loadData">
              <option value="">ทั้งหมด</option>
              <option value="pending">รออนุมัติ</option>
              <option value="approved">อนุมัติแล้ว</option>
              <option value="rejected">ไม่อนุมัติ</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">รออนุมัติ</p>
          <p class="text-2xl font-bold" :class="stats.pending_count > 0 ? 'text-orange-600' : 'text-gray-400'">{{ stats.pending_count }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">อนุมัติแล้ว</p>
          <p class="text-2xl font-bold text-green-600">{{ stats.approved_count }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">OT ทั้งหมดวันนี้</p>
          <p class="text-2xl font-bold text-navy">{{ formatMinutes(stats.total_minutes) }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">OT อนุมัติแล้ว</p>
          <p class="text-2xl font-bold text-blue-600">{{ formatMinutes(stats.approved_minutes) }}</p>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <div v-if="records.length === 0" class="card text-center py-8 text-gray-400">ไม่มีรายการ OT อัตโนมัติวันนี้</div>
        <div v-else class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ประเภท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาจริง</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาตามกะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">OT</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-center px-4 py-3 text-xs font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50" :class="record.status === 'pending' ? 'bg-orange-50/30' : ''">
                  <td class="px-4 py-3 text-sm font-medium text-navy">{{ record.employee_name }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.company_name }}</td>
                  <td class="px-4 py-3">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      record.ot_type === 'before_shift' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'
                    ]">
                      {{ record.ot_type_label }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.ot_type === 'before_shift' ? 'text-blue-600' : 'text-purple-600'">
                    {{ record.actual_time }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-500">{{ record.shift_time }}</td>
                  <td class="px-4 py-3 text-sm font-bold text-orange-600">{{ record.ot_hours_display }}</td>
                  <td class="px-4 py-3">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      record.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                      record.status === 'approved' ? 'bg-green-100 text-green-700' :
                      'bg-red-100 text-red-700'
                    ]">{{ record.status_label }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <div v-if="record.status === 'pending'" class="flex gap-2 justify-center">
                      <button @click="approveRecord(record)" class="px-3 py-1 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100">อนุมัติ</button>
                      <button @click="openRejectModal(record)" class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100">ไม่อนุมัติ</button>
                    </div>
                    <span v-else-if="record.status === 'approved'" class="text-xs text-green-600">โดย {{ record.approved_by }}</span>
                    <span v-else class="text-xs text-red-600">ไม่อนุมัติ</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-navy mb-4">ไม่อนุมัติ OT</h3>
        <div class="space-y-2 mb-4">
          <div class="text-sm"><span class="font-medium text-gray-600">พนักงาน:</span> {{ rejectingRecord?.employee_name }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">ประเภท:</span> {{ rejectingRecord?.ot_type_label }}</div>
          <div class="text-sm"><span class="font-medium text-gray-600">OT:</span> {{ rejectingRecord?.ot_hours_display }}</div>
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
import { ref, reactive, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const records = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const selectedType = ref('')
const selectedStatus = ref('')
const stats = ref({ total_minutes: 0, pending_count: 0, approved_count: 0, approved_minutes: 0 })

const showRejectModal = ref(false)
const rejectingRecord = ref(null)
const rejectForm = reactive({ rejection_reason: '' })

function formatMinutes(m) {
  if (!m) return '0 ชม.'
  const h = Math.floor(m / 60)
  const min = m % 60
  return min > 0 ? `${h} ชม. ${min} น.` : `${h} ชม.`
}

async function loadData() {
  loading.value = true
  try {
    const params = { date: selectedDate.value, company_id: selectedCompany.value }
    if (selectedType.value) params.ot_type = selectedType.value
    if (selectedStatus.value) params.status = selectedStatus.value
    const [res, companiesRes] = await Promise.all([
      api.get('/api/auto-ot', { params }),
      api.get('/api/companies'),
    ])
    records.value = res.data?.data?.records || []
    stats.value = res.data?.data?.stats || {}
    companies.value = companiesRes.data?.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function approveRecord(record) {
  if (!confirm(`อนุมัติ OT ${record.ot_hours_display} ของ ${record.employee_name} ใช่หรือไม่?`)) return
  try {
    await api.put(`/api/auto-ot/${record.id}/approve`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

function openRejectModal(record) {
  rejectingRecord.value = record
  rejectForm.rejection_reason = ''
  showRejectModal.value = true
}

async function confirmReject() {
  saving.value = true
  try {
    await api.put(`/api/auto-ot/${rejectingRecord.value.id}/reject`, rejectForm)
    showRejectModal.value = false
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function approveAll() {
  if (!confirm(`อนุมัติ OT ทั้งหมด ${stats.value.pending_count} รายการ ใช่หรือไม่?`)) return
  try {
    await api.post('/api/auto-ot/approve-all', {
      date: selectedDate.value,
      company_id: selectedCompany.value,
    })
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(loadData)
</script>
