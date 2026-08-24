<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Payslip Management</h1>
        <p class="text-gray-500">จัดการสลิปเงินเดือนพนักงาน</p>
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

    <!-- Employee List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รหัส</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อ</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">แผนก</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เงินสุทธิ</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">กำลังโหลด...</td>
          </tr>
          <tr v-else-if="employees.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">ไม่พบพนักงาน</td>
          </tr>
          <tr v-for="emp in employees" :key="emp.id">
            <td class="px-6 py-4 text-sm text-gray-900">{{ emp.employee_code }}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ emp.name }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ emp.department || '-' }}</td>
            <td class="px-6 py-4">
              <span :class="emp.has_payslip ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                class="text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ emp.has_payslip ? 'กรอกแล้ว' : 'ยังไม่กรอก' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">
              {{ emp.has_payslip ? formatMoney(emp.net_salary) : '-' }}
            </td>
            <td class="px-6 py-4">
              <button @click="openEdit(emp)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                {{ emp.has_payslip ? 'แก้ไข' : 'กรอกข้อมูล' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-lg font-bold">{{ editEmployee?.name }}</h3>
              <p class="text-gray-400 text-sm">{{ months[selectedMonth - 1] }} {{ selectedYear + 543 }}</p>
            </div>
            <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4">
            <!-- Income -->
            <div>
              <p class="text-sm font-bold text-emerald-600 mb-2">รายรับ</p>
              <div class="space-y-2">
                <Field v-model="form.base_salary" label="เงินเดือนพื้นฐาน" />
                <Field v-model="form.ot_pay" label="ค่าโอที" />
                <Field v-model="form.bonus" label="โบนัส" />
                <Field v-model="form.transport_allowance" label="ค่าเดินทาง" />
                <Field v-model="form.meal_allowance" label="ค่าอาหาร" />
                <Field v-model="form.other_allowance" label="เบี้ยเลี้ยงอื่นๆ" />
              </div>
            </div>

            <!-- Deductions -->
            <div>
              <p class="text-sm font-bold text-red-600 mb-2">รายการหัก</p>
              <div class="space-y-2">
                <Field v-model="form.deduction_late" label="หักสาย" />
                <Field v-model="form.deduction_absent" label="หักขาดงาน" />
                <Field v-model="form.deduction_social_security" label="ประกันสังคม" />
                <Field v-model="form.deduction_tax" label="ภาษี ณ ที่จ่าย" />
                <Field v-model="form.deduction_other" label="หักอื่นๆ" />
              </div>
            </div>

            <!-- Summary -->
            <div class="bg-gray-50 rounded-xl p-4 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">รวมรายรับ</span>
                <span class="font-medium text-emerald-600">{{ formatMoney(totalIncome) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">รวมรายการหัก</span>
                <span class="font-medium text-red-600">{{ formatMoney(totalDeduction) }}</span>
              </div>
              <div class="border-t pt-2 flex justify-between">
                <span class="font-bold text-gray-800">เงินสุทธิ</span>
                <span class="font-bold text-blue-600 text-lg">{{ formatMoney(netSalary) }}</span>
              </div>
            </div>

            <!-- Note -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
              <textarea v-model="form.note" rows="2" class="w-full border rounded-lg p-2 text-sm" placeholder="หมายเหตุถึงพนักงาน..." />
            </div>

            <div class="flex gap-3 justify-end">
              <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="savePayslip" :disabled="saving"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium">
                {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, h, onMounted } from 'vue'
import axios from 'axios'

const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const loading = ref(true)
const saving = ref(false)
const employees = ref([])
const showForm = ref(false)
const editEmployee = ref(null)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1]
})

const form = ref({
  base_salary: 0, ot_pay: 0, bonus: 0, transport_allowance: 0,
  meal_allowance: 0, other_allowance: 0,
  deduction_late: 0, deduction_absent: 0, deduction_social_security: 0,
  deduction_tax: 0, deduction_other: 0, note: '',
})

const totalIncome = computed(() =>
  Number(form.value.base_salary) + Number(form.value.ot_pay) + Number(form.value.bonus) +
  Number(form.value.transport_allowance) + Number(form.value.meal_allowance) + Number(form.value.other_allowance)
)

const totalDeduction = computed(() =>
  Number(form.value.deduction_late) + Number(form.value.deduction_absent) +
  Number(form.value.deduction_social_security) + Number(form.value.deduction_tax) + Number(form.value.deduction_other)
)

const netSalary = computed(() => totalIncome.value - totalDeduction.value)

function formatMoney(v) {
  return new Intl.NumberFormat('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v || 0) + ' บาท'
}

const Field = (props) => {
  return h('div', { class: 'flex items-center justify-between gap-4' }, [
    h('label', { class: 'text-sm text-gray-600 shrink-0 w-32' }, props.label),
    h('input', {
      type: 'number',
      value: props.modelValue,
      onInput: (e) => props['onUpdate:modelValue'](e.target.value),
      class: 'w-36 border rounded-lg p-2 text-sm text-right',
      min: 0,
    }),
  ])
}

async function loadEmployees() {
  loading.value = true
  try {
    const res = await axios.get('/api/hr/payslips', {
      params: { month: selectedMonth.value, year: selectedYear.value }
    })
    if (res.data.success) {
      employees.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function openEdit(emp) {
  editEmployee.value = emp
  try {
    const res = await axios.get(`/api/hr/payslips/${emp.id}`, {
      params: { month: selectedMonth.value, year: selectedYear.value }
    })
    if (res.data.success) {
      const d = res.data.data
      form.value = {
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
    }
  } catch (e) {
    console.error(e)
  }
  showForm.value = true
}

async function savePayslip() {
  saving.value = true
  try {
    await axios.post(`/api/hr/payslips/${editEmployee.value.id}`, {
      ...form.value,
      month: selectedMonth.value,
      year: selectedYear.value,
    })
    showForm.value = false
    await loadEmployees()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

watch([selectedMonth, selectedYear], loadEmployees)
onMounted(loadEmployees)
</script>
