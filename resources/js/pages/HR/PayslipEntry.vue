<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">กรอกสลิปเงินเดือน</h1>
        <p class="text-gray-500">กรอกข้อมูลสลิปเงินเดือนทีละหลายคน</p>
      </div>
      <div class="flex gap-3 items-center">
        <select v-model="selectedMonth" class="px-3 py-2 border rounded-lg text-sm">
          <option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option>
        </select>
        <select v-model="selectedYear" class="px-3 py-2 border rounded-lg text-sm">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
      <svg class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div class="text-sm text-blue-800">
        <p class="font-medium">วิธีใช้งาน</p>
        <p>คลิกที่แถวพนักงานเพื่อเปิด/ปิดช่องกรอกข้อมูล กรอกข้อมูลแล้วกด "บันทึก" หรือ "บันทึกทั้งหมด" เพื่อบันทึกหลายรายการพร้อมกัน</p>
      </div>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
      <button @click="expandAll" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">
        เปิดทั้งหมด
      </button>
      <button @click="collapseAll" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">
        ปิดทั้งหมด
      </button>
      <div class="flex-1"></div>
      <button
        @click="saveAll"
        :disabled="savingAll || dirtyCount === 0"
        class="px-5 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm font-medium flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {{ savingAll ? 'กำลังบันทึก...' : 'บันทึกทั้งหมด (' + dirtyCount + ')' }}
      </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">รหัส</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อ</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">แผนก</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">เงินสุทธิ</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">บันทึก</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">กำลังโหลด...</td>
          </tr>
          <tr v-else-if="employees.length === 0">
            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">ไม่พบพนักงาน</td>
          </tr>
          <template v-for="emp in employees" :key="emp.id">
            <tr
              @click="toggleExpand(emp.id)"
              :class="[
                'cursor-pointer transition-colors',
                expanded[emp.id] ? 'bg-blue-50' : 'hover:bg-gray-50',
                dirty[emp.id] ? 'border-l-4 border-l-amber-400' : ''
              ]"
            >
              <td class="px-4 py-3">
                <svg
                  :class="['w-4 h-4 text-gray-400 transition-transform', expanded[emp.id] ? 'rotate-90' : '']"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900">{{ emp.employee_code }}</td>
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ emp.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ emp.department || '-' }}</td>
              <td class="px-4 py-3">
                <span :class="[
                  'text-[10px] font-bold px-2 py-0.5 rounded-full',
                  emp.has_payslip ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'
                ]">
                  {{ emp.has_payslip ? 'กรอกแล้ว' : 'ยังไม่กรอก' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm font-medium text-right">
                <span v-if="dirty[emp.id]" class="text-amber-600">
                  {{ formatMoneyPreview(netSalary(emp.id)) }}
                </span>
                <span v-else class="text-gray-900">
                  {{ emp.has_payslip ? formatMoneyPreview(emp.net_salary) : '-' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button
                  v-if="dirty[emp.id]"
                  @click.stop="saveOne(emp)"
                  :disabled="savingIds[emp.id]"
                  class="text-emerald-600 hover:text-emerald-800 text-sm font-medium"
                >
                  {{ savingIds[emp.id] ? '...' : 'บันทึก' }}
                </button>
              </td>
            </tr>
            <tr v-if="expanded[emp.id]">
              <td colspan="7" class="px-4 py-4 bg-blue-50/50" @click.stop>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  <div>
                    <p class="text-xs font-bold text-emerald-600 mb-3 uppercase">รายรับ</p>
                    <div class="space-y-2">
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">เงินเดือนพื้นฐาน</label>
                        <input type="number" v-model.number="forms[emp.id].base_salary" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">ค่าโอที</label>
                        <input type="number" v-model.number="forms[emp.id].ot_pay" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">โบนัส</label>
                        <input type="number" v-model.number="forms[emp.id].bonus" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">ค่าเดินทาง</label>
                        <input type="number" v-model.number="forms[emp.id].transport_allowance" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">ค่าอาหาร</label>
                        <input type="number" v-model.number="forms[emp.id].meal_allowance" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">เบี้ยเลี้ยงอื่นๆ</label>
                        <input type="number" v-model.number="forms[emp.id].other_allowance" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-bold text-red-600 mb-3 uppercase">รายการหัก</p>
                    <div class="space-y-2">
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">หักสาย</label>
                        <input type="number" v-model.number="forms[emp.id].deduction_late" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">หักขาดงาน</label>
                        <input type="number" v-model.number="forms[emp.id].deduction_absent" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">ประกันสังคม</label>
                        <input type="number" v-model.number="forms[emp.id].deduction_social_security" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">ภาษี ณ ที่จ่าย</label>
                        <input type="number" v-model.number="forms[emp.id].deduction_tax" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                      <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-gray-600 w-36 shrink-0">หักอื่นๆ</label>
                        <input type="number" v-model.number="forms[emp.id].deduction_other" min="0" class="w-36 border rounded-lg p-2 text-sm text-right" />
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-bold text-gray-600 mb-3 uppercase">สรุป</p>
                    <div class="bg-white rounded-xl p-4 space-y-2 border">
                      <div class="flex justify-between text-sm">
                        <span class="text-gray-500">รวมรายรับ</span>
                        <span class="font-medium text-emerald-600">{{ formatMoneyPreview(totalIncome(emp.id)) }}</span>
                      </div>
                      <div class="flex justify-between text-sm">
                        <span class="text-gray-500">รวมรายการหัก</span>
                        <span class="font-medium text-red-600">{{ formatMoneyPreview(totalDeduction(emp.id)) }}</span>
                      </div>
                      <div class="border-t pt-2 flex justify-between">
                        <span class="font-bold text-gray-800">เงินสุทธิ</span>
                        <span class="font-bold text-blue-600 text-lg">{{ formatMoneyPreview(netSalary(emp.id)) }}</span>
                      </div>
                    </div>
                    <div class="mt-3">
                      <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                      <textarea v-model="forms[emp.id].note" rows="2" class="w-full border rounded-lg p-2 text-sm" placeholder="หมายเหตุถึงพนักงาน..." />
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const loading = ref(true)
const savingAll = ref(false)
const employees = ref([])
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const expanded = reactive({})
const forms = reactive({})
const dirty = reactive({})
const savingIds = reactive({})

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1]
})

const dirtyCount = computed(() => {
  return Object.keys(dirty).filter(k => dirty[k]).length
})

function createEmptyForm() {
  return {
    base_salary: 0, ot_pay: 0, bonus: 0, transport_allowance: 0,
    meal_allowance: 0, other_allowance: 0,
    deduction_late: 0, deduction_absent: 0, deduction_social_security: 0,
    deduction_tax: 0, deduction_other: 0, note: '',
  }
}

function totalIncome(empId) {
  const f = forms[empId] || createEmptyForm()
  return Number(f.base_salary) + Number(f.ot_pay) + Number(f.bonus) +
    Number(f.transport_allowance) + Number(f.meal_allowance) + Number(f.other_allowance)
}

function totalDeduction(empId) {
  const f = forms[empId] || createEmptyForm()
  return Number(f.deduction_late) + Number(f.deduction_absent) +
    Number(f.deduction_social_security) + Number(f.deduction_tax) + Number(f.deduction_other)
}

function netSalary(empId) {
  return totalIncome(empId) - totalDeduction(empId)
}

function formatMoneyPreview(v) {
  return new Intl.NumberFormat('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v || 0) + ' บาท'
}

function toggleExpand(empId) {
  expanded[empId] = !expanded[empId]
}

function expandAll() {
  employees.value.forEach(emp => { expanded[emp.id] = true })
}

function collapseAll() {
  Object.keys(expanded).forEach(k => { expanded[k] = false })
}

async function loadEmployees() {
  loading.value = true
  try {
    const res = await axios.get('/api/hr/payslips', {
      params: { month: selectedMonth.value, year: selectedYear.value }
    })
    if (res.data.success) {
      employees.value = res.data.data
      employees.value.forEach(emp => {
        if (!forms[emp.id]) {
          forms[emp.id] = createEmptyForm()
        }
      })
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadFormData(empId) {
  try {
    const res = await axios.get(`/api/hr/payslips/${empId}`, {
      params: { month: selectedMonth.value, year: selectedYear.value }
    })
    if (res.data.success) {
      const d = res.data.data
      forms[empId] = {
        base_salary: d.base_salary || 0,
        ot_pay: d.ot_pay || 0,
        bonus: d.bonus || 0,
        transport_allowance: d.transport_allowance || 0,
        meal_allowance: d.meal_allowance || 0,
        other_allowance: d.other_allowance || 0,
        deduction_late: d.deduction_late || 0,
        deduction_absent: d.deduction_absent || 0,
        deduction_social_security: d.deduction_social_security || 0,
        deduction_tax: d.deduction_tax || 0,
        deduction_other: d.deduction_other || 0,
        note: d.note || '',
      }
      dirty[empId] = false
    }
  } catch (e) {
    console.error(e)
  }
}

watch(selectedMonth, async () => {
  employees.value = []
  Object.keys(forms).forEach(k => delete forms[k])
  Object.keys(dirty).forEach(k => delete dirty[k])
  Object.keys(expanded).forEach(k => delete expanded[k])
  await loadEmployees()
  employees.value.forEach(emp => loadFormData(emp.id))
})

watch(selectedYear, async () => {
  employees.value = []
  Object.keys(forms).forEach(k => delete forms[k])
  Object.keys(dirty).forEach(k => delete dirty[k])
  Object.keys(expanded).forEach(k => delete expanded[k])
  await loadEmployees()
  employees.value.forEach(emp => loadFormData(emp.id))
})

async function saveOne(emp) {
  savingIds[emp.id] = true
  try {
    const f = forms[emp.id]
    await axios.post(`/api/hr/payslips/${emp.id}`, {
      ...f,
      month: selectedMonth.value,
      year: selectedYear.value,
    })
    dirty[emp.id] = false
    emp.has_payslip = true
    emp.net_salary = netSalary(emp.id)
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    savingIds[emp.id] = false
  }
}

async function saveAll() {
  savingAll.value = true
  const dirtyEmpIds = Object.keys(dirty).filter(k => dirty[k]).map(Number)
  let saved = 0
  let failed = 0

  for (const empId of dirtyEmpIds) {
    try {
      const f = forms[empId]
      await axios.post(`/api/hr/payslips/${empId}`, {
        ...f,
        month: selectedMonth.value,
        year: selectedYear.value,
      })
      dirty[empId] = false
      const emp = employees.value.find(e => e.id === empId)
      if (emp) {
        emp.has_payslip = true
        emp.net_salary = netSalary(empId)
      }
      saved++
    } catch (e) {
      failed++
      console.error('Failed to save payslip for emp', empId, e)
    }
  }

  savingAll.value = false
  if (failed > 0) {
    alert('บันทึกสำเร็จ ' + saved + ' รายการ, ไม่สำเร็จ ' + failed + ' รายการ')
  }
}

onMounted(async () => {
  await loadEmployees()
  employees.value.forEach(emp => loadFormData(emp.id))
})
</script>
