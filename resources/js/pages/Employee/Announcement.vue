<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">ประกาศ</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else-if="announcements.length === 0" class="text-center py-12">
        <p class="text-gray-400 text-sm">ไม่มีประกาศในขณะนี้</p>
      </div>

      <div v-else class="space-y-3">
        <div v-for="ann in announcements" :key="ann.id"
          :class="ann.priority === 'urgent' ? 'border-red-300 bg-red-50' : ann.priority === 'important' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white'"
          class="rounded-2xl p-5 border shadow-sm"
          @click="selected = selected === ann.id ? null : ann.id">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="ann.priority === 'urgent'" class="text-[10px] font-bold bg-red-500 text-white px-2 py-0.5 rounded-full">ด่วน</span>
                <span v-else-if="ann.priority === 'important'" class="text-[10px] font-bold bg-amber-500 text-white px-2 py-0.5 rounded-full">สำคัญ</span>
                <h3 class="font-bold text-gray-800 text-sm">{{ ann.title }}</h3>
              </div>
              <p class="text-gray-400 text-xs">{{ formatDate(ann.created_at) }}</p>
              <p class="text-gray-500 text-xs mt-1">{{ ann.body.substring(0, 120) }}{{ ann.body.length > 120 ? '...' : '' }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 shrink-0 mt-1 transition-transform" :class="{ 'rotate-180': selected === ann.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
          <div v-if="selected === ann.id" class="mt-3 pt-3 border-t border-gray-200">
            <p class="text-gray-700 text-sm whitespace-pre-line">{{ ann.body }}</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import dayjs from 'dayjs'
import 'dayjs/locale/th'

dayjs.locale('th')

const loading = ref(true)
const announcements = ref([])
const selected = ref(null)

function formatDate(d) {
  return dayjs(d).format('D MMMM YYYY HH:mm')
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/announcements')
    if (res.data.success) {
      announcements.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
