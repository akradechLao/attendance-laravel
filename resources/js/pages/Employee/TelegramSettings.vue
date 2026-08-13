<template>
  <div class="p-4 space-y-6">
    <h1 class="text-2xl font-bold text-[#0f172a]">ตั้งค่า Telegram</h1>

    <!-- Bot Info -->
    <div v-if="botInfo" class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">Bot</h2>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-lg">🤖</div>
        <div>
          <div class="font-semibold text-sm">{{ botInfo.first_name }}</div>
          <div class="text-xs text-gray-500">@{{ botInfo.username }}</div>
        </div>
        <span class="ml-auto text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">เชื่อมต่อแล้ว</span>
      </div>
    </div>

    <!-- My Chat ID -->
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
            <input v-model="chatId" type="text" placeholder="เช่น 123456789" class="flex-1 border rounded-lg p-2" />
            <button @click="saveChatId" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ saving ? "..." : "บันทึก" }}
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-1">ส่งข้อความหา @userinfobot แล้วคัดลอก ID มาใส่</p>
        </div>
      </div>
    </div>

    <!-- Telegram Groups -->
    <div class="bg-white rounded-xl shadow p-4">
      <div class="flex justify-between items-center mb-3">
        <h2 class="font-semibold text-[#0f172a]">Telegram Groups</h2>
        <button @click="showAddGroup = true" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700">+ เพิ่มกลุ่ม</button>
      </div>
      <div v-if="groups.length === 0" class="text-center py-4 text-gray-500 text-sm">ยังไม่มีกลุ่ม</div>
      <div v-else class="space-y-2">
        <div v-for="g in groups" :key="g.id" class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
          <div>
            <div class="font-semibold text-sm">{{ g.group_name }}</div>
            <div class="text-xs text-gray-500">{{ g.company?.name }} | {{ groupTypeText(g.group_type) }}</div>
            <div class="text-[10px] text-gray-400">Chat ID: {{ g.telegram_chat_id }}</div>
          </div>
          <div class="flex items-center gap-2">
            <span :class="g.is_active ? "text-green-600" : "text-gray-400" class="text-xs">
              {{ g.is_active ? "เปิด" : "ปิด" }}
            </span>
            <button @click="testGroup(g.id)" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">ทดสอบ</button>
            <button @click="editGroup(g)" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">แก้ไข</button>
            <button @click="deleteGroup(g.id)" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">ลบ</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Subordinates -->
    <div v-if="subordinates.length > 0" class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ลูกทีม ({{ subordinates.length }} คน)</h2>
      <div class="space-y-2">
        <div v-for="emp in subordinates" :key="emp.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
          <div>
            <div class="font-medium text-sm">{{ emp.name }}</div>
            <div class="text-xs text-gray-500">{{ emp.employee_code }}</div>
          </div>
          <span v-if="emp.telegram_chat_id" class="text-xs text-green-600">✓ ตั้งค่าแล้ว</span>
          <span v-else class="text-xs text-red-500">✗ ยังไม่ตั้งค่า</span>
        </div>
      </div>
    </div>

    <!-- Test Notification -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold text-[#0f172a] mb-3">ทดสอบแจ้งเตือน</h2>
      <button @click="testNotification" :disabled="testing || !chatId" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">
        {{ testing ? "กำลังส่ง..." : "ส่งข้อความทดสอบ (ส่วนตัว)" }}
      </button>
    </div>

    <!-- Add/Edit Group Modal -->
    <div v-if="showAddGroup" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="font-semibold text-lg">{{ editingGroup ? "แก้ไขกลุ่ม" : "เพิ่มกลุ่มใหม่" }}</h3>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">ชื่อกลุ่ม</label>
          <input v-model="groupForm.group_name" class="w-full border rounded-lg p-2" placeholder="เช่น NTC - ฝ่ายขาย" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
          <select v-model="groupForm.company_id" class="w-full border rounded-lg p-2"><option value="">เลือก</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">ประเภทกลุ่ม</label>
          <select v-model="groupForm.group_type" class="w-full border rounded-lg p-2"><option value="company">บริษัท</option><option value="branch">สาขา</option><option value="department">แผนก</option><option value="supervisor">หัวหน้า</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Telegram Group/Channel Chat ID</label>
          <input v-model="groupForm.telegram_chat_id" class="w-full border rounded-lg p-2" placeholder="-100xxxxxxxxxx" /></div>
        <div v-if="groupForm.group_type === 'branch'"><label class="block text-sm font-medium text-gray-700 mb-1">สาขา (ถ้ามี)</label>
          <select v-model="groupForm.office_location_id" class="w-full border rounded-lg p-2"><option value="">ไม่ระบุ</option>
            <option v-for="o in officeLocations" :key="o.id" :value="o.id">{{ o.name }}</option></select></div>
        <div class="flex items-center gap-2"><input type="checkbox" v-model="groupForm.is_active" id="active" /><label for="active" class="text-sm">เปิดใช้งาน</label></div>
        <div class="flex gap-3 mt-4">
          <button @click="saveGroup" :disabled="savingGroup" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">{{ savingGroup ? "..." : "บันทึก" }}</button>
          <button @click="closeGroupForm" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">ยกเลิก</button>
        </div>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg text-white text-sm z-50"
         :class="toast.type === "success" ? "bg-green-600" : "bg-red-600"">{{ toast.message }}</div>
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
const groups = ref([])
const companies = ref([])
const officeLocations = ref([])
const botInfo = ref(null)
const saving = ref(false)
const testing = ref(false)
const savingGroup = ref(false)
const showAddGroup = ref(false)
const editingGroup = ref(null)
const toast = ref(null)

const groupForm = ref({ company_id: '', group_name: '', group_type: 'company', telegram_chat_id: '', office_location_id: '', is_active: true })

const groupTypeText = (t) => ({ company: 'บริษัท', branch: 'สาขา', department: 'แผนก', supervisor: 'หัวหน้า' })[t] || t

const loadData = async () => {
  try {
    const [empRes, subRes, grpRes, comRes, locRes, botRes] = await Promise.all([
      api.get(`/api/employees/${employeeId.value}`),
      api.get('/api/employees', { params: { per_page: 200 } }),
      api.get('/api/telegram/groups'),
      api.get('/api/companies'),
      api.get('/api/office-locations'),
      api.get('/api/telegram/bot-info').catch(() => ({ data: null })),
    ])
    chatId.value = empRes.data.data?.telegram_chat_id || ''
    const allEmps = subRes.data.data?.data || subRes.data.data || []
    subordinates.value = allEmps.filter(e => e.supervisor_id === employeeId.value || e.reports_to === employeeId.value)
    groups.value = grpRes.data.data || []
    companies.value = comRes.data.data || []
    officeLocations.value = locRes.data.data || []
    botInfo.value = botRes.data?.data || null
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

const testGroup = async (groupId) => {
  try {
    await api.post('/api/telegram/test-group', { group_id: groupId })
    showToast('success', 'ส่งทดสอบกลุ่มสำเร็จ!')
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
}

const editGroup = (g) => {
  editingGroup.value = g
  groupForm.value = { company_id: g.company_id, group_name: g.group_name, group_type: g.group_type, telegram_chat_id: g.telegram_chat_id, office_location_id: g.office_location_id || '', is_active: g.is_active }
  showAddGroup.value = true
}

const saveGroup = async () => {
  savingGroup.value = true
  try {
    if (editingGroup.value) {
      await api.put(`/api/telegram/groups/${editingGroup.value.id}`, groupForm.value)
      showToast('success', 'อัปเดตกลุ่มสำเร็จ')
    } else {
      await api.post('/api/telegram/groups', groupForm.value)
      showToast('success', 'สร้างกลุ่มสำเร็จ')
    }
    closeGroupForm()
    loadData()
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
  savingGroup.value = false
}

const deleteGroup = async (id) => {
  if (!confirm('ต้องการลบกลุ่มนี้?')) return
  try {
    await api.delete(`/api/telegram/groups/${id}`)
    showToast('success', 'ลบกลุ่มสำเร็จ')
    loadData()
  } catch (err) { showToast('error', err.response?.data?.message || 'เกิดข้อผิดพลาด') }
}

const closeGroupForm = () => {
  showAddGroup.value = false
  editingGroup.value = null
  groupForm.value = { company_id: '', group_name: '', group_type: 'company', telegram_chat_id: '', office_location_id: '', is_active: true }
}

const showToast = (type, message) => { toast.value = { type, message }; setTimeout(() => toast.value = null, 3000) }

onMounted(loadData)
</script>
