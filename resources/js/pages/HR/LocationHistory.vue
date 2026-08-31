<template>
  <AppLayout>
    <div class="p-4 sm:p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">ติดตามเส้นทางปฏิบัติงาน</h1>
        <p class="text-gray-500">ติดตามตำแหน่งพนักงานที่ปฏิบัติงานนอกสถานที่แบบเรียลไทม์</p>
      </div>
      <div class="flex gap-2">
        <button @click="refreshLocations" class="btn-secondary" :disabled="refreshing">
          {{ refreshing ? 'กำลังโหลด...' : '↻ รีเฟรช' }}
        </button>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="autoRefresh" class="rounded" />
          อัพเดตอัตโนมัติ (30วิ)
        </label>
      </div>
    </div>

    <!-- Employee selector -->
    <div class="card">
      <div class="flex flex-wrap gap-4">
        <select v-model="selectedEmployeeId" class="input-field w-64" @change="loadHistory">
          <option value="">เลือกพนักงาน</option>
          <option v-for="emp in remoteEmployees" :key="emp.employee_id" :value="emp.employee_id">
            {{ emp.employee_name }} ({{ emp.employee_code }})
          </option>
        </select>
        <input v-model="selectedDate" type="date" class="input-field" @change="loadHistory" />
      </div>
    </div>

    <!-- Map -->
    <div class="card p-0 overflow-hidden">
      <div ref="mapContainer" class="w-full h-[600px]"></div>
    </div>

    <!-- Location list -->
    <div v-if="locationHistory.length > 0" class="card">
      <h3 class="font-semibold text-navy mb-4">ประวัติการสแกน</h3>
      <div class="space-y-3">
        <div v-for="loc in locationHistory" :key="loc.id" 
             class="flex items-center gap-4 p-3 rounded-lg"
             :class="loc.scan_type === 'office_scan' ? 'bg-green-50' : 'bg-blue-50'">
          <div :class="[
            'w-10 h-10 rounded-full flex items-center justify-center',
            loc.scan_type === 'office_scan' ? 'bg-green-100' : 'bg-blue-100'
          ]">
            <span :class="loc.scan_type === 'office_scan' ? 'text-green-600' : 'text-blue-600'">
              {{ loc.scan_type === 'office_scan' ? '🏢' : '📍' }}
            </span>
          </div>
          <div class="flex-1">
            <p class="font-medium text-navy">
              {{ loc.scan_type === 'office_scan' ? 'สแกนที่ออฟฟิศ' : 'สแกนนอกสถานที่' }}
            </p>
            <p class="text-sm text-gray-500">{{ loc.location_name }}</p>
            <p v-if="loc.custom_name" class="text-sm text-blue-600">{{ loc.custom_name }}</p>
          </div>
          <div class="text-right">
            <p class="font-medium text-navy">{{ formatTime(loc.time) }}</p>
            <p v-if="loc.accuracy" class="text-xs text-gray-400">±{{ loc.accuracy }}m</p>
          </div>
          <button v-if="loc.scan_type === 'remote_scan'" 
                  @click="editLocationName(loc)"
                  class="text-blue-500 hover:text-blue-600 text-sm">
            แก้ไขชื่อ
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Location Name Modal -->
    <div v-if="editingLocation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-navy mb-4">แก้ไขชื่อสถานที่</h3>
        <input v-model="editForm.custom_name" type="text" class="input-field mb-4" placeholder="ชื่อสถานที่" />
        <div class="flex justify-end gap-3">
          <button @click="editingLocation = null" class="btn-secondary">ยกเลิก</button>
          <button @click="saveLocationName" class="btn-primary">บันทึก</button>
        </div>
      </div>
    </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import api from '../../services/api'
import L from 'leaflet'
import AppLayout from '@/layouts/AppLayout.vue'

const mapContainer = ref(null)
const remoteEmployees = ref([])
const selectedEmployeeId = ref('')
const selectedDate = ref(new Date().toISOString().split('T')[0])
const locationHistory = ref([])
const refreshing = ref(false)
const autoRefresh = ref(false)
const editingLocation = ref(null)
const editForm = ref({ custom_name: '' })

let map = null
let markers = []
let polyline = null
let refreshInterval = null

