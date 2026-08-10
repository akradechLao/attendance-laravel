<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">มอบหมาย OT บังคับ</h1>
          <p class="text-gray-500">กำหนด OT ให้พนักงาน (บังคับเข้าทำ)</p>
        </div>
        <button @click="showForm = true" class="btn-primary">+ มอบหมาย OT</button>
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
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <!-- List -->
        <div class="card overflow-hidden" v-if="assignments.length > 0">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">พนักงาน</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">วันที่</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">เวลา OT</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">เหตุผล</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="a in assignments" :key="a.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 text-sm font-medium text-navy">{{ a.employee?.name || '-' }}</td>
                  <td class="px-6 py-4 text-sm text-gray-600">{{ a.company?.name || '-' }}</td>
                  <td class="px-6 py-4 text-sm text-gray-600">{{ a.ot_date }}</td>
                  <td class="px-6 py-4 text-sm text-gray-600">{{ a.start_time }} - {{ a.end_time }}</td>
                  <td class="px-6 py-4 text-sm text-gray-500">{{ a.reason || '-' }}</td>
                  <td class="px-6 py-4">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      a.status === 'assigned' ? 'bg-blue-100 text-blue-700' :
                      a.status === 'completed' ? 'bg-green-100 text-green-700' :
                      'bg-gray-100 text-gray-500'
                    ]">
                      {{ a.status === 'assigned' ? 'มอบหมายแล้ว' : a.status === 'completed' ? 'เสร็จสิ้น' : 'ยกเลิก' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button
                      v-if="a.status === 'assigned'"
                      @click="cancelAssignment(a)"
                      class="px-3 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"
                    >
                      ยกเลิก
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="card text-center py-8 text-gray-400">ไม่มีรายการ OT บังคับในวันนี้</div>
      </template>
    </div>

    <!-- Create Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6">
          <h3 class="text-lg font-bold text-navy mb-4">มอบหมาย OT บังคับ</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
              <select v-model="form.emp_id" class="input-field w-full">
                <option value="">เลือกพนักงาน</option>
                <option v-for="emp in allEmployees" :key="emp.id" :value="emp.id">
                  {{ emp.employee_code }} {{ emp.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ *</label>
              <input v-model="form.ot_date" type="date" class="input-field w-full" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเริ่ม *</label>
                <input v-model="form.start_time" type="time" class="input-field w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาสิ้นสุด *</label>
                <input v-model="form.end_time" type="time" class="input-field w-full" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
              <input v-model="form.reason" type="text" class="input-field w-full" placeholder="เหตุผลที่มอบหมาย OT" />
            </div>
            <div class="flex gap-3 justify-end pt-2 border-t">
              <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="saveAssignment" :disabled="saving" class="btn-primary text-sm">
                {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
              </button>
            </div>
          </div>
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
const assignments = ref([])
const allEmployees = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const showForm = ref(false)

const form = reactive({
  emp_id: '',
  ot_date: new Date().toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  reason: '',
})

async function loadData() {
  loading.value = true
  try {
    const [aRes, eRes, cRes] = await Promise.all([
      api.get('/api/mandatory-ot', { params: { date: selectedDate.value, company_id: selectedCompany.value } }),
      api.get('/api/employees', { params: { company_id: selectedCompany.value, per_page: 9999 } }),
      api.get('/api/companies'),
    ])
    assignments.value = aRes.data.data || []
    allEmployees.value = (eRes.data.data?.data || eRes.data.data || [])
    companies.value = cRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function saveAssignment() {
  if (!form.emp_id || !form.ot_date || !form.start_time || !form.end_time) {
    alert('กรุณากรอกข้อมูลให้ครบ')
    return
  }
  saving.value = true
  try {
    await api.post('/api/mandatory-ot', form)
    showForm.value = false
    Object.assign(form, { emp_id: '', ot_date: new Date().toISOString().slice(0, 10), start_time: '', end_time: '', reason: '' })
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function cancelAssignment(a) {
  if (!confirm(`ยกเลิก OT บังคับของ ${a.employee?.name} ใช่หรือไม่?`)) return
  try {
    await api.delete(`/api/mandatory-ot/${a.id}`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

onMounted(loadData)
</script>
