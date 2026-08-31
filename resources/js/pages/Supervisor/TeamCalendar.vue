<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const teamMembers = ref([])
const selectedDate = ref(new Date().toISOString().split('T')[0])

onMounted(async () => {
  await loadTeamCalendar()
})

const loadTeamCalendar = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/team-calendar', {
      params: { date: selectedDate.value }
    })
    teamMembers.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load team calendar:', error)
  } finally {
    loading.value = false
  }
}

const getStatusBadge = (status) => {
  const badges = {
    present: 'bg-green-100 text-green-800',
    absent: 'bg-red-100 text-red-800',
    late: 'bg-yellow-100 text-yellow-800',
    leave: 'bg-blue-100 text-blue-800',
    wfh: 'bg-purple-100 text-purple-800',
  }
  return badges[status] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6 p-4 sm:p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Team Calendar</h1>
          <p class="text-gray-500">ปฏิทินทีมงาน</p>
        </div>
        <input v-model="selectedDate" @change="loadTeamCalendar" type="date" class="px-3 py-2 border rounded-lg" />
      </div>

      <!-- Team Calendar -->
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">พนักงาน</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลาเข้า</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลาออก</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="member in teamMembers" :key="member.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ member.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['px-2 py-1 rounded-full text-xs', getStatusBadge(member.status)]">
                  {{ member.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ member.check_in || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ member.check_out || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
