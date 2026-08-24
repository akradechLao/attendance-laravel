<template>
  <div class="relative">
    <video
      ref="videoRef"
      autoplay
      playsinline
      :class="['w-full aspect-video rounded-xl bg-gray-900 object-cover', { 'hidden': photoTaken }]"
      style="transform: scaleX(-1)"
    ></video>

    <img
      v-if="photoTaken"
      :src="capturedImage"
      class="w-full aspect-video rounded-xl object-cover"
      style="transform: scaleX(-1)"
      alt="Captured photo"
    />

    <!-- Camera controls (only when not hidden by parent) -->
    <div v-if="!photoTaken && !hideControls" class="absolute bottom-4 left-0 right-0 flex justify-center gap-4">
      <button
        @click="toggleCamera"
        class="p-3 bg-white/80 rounded-full hover:bg-white transition-colors"
        title="สลับกล้อง"
      >
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>

    <!-- Retake button (only when not hidden by parent) -->
    <div v-if="photoTaken && !hideControls" class="absolute bottom-4 left-0 right-0 flex justify-center">
      <button
        @click="retake"
        class="px-4 py-2 bg-white/80 rounded-full hover:bg-white transition-colors text-gray-700 font-medium"
      >
        ถ่ายใหม่
      </button>
    </div>

    <!-- No camera message -->
    <div v-if="noCamera" class="absolute inset-0 flex items-center justify-center bg-gray-900 rounded-lg">
      <div class="text-center text-white">
        <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <p class="text-sm">ไม่พบกล้อง</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  hideControls: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['captured'])

const videoRef = ref(null)
const capturedImage = ref('')
const photoTaken = ref(false)
const noCamera = ref(false)
const facingMode = ref('user')

let stream = null

async function startCamera() {
  try {
    const constraints = {
      video: {
        facingMode: facingMode.value,
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    }
    stream = await navigator.mediaDevices.getUserMedia(constraints)
    if (videoRef.value) {
      videoRef.value.srcObject = stream
    }
    noCamera.value = false
  } catch (error) {
    console.error('Error accessing camera:', error)
    noCamera.value = true
  }
}

function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop())
    stream = null
  }
}

function toggleCamera() {
  stopCamera()
  facingMode.value = facingMode.value === 'user' ? 'environment' : 'user'
  startCamera()
}

function capture() {
  if (!videoRef.value) return

  const canvas = document.createElement('canvas')
  canvas.width = videoRef.value.videoWidth
  canvas.height = videoRef.value.videoHeight
  const ctx = canvas.getContext('2d')
  ctx.drawImage(videoRef.value, 0, 0)
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.8)
  photoTaken.value = true
  emit('captured', capturedImage.value)
}

function retake() {
  photoTaken.value = false
  capturedImage.value = ''
  startCamera()
}

defineExpose({ capture, retake })

onMounted(startCamera)
onUnmounted(stopCamera)
</script>
