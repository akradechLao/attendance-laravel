<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <button @click="$emit('prev')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 touch-target">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <p class="font-semibold text-gray-700 text-sm">{{ monthLabel }}</p>
      <button @click="$emit('next')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 touch-target">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-medium text-gray-400 mb-1">
      <div v-for="d in dayNames" :key="d">{{ d }}</div>
    </div>

    <div class="grid grid-cols-7 gap-1">
      <div v-for="(cell, i) in cells" :key="i" class="aspect-square">
        <slot name="cell" :cell="cell" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  year: { type: Number, required: true },
  month: { type: Number, required: true }, // 1-12
})
defineEmits(['prev', 'next'])

const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส']

const monthLabel = computed(() => `${thMonths[props.month - 1]} ${props.year + 543}`)

// วันในเดือนนี้ทั้งหมด แต่ละวันมี date (YYYY-MM-DD) ให้หน้าที่เรียกใช้เอาไปจับคู่กับข้อมูลของตัวเอง
// ช่องว่างก่อน/หลังเดือน (null) ไว้จัดตำแหน่งให้ตรงกับแถวเสาร์-อาทิตย์
const cells = computed(() => {
  const daysInMonth = new Date(props.year, props.month, 0).getDate()
  const startWeekday = new Date(props.year, props.month - 1, 1).getDay()
  const today = new Date()
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

  const arr = []
  for (let i = 0; i < startWeekday; i++) arr.push(null)
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${props.year}-${String(props.month).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    arr.push({ day: d, date: dateStr, isToday: dateStr === todayStr })
  }
  while (arr.length % 7 !== 0) arr.push(null)
  return arr
})
</script>