const greenIcon = L.divIcon({
  className: 'custom-marker',
  html: '<div style="background:#22c55e;width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.3)"></div>',
  iconSize: [12, 12],
  iconAnchor: [6, 6],
})

const blueIcon = L.divIcon({
  className: 'custom-marker',
  html: '<div style="background:#3b82f6;width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.3)"></div>',
  iconSize: [12, 12],
  iconAnchor: [6, 6],
})

onMounted(async () => {
  await nextTick()
  initMap()
  loadRemoteEmployees()
  
  watch(autoRefresh, (val) => {
    if (val) {
      refreshInterval = setInterval(refreshLocations, 30000)
    } else {
      clearInterval(refreshInterval)
    }
  })
})

onUnmounted(() => {
  clearInterval(refreshInterval)
  if (map) map.remove()
})

function initMap() {
  map = L.map(mapContainer.value).setView([13.7563, 100.5018], 6)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
}

async function loadRemoteEmployees() {
  try {
    const res = await api.get('/api/remote/realtime-locations')
    remoteEmployees.value = res.data.data || []
    updateMapMarkers(remoteEmployees.value)
  } catch (err) {
    console.error(err)
  }
}

async function refreshLocations() {
  refreshing.value = true
  await loadRemoteEmployees()
  if (selectedEmployeeId.value) {
    await loadHistory()
  }
  refreshing.value = false
}

async function loadHistory() {
  if (!selectedEmployeeId.value) return
  
  try {
    const res = await api.get(`/api/remote/location-history/${selectedEmployeeId.value}`, {
      params: { date: selectedDate.value }
    })
    locationHistory.value = res.data.data || []
    updateMapWithHistory(locationHistory.value)
  } catch (err) {
    console.error(err)
  }
}

function updateMapMarkers(locations) {
  markers.forEach(m => map.removeLayer(m))
  markers = []
  
  if (polyline) {
    map.removeLayer(polyline)
    polyline = null
  }

  const points = []
  
  locations.forEach(loc => {
    if (loc.latitude && loc.longitude) {
      const marker = L.marker([loc.latitude, loc.longitude], { icon: blueIcon })
        .bindPopup(`
          <b>${loc.employee_name}</b><br/>
          ${loc.location_name}<br/>
          <small>${new Date(loc.last_scan_time).toLocaleString('th-TH')}</small>
        `)
        .addTo(map)
      markers.push(marker)
      points.push([loc.latitude, loc.longitude])
    }
  })

  if (points.length > 0) {
    map.fitBounds(points, { padding: [50, 50] })
  }
}

function updateMapWithHistory(history) {
  markers.forEach(m => map.removeLayer(m))
  markers = []
  
  if (polyline) {
    map.removeLayer(polyline)
    polyline = null
  }

  const points = []
  
  history.forEach((loc, i) => {
    if (loc.latitude && loc.longitude) {
      const icon = loc.scan_type === 'office_scan' ? greenIcon : blueIcon
      const marker = L.marker([loc.latitude, loc.longitude], { icon })
        .bindPopup(`
          <b>${loc.scan_type === 'office_scan' ? '🏢 ออฟฟิศ' : '📍 นอกสถานที่'}</b><br/>
          ${loc.location_name}<br/>
          <small>${formatTime(loc.time)}</small>
        `)
        .addTo(map)
      markers.push(marker)
      points.push([loc.latitude, loc.longitude])
    }
  })

  if (points.length > 1) {
    polyline = L.polyline(points, { color: '#3b82f6', weight: 3, dashArray: '5,10' }).addTo(map)
  }

  if (points.length > 0) {
    map.fitBounds(points, { padding: [50, 50] })
  }
}

function editLocationName(loc) {
  editingLocation.value = loc
  editForm.value.custom_name = loc.custom_name || ''
}

async function saveLocationName() {
  if (!editingLocation.value) return
  
  try {
    await api.put(`/api/remote/location-name/${editingLocation.value.id}`, {
      custom_name: editForm.value.custom_name
    })
    editingLocation.value.custom_name = editForm.value.custom_name
    editingLocation.value = null
    loadHistory()
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

function formatTime(t) {
  if (!t) return '-'
  return new Date(t).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.custom-marker {
  background: transparent;
  border: none;
}
</style>