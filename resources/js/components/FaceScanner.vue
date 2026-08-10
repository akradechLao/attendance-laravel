<template>
  <div class="space-y-4">
    <!-- Camera -->
    <div class="relative max-w-md mx-auto">
      <video
        ref="videoRef"
        autoplay
        playsinline
        class="w-full aspect-video rounded-lg bg-gray-900 object-cover"
      ></video>

      <!-- Scanning overlay -->
      <div v-if="scanning" class="absolute inset-0 rounded-lg overflow-hidden">
        <div class="absolute inset-0 bg-blue-500/10"></div>
        <div class="absolute left-0 right-0 h-1 bg-blue-500 animate-scan-line"></div>
        <div class="absolute inset-4 border-2 border-blue-500 rounded-lg animate-pulse-glow"></div>
      </div>

      <!-- No camera -->
      <div v-if="noCamera" class="absolute inset-0 flex items-center justify-center bg-gray-900 rounded-lg">
        <div class="text-center text-white">
          <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <p class="text-sm">ไม่พบกล้อง</p>
        </div>
      </div>
    </div>

    <!-- Status -->
    <div class="text-center">
      <p v-if="scanning" class="text-blue-500 font-medium animate-pulse">
        กำลังสแกนใบหน้า...
      </p>
      <p v-else-if="verified" class="text-green-500 font-medium">
        ✓ ยืนยันตัวตนสำเร็จ
      </p>
      <p v-else-if="failed" class="text-red-500 font-medium">
        ✗ ไม่สามารถยืนยันตัวตนได้
      </p>
      <p v-else class="text-gray-500">
        กรุณาหันหน้าเข้าหากล้อง
      </p>
    </div>

    <!-- Scan button -->
    <div v-if="!scanning && !verified" class="text-center">
      <button
        @click="startScan"
        :disabled="noCamera"
        class="btn-primary text-lg px-8 py-3"
      >
        เริ่มสแกน
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  employeeId: {
    type: [Number, String],
    required: true
  },
  scanType: {
    type: String,
    default: 'office_scan'
  },
  scanMode: {
    type: String,
    default: 'check_in'
  },
  latitude: {
    type: Number,
    default: null
  },
  longitude: {
    type: Number,
    default: null
  },
  accuracy: {
    type: Number,
    default: null
  },
  customLocationName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['verified', 'failed', 'error'])

const videoRef = ref(null)
const scanning = ref(false)
const verified = ref(false)
const failed = ref(false)
const noCamera = ref(false)

let stream = null

async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: 'user',
        width: { ideal: 640 },
        height: { ideal: 480 }
      }
    })
    if (videoRef.value) {
      videoRef.value.srcObject = stream
    }
    noCamera.value = false
  } catch (error) {
    console.error('Error accessing camera:', error)
    noCamera.value = true
    emit('error', 'ไม่พบกล้อง กรุณาตรวจสอบการเชื่อมต่อ')
  }
}

function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop())
    stream = null
  }
}

function captureImage() {
  if (!videoRef.value) return null

  const canvas = document.createElement('canvas')
  canvas.width = videoRef.value.videoWidth
  canvas.height = videoRef.value.videoHeight
  const ctx = canvas.getContext('2d')
  ctx.drawImage(videoRef.value, 0, 0)
  return canvas.toDataURL('image/jpeg', 0.8)
}

async function startScan() {
  if (scanning.value) return

  scanning.value = true
  failed.value = false

  try {
    const imageData = captureImage()
    if (!imageData) {
      throw new Error('ไม่สามารถถ่ายภาพได้')
    }

    const response = await axios.post('/api/face/verify', {
      employee_id: props.employeeId,
      image: imageData,
      type: props.scanMode,
      latitude: props.latitude,
      longitude: props.longitude,
      accuracy: props.accuracy,
      custom_location_name: props.customLocationName,
    })

    if (response.data.success) {
      verified.value = true
      emit('verified', response.data)
    } else {
      failed.value = true
      emit('failed', response.data?.message)
    }
  } catch (error) {
    console.error('Error verifying face:', error)
    const msg = error.response?.data?.message || 'เกิดข้อผิดพลาดในการสแกน'
    failed.value = true
    emit('error', msg)
  } finally {
    scanning.value = false
  }
}

onMounted(startCamera)
onUnmounted(stopCamera)
</script>
