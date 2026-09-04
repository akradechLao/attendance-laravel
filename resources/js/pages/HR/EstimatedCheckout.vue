<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">Checkout ที่รออนุมัติ</h1>
          <p class="text-gray-500">รายการที่ระบบเติม check_out อัตโนมัติ — ต้องได้รับการอนุมัติจากหัวหน้าหรือ HR</p>
        </div>
        <div class="flex items-center gap-3">
          <select v-model="selectedCompany" class="input-field w-auto" @change="fetchData">
            <option value="">ทุกบริษัท</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <input v-model="selectedDate" type="date" class="input-field w-auto" @change="fetchData" />
          <button @click="fetchData" class="btn-secondary">
            <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            รีเฟรช
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-12"><LoadingSpinner /></div>

      <template v-else>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">รออนุมัติ</p>
            <p class="text-3xl font-bold text-orange-600">{{ records.length }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">อนุมัติแล้ววันนี้</p>
            <p class="text-3xl font-bold text-green-600">{{ approvedToday }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">สายเฉลี่ย</p>
            <p class="text-3xl font-bold text-yellow-600">{{ avgLate }}</p>
          </div>
          <div class="card text-center">
            <p class="text-xs text-gray-500 mb-1">ยังไม่ได้เช็คเอาท์จริง</p>
            <p class="text-3xl font-bold text-red-600">{{ records.filter(r => !r.estimated_approved_by).length }}</p>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-navy">รายการที่ต้องตรวจสอบ ({{ records.length }})</h2>
            <button v-if="records.length > 0 && records.some(r => !r.estimated_approved_by)" @click="approveAll" class="btn-primary text-sm" :disabled="approving">
              {{ approving ? 'กำลังอนุมัติ...' : 'อนุมัติทั้งหมด' }}
            </button>
          </div>

          <div v-if="records.length === 0" class="text-center py-8 text-gray-500">ไม่มีรายการที่ต้องตรวจสอบ</div>

          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">แผนก</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">กะที่กำหนด</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาเข้า</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">Checkout (ระบบ)</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สาย</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">จัดการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <span class="font-medium text-gray-800 text-sm">{{ record.employee_name }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.department }}</td>
                  <td class="px-4 py-3">
                    <div class="text-sm">
                      <span class="font-medium text-navy">{{ record.shift_code || 'office' }}</span>
                      <span class="text-gray-400 ml-1">{{ record.shift_start }}-{{ record.shift_end }}</span>
                    </div>
                    <div class="text-[10px] text-gray-400">来源: {{ record.shift_source }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm font-medium text-green-600">{{ record.check_in }}</td>
                  <td class="px-4 py-3">
                    <div v-if="editingId === record.id" class="flex items-center gap-1">
                      <input v-model="editTime" type="time" class="input-field w-24 text-sm" />
                      <button @click="saveEdit(record)" class="text-green-600 hover:text-green-800 text-xs">บันทึก</button>
                      <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600 text-xs">ยกเลิก</button>
                    </div>
                    <div v-else class="flex items-center gap-1">
                      <span class="text-sm text-orange-600 font-medium">{{ record.check_out }}</span>
                      <span class="text-orange-500 text-xs">⚠️</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm" :class="record.late_minutes > 0 ? 'text-yellow-600 font-medium' : 'text-gray-400'">
                    {{ record.late_minutes > 0 ? record.late_minutes + ' นาที' : '-' }}
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="record.estimated_approved_by" class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">อนุมัติแล้ว</span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">รออนุมัติ</span>
                  </td>
                  <td class="px-4 py-3">
                    <div v-if="!record.estimated_approved_by" class="flex items-center gap-2">
                      <button @click="approve(record)" class="text-green-600 hover:text-green-800 text-sm" :disabled="approving">อนุมัติ</button>
                      <button @click="startEdit(record)" class="text-blue-600 hover:text-blue-800 text-sm">แก้ไข</button>
                    </div>
                    <span v-else class="text-xs text-gray-400">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(true)
const approving = ref(false)
const records = ref([])
const companies = ref([])
const selectedCompany = ref('')
const selectedDate = ref(new Date().toISOString().split('T')[0])
const editingId = ref(null)
const editTime = ref('')

const approvedToday = ref(0)
const avgLate = ref(0)

async function fetchData() {
  loading.value = true
  try {
    const params = { date: selectedDate.value }
    if (selectedCompany.value) params.company_id = selectedCompany.value

    const [res, companiesRes] = await Promise.all([
      api.get('/api/attendance/estimated-checkouts', { params }),
      api.get('/api/companies')
    ])

    records.value = res.data?.data || []
    companies.value = companiesRes.data?.data || []

    const approved = records.value.filter(r => r.estimated_approved_by)
    approvedToday.value = approved.length
    const lateRecords = records.value.filter(r => r.late_minutes > 0)
    avgLate.value = lateRecords.length > 0
      ? Math.round(lateRecords.reduce((sum, r) => sum + r.late_minutes, 0) / lateRecords.length)
      : 0
  } catch (error) {
    console.error('Fetch error:', error)
  } finally {
    loading.value = false
  }
}

async function approve(record) {
  try {
    await api.put(`/api/attendance/estimated-checkouts/${record.id}/approve`)
    record.estimated_approved_by = 1
  } catch (error) {
    alert('ไม่สามารถอนุมัติได้: ' + (error.response?.data?.message || error.message))
  }
}

async function approveAll() {
  if (!confirm('อนุมัติทั้งหมด ' + records.value.filter(r => !r.estimated_approved_by).length + ' รายการ?')) return
  approving.value = true
  try {
    const pending = records.value.filter(r => !r.estimated_approved_by)
    await Promise.all(pending.map(r =>
      api.put(`/api/attendance/estimated-checkouts/${r.id}/approve`)
    ))
    fetchData()
  } catch (error) {
    alert('เกิดข้อผิดพลาด: ' + (error.response?.data?.message || error.message))
  } finally {
    approving.value = false
  }
}

function startEdit(record) {
  editingId.value = record.id
  editTime.value = record.check_out
}

function cancelEdit() {
  editingId.value = null
  editTime.value = ''
}

async function saveEdit(record) {
  try {
    await api.put(`/api/attendance/estimated-checkouts/${record.id}/edit`, {
      check_out: editTime.value
    })
    record.check_out = editTime.value
    record.estimated_approved_by = 1
    editingId.value = null
  } catch (error) {
    alert('ไม่สามารถบันทึกได้: ' + (error.response?.data?.message || error.message))
  }
}

onMounted(fetchData)
</script>
