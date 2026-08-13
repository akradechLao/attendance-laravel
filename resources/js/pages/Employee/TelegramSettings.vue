<template>
  <div class="p-4 space-y-6">
    <h1 class="text-2xl font-bold text-[#0f172a]">ตั้งค่า Telegram</h1>

    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ข้อมูลของฉัน</h2>
      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-gray-700">ชื่อ</label>
          <div class="text-sm text-gray-900">{{ user?.name }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Telegram Chat ID</label>
          <div class="flex gap-2">
            <input v-model="chatId" type="text" placeholder="เช่น 123456789"
                   class="flex-1 border rounded-lg p-2" />
            <button @click="saveChatId" :disabled="saving"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-1">ส่งข้อความหา @userinfobot ใน Telegram แล้วคัดลอก ID มาใส่</p>
        </div>
      </div>
    </div>

    <div v-if="subordinates.length > 0" class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ลูกทีม ({{ subordinates.length }} คน)</h2>
      <div class="space-y-3">
        <div v-for="emp in subordinates" :key="emp.id"
             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
          <div>
            <div class="font-medium text-sm">{{ emp.name }}</div>
            <div class="text-xs text-gray-500">{{ emp.employee_code }}</div>
          </div>
          <span v-if="emp.telegram_chat_id" class="text-xs text-green-600">✓ ตั้งค่าแล้ว</span>
          <span v-else class="text-xs text-red-500">✗ ยังไม่ตั้งค่า</span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ทดสอบแจ้งเตือน</h2>
      <button @click="testNotification" :disabled="testing || !chatId"
              class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">
        {{ testing ? 'กำลังส่ง...' : 'ส่งข้อความทดสอบ' }}
      </button>
    </div>

    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm"
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'">
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import state from '@/store'

const user = computed(() => state.user)
const employeeId = computed(() => user.value?.id)
const chatId = ref('')
const subordinates = ref([])
const saving = ref(false)
const testing = ref(false)
const toast = ref(null)

const loadData = async () => {
  try {
    const res = await api.get(`/api/employees/${employeeId.value}`)
    chatId.value = res.data.data?.telegram_chat_id || ''
    const subRes = await api.get('/api/employees', { params: { per_page: 200 } })
    const allEmps = subRes.data.data?.data || subRes.data.data || []
    subordinates.value = allEmps.filter(e => e.supervisor_id === employeeId.value || e.reports_to === employeeId.value)
  } catch (err) { console.error(err) }
}

const saveChatId = async () => {
  saving.value = true
  try {
    await api.put(`/api/employees/${employeeId.value}`, { telegram_chat_id: chatId.value || null })
    showToast('success', 'บันทึกสำเร็จ')
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
  saving.value = false
}

const testNotification = async () => {
  testing.value = true
  try {
    await api.post('/api/telegram/test', { employee_id: employeeId.value })
    showToast('success', 'ส่งข้อความทดสอบสำเร็จ!')
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
  testing.value = false
}

const showToast = (type, message) => {
  toast.value = { type, message }
  setTimeout(() => toast.value = null, 3000)
}

onMounted(loadData)
</script>
