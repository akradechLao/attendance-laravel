<template>
  <div class="p-4 space-y-6">
    <h1 class="text-2xl font-bold text-[#0f172a]">สถิติของฉัน</h1>

    <div v-if="loading" class="text-center py-8 text-gray-500">กำลังโหลด...</div>

    <template v-else-if="stats">
      <!-- Employee Info -->
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-3">
          <span class="text-2xl font-bold text-blue-600">{{ stats.employee.name?.charAt(0) }}</span>
        </div>
        <div class="font-bold text-[#0f172a]">{{ stats.employee.name }}</div>
        <div class="text-sm text-gray-500">{{ stats.employee.employee_code }} | {{ stats.employee.position || stats.employee.department || '-' }}</div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-blue-50 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-blue-600">{{ stats.summary.month_total }}</div>
          <div class="text-xs text-blue-500">วันรวม (เดือนนี้)</div>
        </div>
        <div class="bg-green-50 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-green-600">{{ stats.summary.on_time_days }}</div>
          <div class="text-xs text-green-500">ตรงเวลา</div>
        </div>
        <div class="bg-red-50 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-red-600">{{ stats.summary.late_days }}</div>
          <div class="text-xs text-red-500">สาย</div>
        </div>
        <div class="bg-orange-50 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-orange-600">{{ stats.summary.year_total }}</div>
          <div class="text-xs text-orange-500">วันรวม (ปีนี้)</div>
        </div>
      </div>

      <!-- Recent Records -->
      <div class="bg-white rounded-xl shadow p-4">
        <h2 class="font-semibold text-[#0f172a] mb-3">ประวัติ 14 วันล่าสุด</h2>
        <div v-if="stats.recent_records.length === 0" class="text-center py-4 text-gray-500">ยังไม่มีประวัติ</div>
        <div v-else class="space-y-2">
          <div v-for="(r, i) in stats.recent_records" :key="i"
               class="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm">
            <span class="text-gray-600">{{ formatDate(r.date) }}</span>
            <div class="flex items-center gap-3">
              <span class="text-gray-600">{{ r.check_in || '-' }}</span>
              <span class="text-gray-300">→</span>
              <span class="text-gray-600">{{ r.check_out || '-' }}</span>
              <span class="font-semibold"
                    :class="r.status === 'late' ? 'text-red-600' : r.status === 'on_time' ? 'text-green-600' : 'text-gray-400'">
                {{ r.status === 'late' ? 'สาย' : r.status === 'on_time' ? '✓' : '-' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const loading = ref(true)
const stats = ref(null)
const employeeId = computed(() => state.user?.id)

const loadStats = async () => {
  try {
    const res = await api.get('/api/employee-stats', { params: { emp_id: employeeId.value } })
    stats.value = res.data.data
  } catch (err) { console.error(err) }
  loading.value = false
}

const formatDate = (d) => new Date(d).toLocaleDateString('th-TH', { month: 'short', day: 'numeric' })

onMounted(loadStats)
</script>
