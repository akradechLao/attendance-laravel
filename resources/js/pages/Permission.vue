<template>
  <AppLayout>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">จัดการสิทธิ์พนักงาน</h1>
      <div class="flex gap-2">
        <input v-model="search" type="text" placeholder="ค้นหาชื่อหรือรหัส..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
        <select v-model="filterRole" class="px-4 py-2 border rounded-lg">
          <option value="">ทุกสิทธิ์</option>
          <option value="employee">พนักงาน</option>
          <option value="admin">HR</option>
          <option value="super_admin">ผู้ดูแลระบบ</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-blue-600">{{ employees.length }}</div>
        <div class="text-sm text-gray-500">พนักงานทั้งหมด</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-green-600">{{ employees.filter(e => e.is_active).length }}</div>
        <div class="text-sm text-gray-500">เปิดใช้งาน</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-red-600">{{ employees.filter(e => !e.is_active).length }}</div>
        <div class="text-sm text-gray-500">ปิดใช้งาน</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">รหัส</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">ชื่อ-สกุล</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">ตำแหน่ง</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">สิทธิ์ปัจจุบัน</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">สถานะ</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in filteredEmployees" :key="emp.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ emp.employee_code }}</td>
            <td class="px-4 py-3">
              <div class="text-sm font-medium">{{ emp.name }}</div>
              <div v-if="emp.nickname" class="text-xs text-gray-400">{{ emp.nickname }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ emp.position }}</td>
            <td class="px-4 py-3">
              <select :value="emp.role" @change="updateRole(emp, $event.target.value)" class="text-sm border rounded px-2 py-1" :disabled="emp.id === currentUserId">
                <option value="employee">พนักงาน</option>
                <option value="admin">HR</option>
                <option value="super_admin">ผู้ดูแลระบบ</option>
              </select>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="toggleStatus(emp)" :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-3 py-1 rounded-full text-xs font-medium" :disabled="emp.id === currentUserId">
                {{ emp.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="resetPassword(emp)" class="text-orange-600 hover:text-orange-800 text-sm" :disabled="emp.id === currentUserId">
                รีเซ็ตรหัสผ่าน
              </button>
            </td>
          </tr>
          <tr v-if="filteredEmployees.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่พบข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="toast" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50">{{ toast }}</div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import AppLayout from '@/layouts/AppLayout.vue'

const employees = ref([])
const search = ref('')
const filterRole = ref('')
const toast = ref('')
const currentUserId = ref(null)

const filteredEmployees = computed(() => {
  return employees.value.filter(emp => {
    const matchSearch = !search.value || emp.name.includes(search.value) || emp.employee_code?.includes(search.value) || emp.nickname?.includes(search.value)
    const matchRole = !filterRole.value || emp.role === filterRole.value
    return matchSearch && matchRole
  })
})

const showToast = (msg) => {
  toast.value = msg
  setTimeout(() => toast.value = '', 3000)
}

const updateRole = async (emp, newRole) => {
  try {
    await api.put(`/api/permissions/employees/${emp.id}/role`, { role: newRole })
    emp.role = newRole
    showToast(`อัพเดทสิทธิ์ ${emp.name} สำเร็จ`)
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

const toggleStatus = async (emp) => {
  const newStatus = !emp.is_active
  if (!confirm(`${newStatus ? 'เปิดใช้งาน' : 'ปิดใช้งาน'} ${emp.name}?`)) return
  try {
    await api.put(`/api/permissions/employees/${emp.id}/status`, { is_active: newStatus })
    emp.is_active = newStatus
    showToast('อัพเดทสถานะสำเร็จ')
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

const resetPassword = async (emp) => {
  if (!confirm(`รีเซ็ตรหัสผ่าน ${emp.name}? (รหัสผ่านใหม่: password)`)) return
  try {
    await api.post(`/api/permissions/employees/${emp.id}/reset-password`)
    showToast('รีเซ็ตรหัสผ่านสำเร็จ')
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/api/permissions/employees')
    employees.value = res.data
  } catch (e) {
    console.error(e)
  }
})
</script>
