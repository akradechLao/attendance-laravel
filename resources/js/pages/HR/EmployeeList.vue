<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-navy">จัดการพนักงาน</h1>
          <p class="text-gray-500">รายการพนักงานทั้งหมด</p>
        </div>
        <button @click="showAddModal = true" class="btn-primary flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          เพิ่มพนักงาน
        </button>
      </div>

      <!-- Search -->
      <div class="card">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                class="input-field pl-12"
                placeholder="ค้นหาชื่อหรือรหัสพนักงาน..."
                @input="debounceSearch"
              />
              <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>
          <select v-model="selectedCompany" class="input-field w-auto" @change="fetchEmployees">
            <option value="">ทุกบริษัท</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Table -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ชื่อ</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">รหัส</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">บริษัท</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">ตำแหน่ง</th>
                  <th class="text-left px-6 py-3 text-sm font-semibold text-gray-600">แผนก</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">ใบหน้า</th>
                  <th class="text-center px-6 py-3 text-sm font-semibold text-gray-600">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="employee in employees" :key="employee.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="text-blue-600 font-semibold">{{ employee.name.charAt(0) }}</span>
                      </div>
                      <span class="font-medium text-navy">{{ employee.name }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-gray-600">{{ employee.code }}</td>
                  <td class="px-6 py-4 text-gray-600">{{ employee.company?.name }}</td>
                  <td class="px-6 py-4 text-gray-600">{{ employee.position }}</td>
                  <td class="px-6 py-4 text-gray-600">{{ employee.department }}</td>
                  <td class="px-6 py-4 text-center">
                    <span
                      v-if="employee.face_data_count > 0"
                      class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700"
                    >
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                      ลงทะเบียนแล้ว
                    </span>
                    <span v-else class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                      ยังไม่ลงทะเบียน
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center justify-center gap-2">
                      <router-link
                        :to="`/employees/${employee.id}/face`"
                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                        title="ลงทะเบียนใบหน้า"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </router-link>
                      <button
                        @click="editEmployee(employee)"
                        class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                        title="แก้ไข"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <button
                        @click="confirmDelete(employee)"
                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                        title="ลบ"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                      <button
                        @click="resetPassword(employee)"
                        class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors"
                        title="รีเซ็ตรหัสผ่าน"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t">
            <p class="text-sm text-gray-500">
              แสดง {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }} จาก {{ totalItems }} รายการ
            </p>
            <div class="flex items-center gap-2">
              <button
                @click="currentPage--"
                :disabled="currentPage === 1"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                ก่อนหน้า
              </button>
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="currentPage = page"
                :class="[
                  'px-3 py-1 rounded border',
                  currentPage === page ? 'bg-blue-500 text-white border-blue-500' : 'border-gray-300 hover:bg-gray-50'
                ]"
              >
                {{ page }}
              </button>
              <button
                @click="currentPage++"
                :disabled="currentPage === totalPages"
                class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                ถัดไป
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Add/Edit Modal -->
      <Modal :show="showAddModal || showEditModal" @close="closeModal" :title="showEditModal ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน'">
        <form @submit.prevent="saveEmployee" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
            <input v-model="form.name" type="text" class="input-field" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสพนักงาน</label>
            <input v-model="form.code" type="text" class="input-field" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
            <select v-model="form.company_id" class="input-field" required>
              <option value="">เลือกบริษัท</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง</label>
            <input v-model="form.position" type="text" class="input-field" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">แผนก</label>
            <input v-model="form.department" type="text" class="input-field" />
          </div>
          <div class="flex justify-end gap-3 pt-4">
            <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
              ยกเลิก
            </button>
            <button type="submit" :disabled="saving" class="btn-primary">
              {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
            </button>
          </div>
        </form>
      </Modal>

      <!-- Delete Confirmation Modal -->
      <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="ยืนยันการลบ">
        <p class="text-gray-600 mb-6">ต้องการลบพนักงาน <strong>{{ deleteTarget?.name }}</strong> ใช่หรือไม่?</p>
        <div class="flex justify-end gap-3">
          <button @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
            ยกเลิก
          </button>
          <button @click="deleteEmployee" :disabled="deleting" class="btn-danger">
            {{ deleting ? 'กำลังลบ...' : 'ลบ' }}
          </button>
        </div>
      </Modal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Modal from '../../components/Modal.vue'

const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const searchQuery = ref('')
const selectedCompany = ref('')
const employees = ref([])
const companies = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)

const showAddModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const editId = ref(null)

const form = reactive({
  name: '',
  code: '',
  company_id: '',
  position: '',
  department: ''
})

const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value))
const visiblePages = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, start + 4)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

let searchTimeout = null
function debounceSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchEmployees()
  }, 300)
}

async function fetchEmployees() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: searchQuery.value,
      company_id: selectedCompany.value || undefined
    }
    const response = await api.get('/api/employees', { params })
    employees.value = response.data.data || response.data
    totalItems.value = response.data.total || employees.value.length
  } catch (error) {
    console.error('Error fetching employees:', error)
  } finally {
    loading.value = false
  }
}

async function fetchCompanies() {
  try {
    const response = await api.get('/api/companies')
    companies.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching companies:', error)
  }
}

function editEmployee(employee) {
  editId.value = employee.id
  Object.assign(form, {
    name: employee.name,
    code: employee.code,
    company_id: employee.company_id,
    position: employee.position,
    department: employee.department
  })
  showEditModal.value = true
}

function confirmDelete(employee) {
  deleteTarget.value = employee
  showDeleteModal.value = true
}

async function saveEmployee() {
  saving.value = true
  try {
    if (showEditModal.value) {
      await api.put(`/api/employees/${editId.value}`, form)
    } else {
      await api.post('/api/employees', form)
    }
    closeModal()
    fetchEmployees()
  } catch (error) {
    console.error('Error saving employee:', error)
    alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล')
  } finally {
    saving.value = false
  }
}

async function deleteEmployee() {
  deleting.value = true
  try {
    await api.delete(`/api/employees/${deleteTarget.value.id}`)
    showDeleteModal.value = false
    fetchEmployees()
  } catch (error) {
    console.error('Error deleting employee:', error)
    alert('เกิดข้อผิดพลาดในการลบข้อมูล')
  } finally {
    deleting.value = false
  }
}

function closeModal() {
  showAddModal.value = false
  showEditModal.value = false
  editId.value = null
  Object.assign(form, { name: '', code: '', company_id: '', position: '', department: '' })
}

async function resetPassword(employee) {
  const newPassword = prompt(`ตั้งรหัสผ่านใหม่สำหรับ ${employee.name}\n(ปล่อยว่าง = ใช้ "password")`, 'password')
  if (newPassword === null) return

  try {
    const res = await api.post(`/api/employees/${employee.id}/reset-password`, {
      password: newPassword || 'password'
    })
    if (res.data.success) {
      alert(`รีเซ็ตรหัสผ่านสำเร็จ!\nพนักงาน: ${employee.name}\nรหัสผ่านใหม่: ${newPassword || 'password'}`)
    }
  } catch (error) {
    alert('เกิดข้อผิดพลาด: ' + (error.response?.data?.message || error.message))
  }
}

watch(currentPage, () => fetchEmployees())

onMounted(() => {
  fetchEmployees()
  fetchCompanies()
})
</script>
