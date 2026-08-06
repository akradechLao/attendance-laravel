<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const locations = ref([])
const showForm = ref(false)
const newLocation = ref({
  name: '',
  latitude: '',
  longitude: '',
  radius: 200,
})

onMounted(async () => {
  await loadLocations()
})

const loadLocations = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/office-locations')
    locations.value = response.data.data
  } catch (error) {
    console.error('Failed to load locations:', error)
  } finally {
    loading.value = false
  }
}

const addLocation = async () => {
  if (!newLocation.value.name || !newLocation.value.latitude || !newLocation.value.longitude) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }

  try {
    await api.post('/api/office-locations', newLocation.value)
    alert('เพิ่มสถานที่สำเร็จ')
    showForm.value = false
    newLocation.value = { name: '', latitude: '', longitude: '', radius: 200 }
    await loadLocations()
  } catch (error) {
    alert('เกิดข้อผิดพลาดในการเพิ่มสถานที่')
  }
}

const deleteLocation = async (id) => {
  if (confirm('คุณต้องการลบสถานที่นี้ใช่หรือไม่?')) {
    try {
      await api.delete(`/api/office-locations/${id}`)
      alert('ลบสถานที่สำเร็จ')
      await loadLocations()
    } catch (error) {
      alert('เกิดข้อผิดพลาดในการลบสถานที่')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Location Settings</h1>
        <p class="text-gray-500">ตั้งค่าสถานที่</p>
      </div>
      <button @click="showForm = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        + เพิ่มสถานที่
      </button>
    </div>

    <!-- Locations List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อสถานที่</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ละติจูด</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ลองจิจูด</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รัศมี (เมตร)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="location in locations" :key="location.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ location.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ location.latitude }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ location.longitude }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ location.radius }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button @click="deleteLocation(location.id)" class="text-red-600 hover:text-red-800">ลบ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Location Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">เพิ่มสถานที่</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">ชื่อสถานที่</label>
            <input v-model="newLocation.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="เช่น สำนักงานใหญ่" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">ละติจูด</label>
              <input v-model="newLocation.latitude" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="13.7563" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">ลองจิจูด</label>
              <input v-model="newLocation.longitude" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="100.5018" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">รัศมี (เมตร)</label>
            <input v-model="newLocation.radius" type="number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">ยกเลิก</button>
            <button @click="addLocation" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">เพิ่ม</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
