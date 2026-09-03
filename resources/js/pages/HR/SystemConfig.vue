<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const loading = ref(true)
const saving = ref(false)
const configData = ref({})
const activeCategory = ref('attendance')
const saveMessage = ref('')
const saveError = ref(false)

const categories = [
  { key: 'attendance', label: 'การเข้างาน', icon: '🕐' },
  { key: 'ot', label: 'โอที (OT)', icon: '⏰' },
  { key: 'wfh', label: '-work from home', icon: '🏠' },
  { key: 'leave', label: 'ลางาน', icon: '📋' },
  { key: 'approval', label: 'ลำดับอนุมัติ', icon: '✅' },
  { key: 'notification', label: 'การแจ้งเตือน', icon: '🔔' },
]

const categoryLabels = {
  attendance: 'การเข้างาน',
  ot: 'โอที (OT)',
  wfh: '-work from home',
  leave: 'ลางาน',
  approval: 'ลำดับอนุมัติ',
  notification: 'การแจ้งเตือน',
}

const typeLabels = {
  integer: 'ตัวเลข',
  boolean: 'ใช่/ไม่ใช่',
  string: 'ข้อความ',
  json: 'JSON',
}

// Local edit state
const edits = ref({})

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  loading.value = true
  try {
    const { data } = await api.get('/api/system-config')
    configData.value = data.data || {}
    // Initialize edits from loaded data
    const e = {}
    for (const [cat, items] of Object.entries(configData.value)) {
      for (const [key, item] of Object.entries(items)) {
        e[key] = String(item.value ?? '')
      }
    }
    edits.value = e
  } catch (err) {
    console.error('Failed to load config:', err)
  } finally {
    loading.value = false
  }
}

async function saveConfig() {
  saving.value = true
  saveMessage.value = ''
  saveError.value = false
  try {
    const configs = Object.entries(edits.value).map(([key, value]) => ({ key, value }))
    await api.put('/api/system-config', { configs })
    saveMessage.value = 'บันทึกเรียบร้อย'
    setTimeout(() => { saveMessage.value = '' }, 3000)
    await loadConfig()
  } catch (err) {
    saveError.value = true
    saveMessage.value = 'เกิดข้อผิดพลาด: ' + (err.response?.data?.message || err.message)
  } finally {
    saving.value = false
  }
}

async function resetConfig() {
  if (!confirm('รีเซ็ตค่าทั้งหมดเป็นค่าเริ่มต้น? การเปลี่ยนแปลงจะหายไป')) return
  try {
    await api.delete('/api/system-config/reset')
    await loadConfig()
    saveMessage.value = 'รีเซ็ตเรียบร้อย'
    setTimeout(() => { saveMessage.value = '' }, 3000)
  } catch (err) {
    saveError.value = true
    saveMessage.value = 'เกิดข้อผิดพลาด'
  }
}
</script>

<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto">
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-2xl font-bold text-navy">ตั้งค่าระบบ</h1>
            <p class="text-gray-500 mt-1">กำหนดนโยบายและค่าตั้งต่างๆ ของระบบ</p>
          </div>
          <div class="flex items-center gap-2">
            <span v-if="saveMessage" :class="saveError ? 'text-red-600' : 'text-emerald-600'" class="text-sm font-medium">
              {{ saveMessage }}
            </span>
            <button @click="resetConfig" class="btn-secondary text-sm" :disabled="saving">
              รีเซ็ตค่าเริ่มต้น
            </button>
            <button @click="saveConfig" class="btn-primary" :disabled="saving">
              {{ saving ? 'กำลังบันทึก...' : 'บันทึกทั้งหมด' }}
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-navy mx-auto"></div>
          <p class="text-gray-500 mt-2">กำลังโหลด...</p>
        </div>

        <template v-else>
          <!-- Category Tabs -->
          <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-3">
            <button
              v-for="cat in categories"
              :key="cat.key"
              @click="activeCategory = cat.key"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                activeCategory === cat.key
                  ? 'bg-navy text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ cat.icon }} {{ cat.label }}
            </button>
          </div>

          <!-- Config Items -->
          <div class="space-y-4">
            <div v-if="!configData[activeCategory]" class="text-center py-8 text-gray-400">
              ไม่มีค่าตั้งในหมวดนี้
            </div>

            <div
              v-for="(item, key) in configData[activeCategory]"
              :key="key"
              class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-100"
            >
              <!-- Key + Description -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-sm text-navy font-semibold">{{ key }}</span>
                  <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 text-gray-600">
                    {{ typeLabels[item.type] || item.type }}
                  </span>
                  <span v-if="item.source === 'default'" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                    ค่าเริ่มต้น
                  </span>
                  <span v-else class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                    DB
                  </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ item.description }}</p>
              </div>

              <!-- Input -->
              <div class="w-full sm:w-48">
                <!-- Boolean -->
                <select
                  v-if="item.type === 'boolean'"
                  v-model="edits[key]"
                  class="input-field text-sm"
                >
                  <option value="true">เปิดใช้งาน</option>
                  <option value="false">ปิดใช้งาน</option>
                </select>

                <!-- Integer -->
                <input
                  v-else-if="item.type === 'integer'"
                  type="number"
                  v-model="edits[key]"
                  class="input-field text-sm"
                  min="0"
                />

                <!-- String / other -->
                <input
                  v-else
                  type="text"
                  v-model="edits[key]"
                  class="input-field text-sm"
                  :placeholder="item.description"
                />
              </div>
            </div>
          </div>
        </template>

        <!-- Info -->
        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-sm text-blue-800">
            <strong>หมายเหตุ:</strong> ค่าที่แสดงเป็นค่าปัจจุบัน (ถ้าแก้ใน DB แล้วจะแสดง "DB")
            ค่า "ค่าเริ่มต้น" คือค่าที่ระบบจะใช้ถ้าไม่มีค่าใน DB — ระบบยังทำงานได้ปกติแม้ฐานข้อมูลเสียหาย
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
