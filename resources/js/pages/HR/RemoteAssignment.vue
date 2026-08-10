<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">ปฏิบัติงานนอกสถานที่</h1>
          <p class="text-gray-500">มอบหมายและจัดการพนักงานที่ไปทำงานต่างจังหวัด</p>
        </div>
        <button @click="openCreateModal" class="btn-primary">+ มอบหมาย</button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-yellow-50 border-l-4 border-yellow-400">
          <p class="text-sm text-gray-500">รออนุมัติ</p>
          <p class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</p>
        </div>
        <div class="card bg-green-50 border-l-4 border-green-400">
          <p class="text-sm text-gray-500">อนุมัติแล้ว</p>
          <p class="text-2xl font-bold text-green-600">{{ stats.approved }}</p>
        </div>
        <div class="card bg-blue-50 border-l-4 border-blue-400">
          <p class="text-sm text-gray-500">กำลังปฏิบัติงาน</p>
          <p class="text-2xl font-bold text-blue-600">{{ stats.active }}</p>
        </div>
        <div class="card bg-red-50 border-l-4 border-red-400">
          <p class="text-sm text-gray-500">ปฏิเสธ</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.rejected }}</p>
        </div>
      </div>

      <!-- Filter -->
      <div class="card">
        <div class="flex flex-wrap gap-4">
          <select v-model="filter.status" class="input-field w-48" @change="loadAssignments">
            <option value="">ทุกสถานะ</option>
            <option value="pending">รออนุมัติ</option>
            <option value="approved">อนุมัติแล้ว</option>
            <option value="rejected">ปฏิเสธ</option>
          </select>
          <select v-model="filter.company_id" class="input-field w-48" @change="loadAssignments">
            <option value="">ทุกบริษัท</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="card overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">พนักงาน</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">บริษัท</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">วันที่ปฏิบัติงาน</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">จุดหมาย</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">สถานะ</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">จัดการ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="item in assignments" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div>
                  <p class="font-medium text-navy text-sm">{{ item.employee?.name }}</p>
                  <p class="text-xs text-gray-500">{{ item.employee?.employee_code }}</p>
                </div>
              </td>
              <td class="px-4 py-3 text-sm">{{ item.company?.name }}</td>
              <td class="px-4 py-3 text-sm">{{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}</td>
              <td class="px-4 py-3 text-sm">{{ item.destination || '-' }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(item.status)">{{ statusText(item.status) }}</span>
              </td>
              <td class="px-4 py-3">
                <div v-if="item.status === 'pending'" class="flex gap-2">
                  <button @click="approve(item)" class="text-green-600 hover:text-green-700 text-sm font-medium">อนุมัติ</button>
                  <button @click="reject(item)" class="text-red-600 hover:text-red-700 text-sm font-medium">ปฏิเสธ</button>
                </div>
                <span v-else class="text-xs text-gray-400">{{ item.approver?.name || '-' }}</span>
              </td>
            </tr>
            <tr v-if="assignments.length === 0">
              <td colspan="6" class="px-8 py-12 text-center text-gray-400">ไม่มีข้อมูล</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showCreateModal = false">
      <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-navy mb-4">มอบหมายปฏิบัติงานนอกสถานที่</h3>
          <form @submit.prevent="createAssignment" class="space-y-4">

            <!-- Company -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท *</label>
              <select v-model="form.company_id" class="input-field" required @change="onCompanyChange">
                <option value="">เลือกบริษัท</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <!-- Employee search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
              <div v-if="form.employee_id && selectedEmployeeName" class="flex items-center gap-2 p-2 bg-blue-50 rounded-lg">
                <span class="text-sm font-medium text-navy flex-1">{{ selectedEmployeeName }}</span>
                <button type="button" @click="clearEmployee" class="text-xs text-red-500 hover:text-red-700">เปลี่ยน</button>
              </div>
              <div v-else>
                <input v-model="employeeSearch" @input="searchEmployees" type="text" class="input-field w-full" placeholder="พิมพ์ชื่อหรือรหัสพนักงาน..." />
                <div v-if="employeeResults.length > 0" class="mt-1 border rounded-lg divide-y max-h-48 overflow-y-auto bg-white shadow-lg">
                  <button v-for="emp in employeeResults" :key="emp.id" type="button" @click="selectEmployee(emp)" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-sm">
                    <span class="font-medium">{{ emp.name }}</span>
                    <span class="text-gray-500 ml-2">{{ emp.employee_code }}</span>
                    <span v-if="emp.division" class="text-gray-400 ml-2">({{ emp.division }})</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันเริ่มต้น *</label>
                <input v-model="form.start_date" type="date" class="input-field" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันสิ้นสุด *</label>
                <input v-model="form.end_date" type="date" class="input-field" required />
              </div>
            </div>

            <!-- Destination -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">จุดหมาย (จังหวัด/สถานที่)</label>
              <input v-model="form.destination" type="text" class="input-field" placeholder="เช่น จ.เชียงใหม่" />
            </div>

            <!-- Reason -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
              <textarea v-model="form.reason" class="input-field" rows="3" placeholder="เหตุผลในการปฏิบัติงานนอกสถานที่"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <button type="button" @click="showCreateModal = false" class="btn-secondary">ยกเลิก</button>
              <button type="submit" class="btn-primary" :disabled="submitting || !form.employee_id">
                {{ submitting ? 'กำลังบันทึก...' : 'บันทึก' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const assignments = ref([])
const companies = ref([])
const showCreateModal = ref(false)
const submitting = ref(false)
const filter = ref({ status: '', company_id: '' })

const form = ref({
  employee_id: '',
  company_id: '',
  start_date: '',
  end_date: '',
  destination: '',
  reason: '',
})

const employeeSearch = ref('')
const employeeResults = ref([])
const selectedEmployeeName = ref('')

const stats = computed(() => {
  const now = new Date()
  return {
    pending: assignments.value.filter(a => a.status === 'pending').length,
    approved: assignments.value.filter(a => a.status === 'approved').length,
    rejected: assignments.value.filter(a => a.status === 'rejected').length,
    active: assignments.value.filter(a => {
      if (a.status !== 'approved') return false
      return new Date(a.start_date) <= now && new Date(a.end_date) >= now
    }).length,
  }
})

onMounted(async () => {
  const res = await api.get('/api/companies')
  companies.value = res.data.data || res.data
  loadAssignments()
})

async function loadAssignments() {
  const params = {}
  if (filter.value.status) params.status = filter.value.status
  if (filter.value.company_id) params.company_id = filter.value.company_id
  const res = await api.get('/api/remote-assignments', { params })
  assignments.value = res.data.data?.data || res.data.data || []
}

function openCreateModal() {
  form.value = { employee_id: '', company_id: '', start_date: '', end_date: '', destination: '', reason: '' }
  employeeSearch.value = ''
  employeeResults.value = []
  selectedEmployeeName.value = ''
  showCreateModal.value = true
}

async function onCompanyChange() {
  form.value.employee_id = ''
  selectedEmployeeName.value = ''
  employeeSearch.value = ''
  employeeResults.value = []
}

let searchTimeout = null
async function searchEmployees() {
  clearTimeout(searchTimeout)
  if (!employeeSearch.value || employeeSearch.value.length < 2) {
    employeeResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const params = { search: employeeSearch.value }
      if (form.value.company_id) params.company_id = form.value.company_id
      const res = await api.get('/api/employees', { params })
      employeeResults.value = res.data.data || []
    } catch (e) {
      employeeResults.value = []
    }
  }, 300)
}

function selectEmployee(emp) {
  form.value.employee_id = emp.id
  selectedEmployeeName.value = `${emp.name} (${emp.employee_code})`
  employeeSearch.value = ''
  employeeResults.value = []
}

function clearEmployee() {
  form.value.employee_id = ''
  selectedEmployeeName.value = ''
}

async function createAssignment() {
  if (!form.value.employee_id) return
  submitting.value = true
  try {
    await api.post('/api/remote-assignments', form.value)
    showCreateModal.value = false
    loadAssignments()
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  } finally {
    submitting.value = false
  }
}

async function approve(item) {
  if (!confirm(`อนุมัติคำขอของ ${item.employee?.name}?`)) return
  try {
    await api.put(`/api/remote-assignments/${item.id}/approve`, {})
    loadAssignments()
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

async function reject(item) {
  if (!confirm(`ปฏิเสธคำขอของ ${item.employee?.name}?`)) return
  try {
    await api.put(`/api/remote-assignments/${item.id}/reject`, {})
    loadAssignments()
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('th-TH')
}

function statusText(s) {
  return { pending: 'รออนุมัติ', approved: 'อนุมัติ', rejected: 'ปฏิเสธ' }[s] || s
}

function statusBadgeClass(s) {
  const base = 'px-2 py-1 rounded-full text-xs font-medium'
  return {
    pending: base + ' bg-yellow-100 text-yellow-700',
    approved: base + ' bg-green-100 text-green-700',
    rejected: base + ' bg-red-100 text-red-700',
  }[s] || base
}
</script>
