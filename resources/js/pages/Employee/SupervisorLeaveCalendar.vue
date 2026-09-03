<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">สรุปการลาของทีม</h1>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <div v-else class="space-y-6">
        <!-- Today -->
        <Section
          title="วันนี้"
          :subtitle="formatDate(today)"
          :items="data.today"
          color="red"
          empty="ไม่มีใครลาวันนี้"
          icon="today"
        />

        <!-- Tomorrow -->
        <Section
          title="พรุ่งนี้"
          :subtitle="formatDate(tomorrow)"
          :items="data.tomorrow"
          color="amber"
          empty="ไม่มีใครลาพรุ่งนี้"
          icon="tomorrow"
        />

        <!-- This Week -->
        <Section
          title="สัปดาห์นี้"
          :subtitle="weekRange"
          :items="data.this_week"
          color="blue"
          empty="ไม่มีใครลาสัปดาห์นี้"
          icon="week"
        />

        <!-- Upcoming -->
        <Section
          title="ล่วงหน้า"
          :subtitle="`หลัง ${formatDate(weekEnd)}`"
          :items="data.upcoming"
          color="purple"
          empty="ไม่มีใครลาล่วงหน้า"
          icon="upcoming"
        />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, h, onMounted } from 'vue'
import api from '../../services/api'

const thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']

const loading = ref(true)
const data = ref({ today: [], tomorrow: [], this_week: [], upcoming: [] })

const today = new Date()
const tomorrow = new Date(today); tomorrow.setDate(tomorrow.getDate() + 1)
const weekEnd = new Date(today); weekEnd.setDate(weekEnd.getDate() + 7)

function formatDate(d) {
  return `${d.getDate()} ${thMonths[d.getMonth()]} ${d.getFullYear() + 543}`
}

const weekRange = computed(() => {
  const s = new Date(today); s.setDate(s.getDate() + 1)
  const e = new Date(weekEnd)
  return `${s.getDate()} ${thMonths[s.getMonth()]} - ${e.getDate()} ${thMonths[e.getMonth()]}`
})

// Inline Section component
const Section = (props) => {
  const colorMap = {
    red: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', dot: 'bg-red-500' },
    amber: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', dot: 'bg-amber-500' },
    blue: { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700', dot: 'bg-blue-500' },
    purple: { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-700', dot: 'bg-purple-500' },
  }
  const c = colorMap[props.color] || colorMap.blue

  if (props.items.length === 0) {
    return h('div', { class: `${c.bg} rounded-2xl p-5 border ${c.border}` }, [
      h('div', { class: 'flex items-center gap-2 mb-1' }, [
        h('div', { class: `w-2 h-2 rounded-full ${c.dot}` }),
        h('h2', { class: `font-bold ${c.text} text-sm` }, props.title),
        h('span', { class: 'text-gray-400 text-xs ml-2' }, props.subtitle),
      ]),
      h('p', { class: 'text-gray-400 text-sm mt-3 text-center' }, props.empty),
    ])
  }

  return h('div', { class: `${c.bg} rounded-2xl p-5 border ${c.border}` }, [
    h('div', { class: 'flex items-center gap-2 mb-3' }, [
      h('div', { class: `w-2 h-2 rounded-full ${c.dot}` }),
      h('h2', { class: `font-bold ${c.text} text-sm` }, props.title),
      h('span', { class: 'text-gray-400 text-xs ml-2' }, props.subtitle),
      h('span', { class: `ml-auto text-[10px] font-bold ${c.text} bg-white px-2 py-0.5 rounded-full` }, `${props.items.length} คน`),
    ]),
    h('div', { class: 'space-y-2' },
      props.items.map(item =>
        h('div', { class: 'bg-white rounded-xl p-3 border border-gray-100 flex items-center justify-between' }, [
          h('div', {}, [
            h('p', { class: 'font-medium text-gray-800 text-sm' }, item.employee?.name || '-'),
            h('p', { class: 'text-gray-400 text-[10px]' }, `${item.employee?.department || ''} • ${item.leave_type}`),
            h('p', { class: 'text-gray-400 text-[10px]' }, `${formatDateShort(item.start_date)} - ${formatDateShort(item.end_date)} (${item.total_days} วัน)`),
            item.reason ? h('p', { class: 'text-gray-400 text-[10px] italic mt-0.5' }, `เหตุผล: ${item.reason}`) : null,
          ]),
          h('span', { class: 'text-gray-300 text-xs shrink-0' }, `${item.total_days}วัน`),
        ])
      )
    ),
  ])
}

function formatDateShort(d) {
  const dt = new Date(d)
  return `${dt.getDate()}${thMonths[dt.getMonth()]}`
}

onMounted(async () => {
  try {
    const res = await api.get('/api/supervisor/leave-calendar')
    if (res.data.success) {
      data.value = res.data.data
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
