<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const importing = ref(false)
const holidays = ref([])
const showForm = ref(false)
const editingId = ref(null)
const selectedYear = ref(new Date().getFullYear())
const importYear = ref(new Date().getFullYear())
const newHoliday = ref({
  name: '',
  date: '',
  type: 'company',
})

const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']

const typeOptions = [
  { value: 'government', label: 'วันหยุดราชการ', color: 'bg-blue-100 text-blue-700' },
  { value: 'company', label: 'วันหยุดบริษัท', color: 'bg-emerald-100 text-emerald-700' },
  { value: 'special', label: 'วันหยุดพิเศษ', color: 'bg-amber-100 text-amber-700' },
]

const formatDate = (d) => {
  if (!d) return ''
  // Handle ISO datetime strings like "2025-12-31T17:00:00.000000Z"
  const datePart = String(d).split('T')[0]
  const parts = datePart.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return d
}

function parseDate(d) {
  if (!d) return new Date()
  const datePart = String(d).split('T')[0]
  const dt = new Date(datePart + 'T12:00:00')
  return isNaN(dt.getTime()) ? new Date() : dt
}

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1, y + 2]
})

onMounted(async () => {
  await loadHolidays()
})

const loadHolidays = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/holidays', {
      params: { year: selectedYear.value }
    })
    holidays.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load holidays:', error)
  } finally {
    loading.value = false
  }
}

const openAdd = () => {
  editingId.value = null
  newHoliday.value = { name: '', date: '', type: 'company' }
  showForm.value = true
}

const openEdit = (holiday) => {
  editingId.value = holiday.id
  newHoliday.value = { name: holiday.name, date: holiday.date, type: holiday.type || 'company' }
  showForm.value = true
}

const saveHoliday = async () => {
  if (!newHoliday.value.name || !newHoliday.value.date) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }

  try {
    if (editingId.value) {
      await api.put(`/api/holidays/${editingId.value}`, newHoliday.value)
      alert('แก้ไขวันหยุดสำเร็จ')
    } else {
      await api.post('/api/holidays', newHoliday.value)
      alert('เพิ่มวันหยุดสำเร็จ')
    }
    showForm.value = false
    newHoliday.value = { name: '', date: '', type: 'company' }
    await loadHolidays()
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการบันทึกวันหยุด')
  }
}

const importHolidays = async () => {
  if (!confirm(`ดึงวันหยุดราชการปี ${importYear.value} มาใส่ตาราง? (รายการซ้ำวันที่จะอัปเดตชื่อใหม่)`)) return
  importing.value = true
  try {
    const res = await api.post('/api/holidays/import-official', { year: importYear.value })
    alert(res.data?.message || 'นำเข้าวันหยุดราชการเรียบร้อย')
    selectedYear.value = importYear.value
    await loadHolidays()
  } catch (error) {
    alert(error.response?.data?.message || 'เกิดข้อผิดพลาดในการนำเข้าวันหยุดราชการ')
  } finally {
    importing.value = false
  }
}

const deleteHoliday = async (id) => {
  if (confirm('คุณต้องการลบวันหยุดนี้ใช่หรือไม่?')) {
    try {
      await api.delete(`/api/holidays/${id}`)
      alert('ลบวันหยุดสำเร็จ')
      await loadHolidays()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการลบวันหยุด')
    }
  }
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6 p-4 sm:p-6">
      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">จัดการวันหยุด</h1>
          <p class="text-gray-500">จัดการวันหยุดราชการและวันหยุดบริษัท</p>
          <div class="flex items-center gap-3 mt-2 text-[10px]">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> ราชการ</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> บริษัท</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> พิเศษ</span>
          </div>
        </div>
        <div class="flex gap-3 items-center flex-wrap">
        <div class="flex gap-2 items-center">
          <select v-model="importYear" class="px-3 py-2 border rounded-lg">
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
          <button @click="importHolidays" :disabled="importing"
            class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50">
            {{ importing ? 'กำลังนำเข้า...' : 'นำเข้าวันหยุดราชการ' }}
          </button>
        </div>
        <select v-model="selectedYear" @change="loadHolidays" class="px-3 py-2 border rounded-lg">
          <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
        </select>
        <button @click="openAdd" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          + เพิ่มวันหยุด
        </button>
      </div>
    </div>

    <!-- Holiday List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เดือน</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อวันหยุด</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">กำลังโหลด...</td>
          </tr>
          <tr v-else-if="holidays.length === 0">
            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">ไม่พบวันหยุดในปีที่เลือก</td>
          </tr>
          <tr v-for="holiday in holidays" :key="holiday.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(holiday.date) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ months[parseDate(holiday.date).getMonth()] }}</td>
            <td class="px-6 py-4 text-sm text-gray-900">
              <div class="flex items-center gap-2">
                {{ holiday.name }}
                <span :class="{
                  'bg-blue-100 text-blue-700': holiday.type === 'government',
                  'bg-emerald-100 text-emerald-700': holiday.type === 'company' || !holiday.type,
                  'bg-amber-100 text-amber-700': holiday.type === 'special',
                }" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                  {{ { government: 'ราชการ', company: 'บริษัท', special: 'พิเศษ' }[holiday.type] || 'บริษัท' }}
                </span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap space-x-3">
              <button @click="openEdit(holiday)" class="text-blue-600 hover:text-blue-800">แก้ไข</button>
              <button @click="deleteHoliday(holiday.id)" class="text-red-600 hover:text-red-800">ลบ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit Holiday Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">{{ editingId ? 'แก้ไขวันหยุด' : 'เพิ่มวันหยุด' }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">ชื่อวันหยุด</label>
            <input v-model="newHoliday.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="เช่น วันขึ้นปีใหม่" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">ประเภท</label>
            <select v-model="newHoliday.type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">วันที่</label>
            <input v-model="newHoliday.date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
            <button @click="saveHoliday" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">บันทึก</button>
          </div>
        </div>
      </div>
    </div>
    </div>
  </AppLayout>
</template>
