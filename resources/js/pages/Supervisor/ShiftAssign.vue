<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">มอบหมายกะ工作</h1>
          <p class="text-gray-500">กำหนดกะ工作ให้ทีม (รอบ {{ cycleDisplay }})</p>
        </div>
        <button @click="fetchData" class="btn-secondary flex items-center gap-2">
          <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          รีเฟรช
        </button>
      </div>

      <!-- Cycle Info Banner -->
      <div class="card" :class="cycleInfo.can_assign ? 'border-l-4 border-green-500' : 'border-l-4 border-amber-500'">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="cycleInfo.can_assign ? 'bg-green-100' : 'bg-amber-100'">
            <svg class="w-5 h-5" :class="cycleInfo.can_assign ? 'text-green-600' : 'text-amber-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-gray-800">
              รอบกะ: {{ formatDateThai(cycleInfo.start) }} - {{ formatDateThai(cycleInfo.end) }}
            </p>
            <p class="text-sm" :class="cycleInfo.can_assign ? 'text-green-600' : 'text-amber-600'">
              {{ cycleInfo.message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">ทีมทั้งหมด</p>
          <p class="text-3xl font-bold text-gray-800">{{ stats.total }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">มอบหมายแล้ว</p>
          <p class="text-3xl font-bold text-green-600">{{ stats.assigned }}</p>
        </div>
        <div class="card text-center">
          <p class="text-xs text-gray-500 mb-1">ยังไม่มอบหมาย</p>
          <p class="text-3xl font-bold text-amber-600">{{ stats.pending }}</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <!-- Team List -->
      <div v-else class="space-y-3">
        <div v-for="emp in employees" :key="emp.id" class="card">
          <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <!-- Employee Info -->
            <div class="flex items-center gap-3 sm:w-64">
              <div class="w-10 h-10 rounded-full bg-navy/10 flex items-center justify-center text-navy font-bold text-sm flex-shrink-0">
                {{ emp.employee_code }}
              </div>
              <div class="min-w-0">
                <p class="font-bold text-gray-800 text-sm truncate">{{ emp.name }}</p>
                <p class="text-xs text-gray-500">{{ emp.division || emp.department || '-' }}</p>
              </div>
            </div>

            <!-- Current Assignment -->
            <div class="flex-1">
              <div v-if="emp.current_assignment" class="flex items-center gap-2">
                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">
                  {{ emp.current_assignment.shift_code }}
                </span>
                <span class="text-xs text-gray-500">มอบหมายแล้ว</span>
              </div>
              <div v-else class="text-xs text-amber-600 font-medium">ยังไม่มอบหมาย</div>
            </div>

            <!-- Action -->
            <div class="flex items-center gap-2">
              <select
                v-model="selectedShifts[emp.id]"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 min-w-[200px]"
              >
                <option value="">-- เลือกกะ --</option>
                <option v-for="shift in emp.available_shifts" :key="shift.work_shift_id" :value="shift.work_shift_id">
                  {{ shift.shift_code }} ({{ shift.start_time }}-{{ shift.end_time }})
                </option>
              </select>
              <button
                @click="assignShift(emp)"
                :disabled="!selectedShifts[emp.id] || assigning[emp.id]"
                class="btn-primary text-sm px-4 py-2"
              >
                <span v-if="assigning[emp.id]">กำลัง...</span>
                <span v-else>มอบหมาย</span>
              </button>
              <button
                v-if="emp.current_assignment"
                @click="removeShift(emp)"
                :disabled="removing[emp.id]"
                class="btn-danger text-sm px-3 py-2"
              >
                <span v-if="removing[emp.id]">...</span>
                <span v-else>ลบ</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && employees.length === 0" class="card text-center py-12">
        <p class="text-gray-400">ไม่มีพนักงานในทีม</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const loading = ref(true)
const employees = ref([])
const cycleInfo = ref({ start: '', end: '', can_assign: true, message: '' })
const stats = ref({ total: 0, assigned: 0, pending: 0 })
const selectedShifts = ref({})
const assigning = ref({})
const removing = ref({})

const cycleDisplay = computed(() => {
  if (!cycleInfo.value.start) return ''
  return `${formatDateThai(cycleInfo.value.start)} - ${formatDateThai(cycleInfo.value.end)}`
})

const formatDateThai = (dateStr) => {
  if (!dateStr) return ''
  const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']
  const d = new Date(dateStr + 'T00:00:00')
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear() + 543}`
}

const fetchData = async () => {
  loading.value = true
  try {
    const [teamRes, summaryRes] = await Promise.all([
      api.get('/api/supervisor/shift-assign/team'),
      api.get('/api/supervisor/shift-assign/summary'),
    ])

    if (teamRes.data.success) {
      employees.value = teamRes.data.data
      cycleInfo.value = teamRes.data.cycle
    }

    if (summaryRes.data.success) {
      stats.value = summaryRes.data.stats
    }
  } catch (err) {
    console.error('Failed to load team data:', err)
  } finally {
    loading.value = false
  }
}

const assignShift = async (emp) => {
  const workShiftId = selectedShifts.value[emp.id]
  if (!workShiftId) return

  assigning.value[emp.id] = true
  try {
    const res = await api.post('/api/supervisor/shift-assign', {
      employee_id: emp.id,
      work_shift_id: workShiftId,
    })
    if (res.data.success) {
      alert(res.data.message)
      await fetchData()
    }
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  } finally {
    assigning.value[emp.id] = false
  }
}

const removeShift = async (emp) => {
  if (!confirm(`ลบกะของ ${emp.name} ใช่ไหม?`)) return

  removing.value[emp.id] = true
  try {
    const res = await api.delete(`/api/supervisor/shift-assign/${emp.id}`)
    if (res.data.success) {
      alert(res.data.message)
      await fetchData()
    }
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  } finally {
    removing.value[emp.id] = false
  }
}

onMounted(fetchData)
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-secondary {
  @apply bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition;
}
.btn-danger {
  @apply bg-red-50 text-red-600 rounded-lg font-medium hover:bg-red-100 transition disabled:opacity-50;
}
</style>
