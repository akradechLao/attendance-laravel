<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-navy">นำเข้ารูปใบหน้าพนักงาน</h1>
        <p class="text-gray-500">อัพโหลดรูปใบหน้าสำหรับพนักงานที่ยังไม่ได้ลงทะเบียน</p>
      </div>

      <!-- Employee Search -->
      <div class="card">
        <h3 class="font-semibold text-navy mb-3">ค้นหาพนักงาน</h3>
        <div class="flex gap-3">
          <div class="flex-1 relative">
            <input
              v-model="searchQuery"
              type="text"
              class="input-field pl-10"
              placeholder="พิมพ์ชื่อหรือรหัสพนักงาน..."
              @input="searchEmployees"
            />
            <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <select v-model="selectedCompanyId" class="input-field w-auto" @change="searchEmployees">
            <option value="">ทุกบริษัท</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </div>

        <div v-if="searchResults.length > 0" class="mt-3 max-h-48 overflow-y-auto border rounded-lg">
          <button
            v-for="emp in searchResults"
            :key="emp.id"
            @click="selectEmployee(emp)"
            class="w-full px-4 py-3 text-left hover:bg-blue-50 border-b last:border-b-0 flex items-center gap-3 transition-colors"
          >
            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
              <span class="text-blue-600 text-sm font-semibold">{{ emp.name.charAt(0) }}</span>
            </div>
            <div>
              <p class="font-medium text-navy text-sm">{{ emp.name }}</p>
              <p class="text-xs text-gray-500">{{ emp.employee_code }} | {{ emp.company?.name }}</p>
            </div>
            <span
              v-if="emp.face_data_count > 0"
              class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
            >
              มีใบหน้าแล้ว
            </span>
            <span v-else class="ml-auto text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">
              ยังไม่มี
            </span>
          </button>
        </div>
      </div>

      <!-- Selected Employee & Upload -->
      <div v-if="selectedEmployee" class="card">
        <div class="flex items-center gap-4 mb-4">
          <div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center">
            <span class="text-white text-xl font-bold">{{ selectedEmployee.name.charAt(0) }}</span>
          </div>
          <div>
            <h3 class="font-semibold text-navy">{{ selectedEmployee.name }}</h3>
            <p class="text-sm text-gray-500">{{ selectedEmployee.employee_code }} | {{ selectedEmployee.company?.name }}</p>
          </div>
          <button @click="selectedEmployee = null" class="ml-auto text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Upload Area -->
        <div
          class="border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
          :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400'"
          @dragover.prevent="isDragging = true"
          @dragleave="isDragging = false"
          @drop.prevent="handleDrop"
          @click="$refs.fileInput.click()"
        >
          <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p class="text-gray-600 font-medium">ลากไฟล์มาวาง หรือคลิกเพื่อเลือกรูป</p>
          <p class="text-xs text-gray-400 mt-1">PNG, JPG ไม่เกิน 2MB ต่อไฟล์ (สูงสุด 5 รูป)</p>
        </div>
        <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="handleFileSelect" />

        <!-- Preview -->
        <div v-if="uploadedPhotos.length > 0" class="mt-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-navy">รูปที่เลือก ({{ uploadedPhotos.length }}/5)</p>
            <button v-if="uploadedPhotos.length > 0" @click="clearPhotos" class="text-xs text-red-500 hover:text-red-600">ลบทั้งหมด</button>
          </div>
          <div class="grid grid-cols-5 gap-3">
            <div v-for="(photo, index) in uploadedPhotos" :key="index" class="relative group">
              <img :src="photo.preview" class="w-full aspect-square object-cover rounded-lg border-2 border-green-400" />
              <button
                @click="removePhoto(index)"
                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
              >×</button>
              <p class="text-xs text-center text-gray-500 mt-1">{{ facePositions[index] }}</p>
            </div>
          </div>
        </div>

        <!-- Register Button -->
        <div v-if="uploadedPhotos.length === 5" class="mt-4 text-center">
          <button
            @click="uploadAndRegister"
            :disabled="registering"
            class="btn-primary text-base px-8 py-3"
          >
            {{ registering ? 'กำลังอัพโหลดและลงทะเบียน...' : 'อัพโหลดและลงทะเบียนใบหน้า' }}
          </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="mt-3 p-3 bg-red-50 rounded-lg text-center">
          <p class="text-red-600 text-sm">{{ error }}</p>
        </div>

        <!-- Success -->
        <div v-if="success" class="mt-3 p-3 bg-green-50 rounded-lg text-center">
          <p class="text-green-600 text-sm font-medium">✓ ลงทะเบียนใบหน้าสำเร็จ!</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const searchQuery = ref('')
const selectedCompanyId = ref('')
const companies = ref([])
const searchResults = ref([])
const selectedEmployee = ref(null)
const uploadedPhotos = ref([])
const isDragging = ref(false)
const registering = ref(false)
const error = ref('')
const success = ref(false)

const facePositions = ['ตรงหน้า', 'ซ้าย 45°', 'ขวา 45°', 'มองขึ้น', 'มองลง']

let searchTimeout = null
function searchEmployees() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (!searchQuery.value && !selectedCompanyId.value) {
      searchResults.value = []
      return
    }
    try {
      const params = { search: searchQuery.value, per_page: 20 }
      if (selectedCompanyId.value) params.company_id = selectedCompanyId.value
      const res = await api.get('/api/employees', { params })
      searchResults.value = res.data.data?.data || res.data.data || []
    } catch {
      searchResults.value = []
    }
  }, 300)
}

function selectEmployee(emp) {
  selectedEmployee.value = emp
  uploadedPhotos.value = []
  error.value = ''
  success.value = false
  searchResults.value = []
  searchQuery.value = ''
}

function handleFileSelect(event) {
  const files = Array.from(event.target.files)
  addFiles(files)
  event.target.value = ''
}

function handleDrop(event) {
  isDragging.value = false
  const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'))
  addFiles(files)
}

function addFiles(files) {
  for (const file of files) {
    if (uploadedPhotos.value.length >= 5) break
    if (file.size > 2 * 1024 * 1024) {
      error.value = `ไฟล์ ${file.name} มีขนาดใหญ่เกิน 2MB`
      continue
    }
    uploadedPhotos.value.push({
      file,
      preview: URL.createObjectURL(file)
    })
    error.value = ''
  }
}

function removePhoto(index) {
  URL.revokeObjectURL(uploadedPhotos.value[index].preview)
  uploadedPhotos.value.splice(index, 1)
}

function clearPhotos() {
  uploadedPhotos.value.forEach(p => URL.revokeObjectURL(p.preview))
  uploadedPhotos.value = []
}

async function uploadAndRegister() {
  if (!selectedEmployee.value || uploadedPhotos.value.length < 5) return
  registering.value = true
  error.value = ''
  success.value = false

  try {
    const base64Photos = await Promise.all(
      uploadedPhotos.value.map(photo => fileToBase64(photo.file))
    )

    await api.post(`/api/employees/${selectedEmployee.value.id}/face`, {
      images: base64Photos
    })

    success.value = true
    uploadedPhotos.value = []
    selectedEmployee.value = null
    searchEmployees()
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาดในการลงทะเบียน'
  } finally {
    registering.value = false
  }
}

function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

onMounted(async () => {
  try {
    const res = await api.get('/api/companies')
    companies.value = res.data.data || []
  } catch {}
})
</script>
