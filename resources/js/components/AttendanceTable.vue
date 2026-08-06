<template>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="bg-gray-50">
          <th
            v-for="column in columns"
            :key="column.key"
            @click="column.sortable && toggleSort(column.key)"
            :class="[
              'text-left px-6 py-3 text-sm font-semibold text-gray-600',
              column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : ''
            ]"
          >
            <div class="flex items-center gap-2">
              {{ column.label }}
              <svg
                v-if="column.sortable && sortKey === column.key"
                class="w-4 h-4"
                :class="sortOrder === 'asc' ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <tr
          v-for="(record, index) in sortedAttendances"
          :key="index"
          class="hover:bg-gray-50"
        >
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-blue-600 text-sm font-semibold">{{ record.employee?.name?.charAt(0) }}</span>
              </div>
              <span class="font-medium text-navy">{{ record.employee?.name }}</span>
            </div>
          </td>
          <td class="px-6 py-4 text-gray-600">{{ record.employee?.code }}</td>
          <td class="px-6 py-4">
            <span
              :class="[
                'font-medium',
                record.is_late ? 'text-yellow-600' : 'text-green-600'
              ]"
            >
              {{ formatTime(record.check_in) }}
            </span>
          </td>
          <td class="px-6 py-4 text-gray-600">
            {{ record.check_out ? formatTime(record.check_out) : '-' }}
          </td>
          <td class="px-6 py-4">
            <span
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium',
                record.is_late
                  ? 'bg-red-100 text-red-700'
                  : 'bg-green-100 text-green-700'
              ]"
            >
              {{ record.is_late ? 'สาย' : 'ปกติ' }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="attendances.length === 0" class="text-center py-8 text-gray-500">
      ไม่มีข้อมูล
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  attendances: {
    type: Array,
    default: () => []
  }
})

const sortKey = ref('')
const sortOrder = ref('asc')

const columns = [
  { key: 'name', label: 'ชื่อ', sortable: true },
  { key: 'code', label: 'รหัส', sortable: true },
  { key: 'check_in', label: 'เวลาเข้า', sortable: true },
  { key: 'check_out', label: 'เวลาออก', sortable: true },
  { key: 'status', label: 'สถานะ', sortable: true }
]

const sortedAttendances = computed(() => {
  if (!sortKey.value) return props.attendances

  return [...props.attendances].sort((a, b) => {
    let valA, valB

    switch (sortKey.value) {
      case 'name':
        valA = a.employee?.name || ''
        valB = b.employee?.name || ''
        break
      case 'code':
        valA = a.employee?.code || ''
        valB = b.employee?.code || ''
        break
      case 'check_in':
        valA = a.check_in || ''
        valB = b.check_in || ''
        break
      case 'check_out':
        valA = a.check_out || ''
        valB = b.check_out || ''
        break
      case 'status':
        valA = a.is_late ? 1 : 0
        valB = b.is_late ? 1 : 0
        break
      default:
        return 0
    }

    if (typeof valA === 'string') {
      const comparison = valA.localeCompare(valB)
      return sortOrder.value === 'asc' ? comparison : -comparison
    }

    return sortOrder.value === 'asc' ? valA - valB : valB - valA
  })
})

function toggleSort(key) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

function formatTime(timeStr) {
  if (!timeStr) return '-'
  return new Date(timeStr).toLocaleTimeString('th-TH', {
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
