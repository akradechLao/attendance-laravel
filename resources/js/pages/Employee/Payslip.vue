<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/dashboard" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">สลิปเงินเดือน</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <!-- Month Selector -->
      <div class="flex items-center justify-center gap-4 mb-6">
        <button @click="prevMonth" class="p-2 hover:bg-gray-100 rounded-lg">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div class="text-center">
          <p class="font-bold text-gray-800">{{ thMonths[month - 1] }} {{ year + 543 }}</p>
        </div>
        <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-lg">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else class="space-y-4">
        <!-- No Data -->
        <div v-if="!payslip.exists" class="bg-white rounded-2xl p-8 border border-gray-200 shadow-sm text-center">
          <div class="w-16 h-16 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
            </svg>
          </div>
          <p class="text-gray-400 text-sm">ยังไม่มีข้อมูลสลิปเงินเดือนเดือนนี้</p>
          <p class="text-gray-300 text-xs mt-1">รอฝ่ายบุคคลกรอกข้อมูล</p>
        </div>

        <!-- Payslip Card -->
        <div v-else>
          <!-- Header -->
          <div class="bg-gradient-to-r from-blue-600 to-blue-500 rounded-2xl p-6 text-white text-center mb-4">
            <p class="text-sm opacity-80">สลิปเงินเดือน</p>
            <p class="text-2xl font-bold mt-1">{{ thMonths[month - 1] }} {{ year + 543 }}</p>
          </div>

          <!-- Net Salary -->
          <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm text-center mb-4">
            <p class="text-gray-400 text-sm">เงินสุทธิ</p>
            <p class="text-4xl font-bold text-blue-600 mt-1">{{ formatMoney(payslip.net_salary) }}</p>
          </div>

          <!-- Income -->
          <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
            <h3 class="text-sm font-bold text-emerald-600 uppercase tracking-wide mb-3">รายรับ</h3>
            <div class="space-y-2">
              <Row label="เงินเดือนพื้นฐาน" :value="payslip.base_salary" />
              <Row label="ค่าโอที" :value="payslip.ot_pay" />
              <Row label="โบนัส" :value="payslip.bonus" />
              <Row label="ค่าเดินทาง" :value="payslip.transport_allowance" />
              <Row label="ค่าอาหาร" :value="payslip.meal_allowance" />
              <Row label="เบี้ยเลี้ยงอื่นๆ" :value="payslip.other_allowance" />
              <div class="border-t border-gray-100 pt-2 mt-2">
                <Row label="รวมรายรับ" :value="payslip.total_allowances + payslip.base_salary" bold />
              </div>
            </div>
          </div>

          <!-- Deductions -->
          <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
            <h3 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">รายการหัก</h3>
            <div class="space-y-2">
              <Row label="หักสาย" :value="payslip.deduction_late" />
              <Row label="หักขาดงาน" :value="payslip.deduction_absent" />
              <Row label="ประกันสังคม" :value="payslip.deduction_social_security" />
              <Row label="ภาษี ณ ที่จ่าย" :value="payslip.deduction_tax" />
              <Row label="หักอื่นๆ" :value="payslip.deduction_other" />
              <div class="border-t border-gray-100 pt-2 mt-2">
                <Row label="รวมรายการหัก" :value="payslip.total_deductions" bold />
              </div>
            </div>
          </div>

          <!-- Note -->
          <div v-if="payslip.note" class="bg-amber-50 rounded-2xl p-4 border border-amber-200">
            <p class="text-amber-700 text-xs font-medium">หมายเหตุ</p>
            <p class="text-amber-600 text-sm mt-1">{{ payslip.note }}</p>
          </div>
        </div>

        <!-- History -->
        <div v-if="history.length > 0" class="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
          <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-3">ประวัติสลิป</h3>
          <div class="space-y-2">
            <div v-for="h in history" :key="`${h.year}-${h.month}`"
              @click="month = h.month; year = h.year; loadPayslip()"
              class="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
              <div>
                <p class="font-medium text-gray-800 text-sm">{{ thMonths[h.month - 1] }} {{ h.year + 543 }}</p>
                <p class="text-gray-400 text-[10px]">รายรับ {{ formatMoney(h.total_allowances + h.base_salary) }}</p>
              </div>
              <p class="font-bold text-blue-600 text-sm">{{ formatMoney(h.net_salary) }}</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, h, watch, onMounted } from 'vue'
import axios from 'axios'

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

const loading = ref(true)
const month = ref(new Date().getMonth() + 1)
const year = ref(new Date().getFullYear())
const payslip = ref({ exists: false, base_salary: 0, net_salary: 0, total_allowances: 0, total_deductions: 0 })
const history = ref([])

function formatMoney(v) {
  return new Intl.NumberFormat('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v || 0) + ' บาท'
}

function prevMonth() {
  if (month.value === 1) { month.value = 12; year.value-- }
  else month.value--
  loadPayslip()
}

function nextMonth() {
  if (month.value === 12) { month.value = 1; year.value++ }
  else month.value++
  loadPayslip()
}

const Row = (props) => {
  return h('div', { class: 'flex items-center justify-between' }, [
    h('span', { class: `text-sm ${props.bold ? 'font-bold text-gray-800' : 'text-gray-600'}` }, props.label),
    h('span', { class: `text-sm ${props.bold ? 'font-bold text-gray-800' : 'text-gray-800'}` }, formatMoney(props.value)),
  ])
}

async function loadPayslip() {
  loading.value = true
  try {
    const [payRes, histRes] = await Promise.allSettled([
      axios.get('/api/employee/payslip', { params: { month: month.value, year: year.value } }),
      axios.get('/api/employee/payslip/history', { params: { year: year.value } }),
    ])
    if (payRes.status === 'fulfilled' && payRes.value.data.success) {
      payslip.value = payRes.value.data.data
    }
    if (histRes.status === 'fulfilled' && histRes.value.data.success) {
      history.value = histRes.value.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadPayslip)
</script>
