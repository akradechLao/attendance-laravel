<template>
  <div v-if="time" class="leading-tight">
    <div class="text-[10px] text-gray-400">{{ date }}</div>
    <div class="text-sm font-semibold" :class="colorClass">{{ time }} น.</div>
  </div>
  <span v-else class="text-gray-300">-</span>
</template>

<script setup>
defineProps({
  date: { type: String, default: null },
  time: { type: String, default: null },
  colorClass: { type: String, default: 'text-navy' },
})
</script>
