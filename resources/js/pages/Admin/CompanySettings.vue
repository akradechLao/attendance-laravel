<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const saving = ref(false)
const uploadingLogo = ref(false)
const companies = ref([])
const activeCompanyId = ref(null)
const logoInput = ref(null)
const logoPreview = ref(null)

const companyNames = {
  1: 'NTC',
  2: 'ETC',
  3: 'ETECH',
  4: 'STC',
}

const activeCompany = computed(() => {
  return companies.value.find(c => c.id === activeCompanyId.value) || null
})

const form = ref({
  name: '',
  phone: '',
  email: '',
  address: '',
  website: '',
  work_start_time: '08:30',
  work_end_time: '17:30',
  late_threshold: 30,
  location_radius: 200,
  enable_face_recognition: true,
  enable_remote_scan: true,
})

onMounted(async () => {
  await loadAllCompanies()
})

async function loadAllCompanies() {
  loading.value = true
  try {
    const res = await api.get('/api/company-settings/all')
    companies.value = res.data.data?.data || res.data.data || []
    if (companies.value.length > 0 && !activeCompanyId.value) {
      activeCompanyId.value = companies.value[0].id
      loadCompanyForm()
    }
  } catch (err) {
    console.error('Failed to load companies:', err)
  } finally {
    loading.value = false
  }
}

function selectCompany(id) {
  activeCompanyId.value = id
  logoPreview.value = null
  loadCompanyForm()
}

function loadCompanyForm() {
  const company = activeCompany.value
  if (!company) return

  form.value = {
    name: company.name || '',
    phone: company.phone || '',
    email: company.email || '',
    address: company.address || '',
    website: company.website || '',
    work_start_time: '08:30',
    work_end_time: '17:30',
    late_threshold: 30,
    location_radius: 200,
    enable_face_recognition: true,
    enable_remote_scan: true,
  }

  // Load settings from company_settings
  if (company.settings && Array.isArray(company.settings)) {
    company.settings.forEach(s => {
      if (s.key in form.value) {
        if (s.value === '1') form.value[s.key] = true
        else if (s.value === '0') form.value[s.key] = false
        else if (!isNaN(s.value) && s.key !== 'work_start_time' && s.key !== 'work_end_time') form.value[s.key] = Number(s.value)
        else form.value[s.key] = s.value
      }
    })
  }
}

