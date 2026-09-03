<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(false)
const announcements = ref([])
const companies = ref([])
const showForm = ref(false)
const editingId = ref(null)
const currentPage = ref(1)
const lastPage = ref(1)

const form = ref({
  title: '',
  body: '',
  priority: 'normal',
  company_id: '',
  published_at: '',
  expires_at: '',
  is_active: true,
})

const priorities = [
  { value: 'normal', label: 'ปกติ', color: 'bg-blue-100 text-blue-800' },
  { value: 'important', label: 'สำคัญ', color: 'bg-amber-100 text-amber-800' },
  { value: 'urgent', label: 'ด่วน', color: 'bg-red-100 text-red-800' },
]

const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม']

function formatDate(d) {
  if (!d) return '-'
  const dt = new Date(d)
  return `${dt.getDate()} ${thMonths[dt.getMonth()]} ${dt.getFullYear() + 543} ${String(dt.getHours()).padStart(2,'0')}:${String(dt.getMinutes()).padStart(2,'0')}`
}

onMounted(async () => {
  await Promise.all([loadAnnouncements(), loadCompanies()])
})

async function loadAnnouncements(page = 1) {
  loading.value = true
  try {
    const response = await api.get('/api/announcements/admin', { params: { page } })
    announcements.value = response.data.data?.data || response.data.data || []
    currentPage.value = response.data.data?.current_page || 1
    lastPage.value = response.data.data?.last_page || 1
  } catch (error) {
    console.error('Failed to load announcements:', error)
  } finally {
    loading.value = false
  }
}

async function loadCompanies() {
  try {
    const response = await api.get('/api/companies')
    companies.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load companies:', error)
  }
}

function openForm(ann = null) {
  if (ann) {
    editingId.value = ann.id
    form.value = {
      title: ann.title,
      body: ann.body,
      priority: ann.priority,
      company_id: ann.company_id || '',
      published_at: ann.published_at ? ann.published_at.replace(' ', 'T').slice(0, 16) : '',
      expires_at: ann.expires_at ? ann.expires_at.replace(' ', 'T').slice(0, 16) : '',
      is_active: ann.is_active,
    }
  } else {
    editingId.value = null
    form.value = { title: '', body: '', priority: 'normal', company_id: '', published_at: '', expires_at: '', is_active: true }
  }
  showForm.value = true
}

function saveAnnouncement() {
  if (!form.value.title || !form.value.body) {
    alert('กรุณากรอกชื่อและเนื้อหาประกาศ')
    return
  }

  const payload = { ...form.value }
  if (payload.published_at) payload.published_at = payload.published_at.replace('T', ' ') + ':00'
  if (payload.expires_at) payload.expires_at = payload.expires_at.replace('T', ' ') + ':00'
  if (!payload.company_id) delete payload.company_id
  if (!payload.published_at) delete payload.published_at
  if (!payload.expires_at) delete payload.expires_at

  const request = editingId.value
    ? api.put(`/api/announcements/${editingId.value}`, payload)
    : api.post('/api/announcements', payload)

  request.then(() => {
    alert(editingId.value ? 'แก้ไขประกาศสำเร็จ' : 'สร้างประกาศสำเร็จ')
    showForm.value = false
    loadAnnouncements(currentPage.value)
  }).catch(err => {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  })
}

function deleteAnnouncement(ann) {
  if (!confirm(`ต้องการลบประกาศ "${ann.title}" ใช่หรือไม่?`)) return
  api.delete(`/api/announcements/${ann.id}`).then(() => {
    alert('ลบประกาศเรียบร้อย')
    loadAnnouncements(currentPage.value)
  }).catch(err => {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  })
}

function toggleActive(ann) {
  api.put(`/api/announcements/${ann.id}`, { is_active: !ann.is_active }).then(() => {
    loadAnnouncements(currentPage.value)
  })
}

function getPriorityInfo(p) {
  return priorities.find(pr => pr.value === p) || priorities[0]
}
</script>

<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-navy">จัดการประกาศ</h1>
          <p class="text-gray-500">สร้าง แก้ไข ลบประกาศให้พนักงาน</p>
        </div>
        <button @click="openForm()" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-slate-800 text-sm font-medium">
          + สร้างประกาศ
        </button>
      </div>

      <div v-if="loading" class="text-center py-8 text-gray-500">กำลังโหลด...</div>

      <div v-else-if="announcements.length === 0" class="text-center py-12 text-gray-400">
        ไม่มีประกาศ
      </div>

      <div v-else class="space-y-3">
        <div v-for="ann in announcements" :key="ann.id"
          class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span :class="getPriorityInfo(ann.priority).color" class="text-[10px] font-bold px-2 py-0.5 rounded-full">
                    {{ getPriorityInfo(ann.priority).label }}
                  </span>
                  <span v-if="!ann.is_active" class="text-[10px] font-bold bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full">ปิดใช้งาน</span>
                  <h3 class="font-bold text-navy text-sm">{{ ann.title }}</h3>
                </div>
                <p class="text-gray-500 text-xs mt-1 line-clamp-2">{{ ann.body }}</p>
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                  <span>สร้าง: {{ formatDate(ann.created_at) }}</span>
                  <span v-if="ann.published_at">เริ่ม: {{ formatDate(ann.published_at) }}</span>
                  <span v-if="ann.expires_at">สิ้นสุด: {{ formatDate(ann.expires_at) }}</span>
                  <span v-if="ann.creator">โดย: {{ ann.creator.username }}</span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button @click="toggleActive(ann)" :class="ann.is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600'" class="p-1.5 rounded-lg hover:bg-gray-50 text-xs" :title="ann.is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน'">
                  {{ ann.is_active ? '✓' : '○' }}
                </button>
                <button @click="openForm(ann)" class="p-1.5 text-blue-500 hover:text-blue-700 rounded-lg hover:bg-blue-50 text-xs">แก้ไข</button>
                <button @click="deleteAnnouncement(ann)" class="p-1.5 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50 text-xs">ลบ</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="flex justify-center gap-2">
        <button v-for="p in lastPage" :key="p" @click="loadAnnouncements(p)"
          :class="p === currentPage ? 'bg-navy text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
          class="px-3 py-1.5 rounded-lg text-sm border">{{ p }}</button>
      </div>

      <!-- Form Modal -->
      <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-bold text-navy mb-4">{{ editingId ? 'แก้ไขประกาศ' : 'สร้างประกาศ' }}</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อประกาศ *</label>
              <input v-model="form.title" type="text" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="หัวข้อประกาศ" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เนื้อหาประกาศ *</label>
              <textarea v-model="form.body" rows="5" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="รายละเอียดประกาศ..." required></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ระดับความสำคัญ</label>
                <select v-model="form.priority" class="w-full px-3 py-2 border rounded-lg text-sm">
                  <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
                <select v-model="form.company_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                  <option value="">ทุกบริษัท</option>
                  <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันเริ่มประกาศ</label>
                <input v-model="form.published_at" type="datetime-local" class="w-full px-3 py-2 border rounded-lg text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันสิ้นสุดประกาศ</label>
                <input v-model="form.expires_at" type="datetime-local" class="w-full px-3 py-2 border rounded-lg text-sm" />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded" />
              <label for="is_active" class="text-sm text-gray-700">เปิดใช้งานประกาศ</label>
            </div>
            <div class="flex gap-3 justify-end pt-2 border-t">
              <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="saveAnnouncement" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-slate-800 text-sm font-medium">
                {{ editingId ? 'บันทึก' : 'สร้างประกาศ' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
