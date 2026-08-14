<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">ยืนยันข้อมูลเข้างาน</h1>
          <p class="text-gray-500">ตรวจสอบและยืนยันข้อมูลเข้างานก่อนส่งรายงาน</p>
        </div>
        <button
          v-if="stats.unverified > 0"
          @click="verifyAll"
          class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 flex items-center gap-2"
        >
          <span>✅</span> ยืนยันทั้งหมด ({{ stats.unverified }})
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
            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะตรวจสอบ</label>
            <select v-model="selectedVerified" class="input-field" @change="loadData">
              <option value="">ทั้งหมด</option>
              <option value="0">รอตรวจสอบ</option>
              <option value="1">ตรวจสอบแล้ว</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">รายการทั้งหมด</p>
          <p class="text-2xl font-bold text-navy">{{ stats.total }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">รอตรวจสอบ</p>
          <p class="text-2xl font-bold" :class="stats.unverified > 0 ? 'text-orange-600' : 'text-gray-400'">{{ stats.unverified }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">ตรวจสอบแล้ว</p>
          <p class="text-2xl font-bold text-green-600">{{ stats.verified }}</p>
        </div>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <div v-if="records.length === 0" class="card text-center py-8 text-gray-400">ไม่มีรายการเข้างานวันนี้</div>
        <div v-else class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">กะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาเข้า</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">เวลาออก</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สถานะ</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">สาย (น.)</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ประเภท</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">ตรวจสอบ</th>
                  <th class="text-center px-4 py-3 text-xs font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50" :class="!record.is_verified ? 'bg-orange-50/30' : ''">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                        <span class="text-blue-600 text-xs font-semibold">{{ record.employee_name?.charAt(0) }}</span>
                      </div>
                      <span class="font-medium text-navy text-sm">{{ record.employee_name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.employee_code }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.company_name }}</td>
                  <td class="px-4 py-3 text-xs text-gray-500">{{ record.shift_time }}</td>
                  <td class="px-4 py-3 text-sm font-medium" :class="record.original_status === 'late' ? 'text-yellow-600' : 'text-green-600'">{{ record.check_in || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ record.check_out || '-' }}</td>
                  <td class="px-4 py-3">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      record.final_status === 'late' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'
                    ]">
                      {{ record.final_status === 'late' ? 'สาย' : 'ปกติ' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm" :class="record.late_minutes > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">
                    {{ record.late_minutes || '-' }}
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="record.scan_type === 'remote_scan'" class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">นอกสถานที่</span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">ออฟฟิศ</span>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="record.is_verified" class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                      ✅ {{ record.verified_by }}
                    </span>
                    <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">⏳ รอตรวจสอบ</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      v-if="!record.is_verified"
                      @click="verifySingle(record)"
                      class="px-3 py-1 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100"
                    >
                      ยืนยัน
                    </button>
                    <button
                      v-else
                      @click="unverifySingle(record)"
                      class="px-3 py-1 text-xs font-medium bg-gray-50 text-gray-500 rounded-lg hover:bg-gray-100"
                    >
                      ยกเลิก
                    </button>
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

const loading = ref(true)
const records = ref([])
const companies = ref([])
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedCompany = ref('')
const selectedVerified = ref('')
const stats = ref({ total: 0, verified: 0, unverified: 0 })

async function loadData() {
  loading.value = true
  try {
    const params = { date: selectedDate.value, company_id: selectedCompany.value, verified: selectedVerified.value }
    const [res, companiesRes] = await Promise.all([
      api.get('/api/attendance-verification', { params }),
      api.get('/api/companies'),
    ])
    records.value = res.data?.data?.records || []
    stats.value = res.data?.data?.stats || { total: 0, verified: 0, unverified: 0 }
    companies.value = companiesRes.data?.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function verifySingle(record) {
  try {
    await api.put(`/api/attendance-verification/${record.id}/verify`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function unverifySingle(record) {
  try {
    await api.put(`/api/attendance-verification/${record.id}/unverify`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function verifyAll() {
  if (!confirm(`ยืนยันข้อมูลเข้างานทั้งหมด ${stats.value.unverified} รายการ ใช่หรือไม่?`)) return
  try {
    await api.post('/api/attendance-verification/verify-all', {
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