async function saveCompany() {
  if (!activeCompanyId.value) return
  saving.value = true
  try {
    await api.put('/api/company-settings', form.value, {
      params: { company_id: activeCompanyId.value }
    })
    alert('บันทึกสำเร็จ')
    await loadAllCompanies()
  } catch (err) {
    alert('เกิดข้อผิดพลาด: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

function triggerLogoUpload() {
  logoInput.value?.click()
}

async function handleLogoUpload(event) {
  const file = event.target.files?.[0]
  if (!file || !activeCompanyId.value) return

  if (!file.type.startsWith('image/')) {
    alert('กรุณาเลือกไฟล์รูปภาพ')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    alert('ไฟล์ต้องไม่เกิน 2MB')
    return
  }

  // Preview
  logoPreview.value = URL.createObjectURL(file)

  uploadingLogo.value = true
  try {
    const formData = new FormData()
    formData.append('logo', file)
    const res = await api.post('/api/company-settings/logo', formData, {
      params: { company_id: activeCompanyId.value },
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    logoPreview.value = null
    await loadAllCompanies()
    alert('อัพโหลดโลโก้สำเร็จ')
  } catch (err) {
    logoPreview.value = null
    alert('อัพโหลดไม่สำเร็จ: ' + (err.response?.data?.message || err.message))
  } finally {
    uploadingLogo.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function removeLogo() {
  if (!activeCompanyId.value) return
  if (!confirm('ลบโลโก้บริษัทนี้?')) return
  try {
    await api.delete('/api/company-settings/logo', {
      params: { company_id: activeCompanyId.value }
    })
    await loadAllCompanies()
  } catch (err) {
    alert('ลบไม่สำเร็จ: ' + (err.response?.data?.message || err.message))
  }
}
</script>

<template>
  <div class="p-4 sm:p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-navy">ตั้งค่าบริษัท</h1>
      <p class="text-sm text-gray-500 mt-1">จัดการข้อมูลบริษัท โลโก้ และการตั้งค่าระบบ</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-gray-400">กำลังโหลด...</div>

    <template v-else>
      <!-- Company Tabs -->
      <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
        <button
          v-for="company in companies"
          :key="company.id"
          @click="selectCompany(company.id)"
          :class="[
            'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap',
            activeCompanyId === company.id
              ? 'bg-navy text-white shadow-md'
              : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
          ]"
        >
          {{ company.name }}
        </button>
      </div>

      <!-- Active Company Form -->
      <div v-if="activeCompany" class="space-y-6">
        <!-- Logo Section -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="text-lg font-semibold text-navy mb-4">โลโก้บริษัท</h2>
          <div class="flex items-start gap-6">
            <!-- Logo Preview -->
            <div class="relative">
              <div class="w-32 h-32 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
                <img
                  v-if="logoPreview || activeCompany.logo_url"
                  :src="logoPreview || activeCompany.logo_url"
                  alt="Logo"
                  class="w-full h-full object-contain p-2"
                />
                <div v-else class="text-center text-gray-400">
                  <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span class="text-xs">ไม่มีโลโก้</span>
                </div>
              </div>
              <div v-if="uploadingLogo" class="absolute inset-0 bg-white/70 rounded-xl flex items-center justify-center">
                <div class="animate-spin w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full"></div>
              </div>
            </div>

            <div class="flex-1 space-y-2">
              <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="handleLogoUpload" />
              <div class="flex gap-2">
                <button @click="triggerLogoUpload" :disabled="uploadingLogo" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                  {{ uploadingLogo ? 'กำลังอัพโหลด...' : 'เลือกรูป' }}
                </button>
                <button v-if="activeCompany.logo_url" @click="removeLogo" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm hover:bg-red-100">
                  ลบโลโก้
                </button>
              </div>
              <p class="text-xs text-gray-400">PNG, JPG, SVG หรือ WebP ขนาดไม่เกิน 2MB</p>
            </div>
          </div>
        </div>

        <!-- Company Info -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="text-lg font-semibold text-navy mb-4">ข้อมูลบริษัท</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อบริษัท *</label>
              <input v-model="form.name" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
              <input v-model="form.phone" type="tel" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="02-xxx-xxxx" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
              <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="info@company.com" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เว็บไซต์</label>
              <input v-model="form.website" type="url" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="https://company.com" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
              <textarea v-model="form.address" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
            </div>
          </div>
        </div>

        <!-- Work Settings -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="text-lg font-semibold text-navy mb-4">ตั้งค่าเวลาทำงาน</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้างาน</label>
              <input v-model="form.work_start_time" type="time" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเลิกงาน</label>
              <input v-model="form.work_end_time" type="time" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">กำหนดสาย (นาที)</label>
              <input v-model.number="form.late_threshold" type="number" min="0" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">รัศมี GPS (เมตร)</label>
              <input v-model.number="form.location_radius" type="number" min="50" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
            </div>
          </div>
        </div>

        <!-- Feature Toggles -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="text-lg font-semibold text-navy mb-4">เปิด/ปิด ฟีเจอร์</h2>
          <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.enable_face_recognition" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span class="text-sm text-gray-700">เปิดใช้งาน Face Recognition</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.enable_remote_scan" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span class="text-sm text-gray-700">เปิดใช้งาน Remote Scan (สแกนนอกสถานที่)</span>
            </label>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
          <button @click="saveCompany" :disabled="saving" class="px-6 py-2.5 bg-navy text-white rounded-lg hover:bg-navy/90 disabled:opacity-50 text-sm font-medium">
            {{ saving ? 'กำลังบันทึก...' : 'บันทึกการตั้งค่า' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
