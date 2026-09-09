<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
        <router-link to="/employee/menu" class="text-blue-500 active:text-blue-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-lg font-bold text-gray-800">รายการรออนุมัติ</h1>
        <span v-if="counts.total > 0" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
          {{ counts.total }}
        </span>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
      <div v-if="loading" class="text-center py-10 text-gray-500">กำลังโหลด...</div>

      <template v-for="section in sections" :key="section.key">
        <div v-if="data[section.key]?.length > 0" class="bg-white rounded-xl shadow">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
              <span class="text-lg">{{ section.icon }}</span>
              <h2 class="font-semibold text-[#0f172a]">{{ section.label }}</h2>
              <span class="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">
                {{ counts[section.key] ?? data[section.key].length }}
              </span>
            </div>
          </div>

          <div class="divide-y divide-gray-50">
            <div v-for="item in data[section.key]" :key="item.id"
                 class="px-4 py-3 hover:bg-gray-50 transition-colors">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-sm text-[#0f172a]">{{ item.employee_name }}</span>
                    <span class="text-xs text-gray-400">{{ item.employee_code }}</span>
                    <span class="text-xs text-gray-400">{{ item.company }}</span>
                  </div>
                  <div class="text-sm text-gray-600">{{ item.detail }}</div>
                  <div v-if="item.reason" class="text-xs text-gray-400 mt-1">เหตุผล: {{ item.reason }}</div>
                  <div v-if="item.status_label" class="mt-1">
                    <span class="inline-block text-xs px-2 py-0.5 rounded-full"
                          :class="item.status === 'pending_manager' ? 'bg-yellow-100 text-yellow-700' : 'bg-orange-100 text-orange-700'">
                      {{ item.status_label }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <button @click="approve(item)"
                          :disabled="processing"
                          class="px-3 py-1.5 bg-green-500 text-white text-sm font-medium rounded-lg hover:bg-green-600 disabled:opacity-50">
                    อนุมัติ
                  </button>
                  <button v-if="item.reject_url"
                          @click="reject(item)"
                          :disabled="processing"
                          class="px-3 py-1.5 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 disabled:opacity-50">
                    ปฏิเสธ
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="hasMore[section.key]" class="px-4 py-3 border-t border-gray-100 text-center">
            <button @click="loadMore(section.key)"
                    :disabled="loadingMore === section.key"
                    class="text-sm font-medium text-blue-600 hover:text-blue-700 disabled:opacity-50">
              <span v-if="loadingMore === section.key">กำลังโหลด...</span>
              <span v-else>โหลดเพิ่มเติม (เหลืออีก {{ counts[section.key] - data[section.key].length }})</span>
            </button>
          </div>
        </div>
      </template>

      <div v-if="!loading && counts.total === 0" class="text-center py-10 text-gray-400">
        <div class="text-4xl mb-3">✅</div>
        <div>ไม่มีรายการค้างอนุมัติ</div>
      </div>
    </main>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 class="font-bold text-gray-800">ปฏิเสธคำขอ</h3>
        <div>
          <label class="block text-sm text-gray-600 mb-1">เหตุผลในการปฏิเสธ</label>
          <textarea v-model="rejectReason" rows="3" class="w-full border rounded-lg p-2 text-sm" placeholder="กรอกเหตุผล..." />
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showRejectModal = false" class="px-4 py-2 text-gray-600 text-sm">ยกเลิก</button>
          <button @click="confirmReject" :disabled="!rejectReason || processing"
                  class="px-4 py-2 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 disabled:opacity-50">
            ยืนยันปฏิเสธ
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast" class="fixed bottom-4 right-4 z-50">
      <div class="px-4 py-3 rounded-lg shadow-lg text-white text-sm font-medium"
           :class="toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'">
        {{ toast.message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../../services/api'

const loading = ref(true)
const processing = ref(false)
const data = reactive({
  leave: [], ot: [], wfh: [], remote: [],
  shift_swap: [], shift_request: [], forced_leave: [], estimated_checkout: [],
})
const counts = reactive({ total: 0, leave: 0, ot: 0, wfh: 0, remote: 0, shift_swap: 0, shift_request: 0, forced_leave: 0, estimated_checkout: 0 })

const sections = [
  { key: 'leave', label: 'ลางาน', icon: '📅' },
  { key: 'ot', label: 'OT', icon: '⏰' },
  { key: 'wfh', label: 'WFH', icon: '🏠' },
  { key: 'remote', label: 'ปฏิบัติงานนอกสถานที่', icon: '📍' },
  { key: 'shift_swap', label: 'สลับเวร', icon: '🔄' },
  { key: 'shift_request', label: 'ร้องขอเข้ากะ', icon: '📋' },
  { key: 'forced_leave', label: 'บังคับลา', icon: '⚠️' },
  { key: 'estimated_checkout', label: 'Checkout ประมาณการ', icon: '🕐' },
]

const showRejectModal = ref(false)
const rejectItem = ref(null)
const rejectReason = ref('')
const toast = ref(null)

// The API returns the first page of every section plus the true totals,
// so `counts` stays accurate even when only some rows are loaded.
const hasMore = reactive({})
const pageOf = reactive({})
const loadingMore = ref(null)

const loadData = async () => {
  loading.value = true
  try {
    const res = await api.get('/api/pending-approvals')
    const d = res.data.data
    for (const key of Object.keys(data)) {
      data[key] = d[key] || []
      pageOf[key] = 1
      hasMore[key] = d.pagination?.has_more?.[key] ?? false
    }
    Object.assign(counts, d.counts)
  } catch (err) {
    console.error(err)
  }
  loading.value = false
}

const loadMore = async (key) => {
  loadingMore.value = key
  try {
    const next = (pageOf[key] || 1) + 1
    const res = await api.get('/api/pending-approvals', { params: { type: key, page: next } })
    const d = res.data.data
    data[key] = [...data[key], ...(d[key] || [])]
    pageOf[key] = next
    hasMore[key] = d.pagination?.has_more?.[key] ?? false
    Object.assign(counts, d.counts)
  } catch (err) {
    console.error(err)
  }
  loadingMore.value = null
}

const approve = async (item) => {
  if (!confirm(`อนุมัติคำขอของ ${item.employee_name}?`)) return
  processing.value = true
  try {
    await api.put(item.approve_url)
    showToast('success', 'อนุมัติสำเร็จ')
    await loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  processing.value = false
}

const reject = (item) => {
  rejectItem.value = item
  rejectReason.value = ''
  showRejectModal.value = true
}

const confirmReject = async () => {
  if (!rejectItem.value || !rejectReason.value) return
  processing.value = true
  try {
    // Each approval type stores the reason under a different column
    // (rejection_reason / supervisor_note); the API tells us which one.
    const field = rejectItem.value.reject_field
    const payload = field ? { [field]: rejectReason.value } : {}
    await api.put(rejectItem.value.reject_url, payload)
    showToast('success', 'ปฏิเสธคำขอแล้ว')
    showRejectModal.value = false
    await loadData()
  } catch (err) {
    showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
  processing.value = false
}

const showToast = (type, message) => {
  toast.value = { type, message }
  setTimeout(() => toast.value = null, 3000)
}

onMounted(loadData)
</script>
