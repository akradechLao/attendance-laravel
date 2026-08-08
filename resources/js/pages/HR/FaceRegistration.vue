<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex items-center gap-4">
        <router-link to="/employees" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
          <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-navy">ลงทะเบียนใบหน้า</h1>
          <p class="text-gray-500">ถ่ายภาพใบหน้า 5 ตำแหน่ง</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <LoadingSpinner />
      </div>

      <template v-else>
        <!-- Employee Info -->
        <div class="card">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center">
              <span class="text-blue-600 text-2xl font-bold">{{ employee?.name?.charAt(0) }}</span>
            </div>
            <div>
              <h2 class="text-xl font-semibold text-navy">{{ employee?.name }}</h2>
              <p class="text-gray-500">{{ employee?.code }} | {{ employee?.company?.name }}</p>
            </div>
          </div>
        </div>

        <!-- Progress -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-navy">ความคืบหน้า</h3>
            <span class="text-sm text-gray-500">{{ capturedPhotos.length }}/5 ภาพ</span>
          </div>
          <div class="flex gap-2">
            <div
              v-for="(pos, index) in photoPositions"
              :key="index"
              :class="[
                'flex-1 h-2 rounded-full transition-colors duration-300',
                capturedPhotos.length > index ? 'bg-green-500' : 'bg-gray-200'
              ]"
            ></div>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>ตรงหน้า</span>
            <span>ซ้าย 45°</span>
            <span>ขวา 45°</span>
            <span>มองขึ้น</span>
            <span>มองลง</span>
          </div>
        </div>

        <!-- Camera Section -->
        <div class="card">
          <div class="text-center mb-4">
            <h3 class="font-semibold text-navy text-lg">{{ currentPositionLabel }}</h3>
            <p class="text-gray-500 text-sm">กรุณาจัดตำแหน่งใบหน้าตามที่กำหนด</p>
          </div>

          <div class="relative max-w-md mx-auto">
            <Camera
              v-if="!capturing"
              @captured="handlePhotoCaptured"
              ref="cameraRef"
            />

            <!-- Capturing overlay -->
            <div v-if="capturing" class="relative">
              <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden flex items-center justify-center">
                <LoadingSpinner />
              </div>
            </div>
          </div>

          <!-- Capture button -->
          <div v-if="!capturing && capturedPhotos.length < 5" class="text-center mt-6">
            <button
              @click="capturePhoto"
              class="w-20 h-20 rounded-full bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center mx-auto transition-all duration-200 hover:scale-105 animate-pulse-glow"
            >
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
            <p class="text-sm text-gray-500 mt-2">กดเพื่อถ่ายภาพ</p>
          </div>
        </div>

        <!-- Photo Preview -->
        <div v-if="capturedPhotos.length > 0" class="card">
          <h3 class="font-semibold text-navy mb-4">ภาพที่ถ่ายแล้ว</h3>
          <div class="grid grid-cols-5 gap-4">
            <div
              v-for="(photo, index) in capturedPhotos"
              :key="index"
              class="text-center"
            >
              <img
                :src="photo.data"
                :alt="photoPositions[index]"
                class="w-full aspect-square object-cover rounded-lg border-2 border-green-500"
              />
              <p class="text-xs text-gray-500 mt-1">{{ photoPositions[index] }}</p>
            </div>
          </div>
        </div>

        <!-- Register Button -->
        <div v-if="capturedPhotos.length === 5" class="card">
          <div class="text-center">
            <p class="text-green-600 font-medium mb-4">✓ ถ่ายภาพครบทั้ง 5 ตำแหน่งแล้ว</p>
            <button
              @click="registerFace"
              :disabled="registering"
              class="btn-success text-lg px-8 py-3"
            >
              {{ registering ? 'กำลังบันทึก...' : 'บันทึกลงทะเบียนใบหน้า' }}
            </button>
          </div>
        </div>

        <!-- Success Modal -->
        <Modal :show="showSuccess" @close="goBack" title="สำเร็จ">
          <div class="text-center py-4">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
              <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <p class="text-lg font-medium text-navy">ลงทะเบียนใบหน้าสำเร็จ</p>
            <p class="text-gray-500 mt-2">ระบบได้บันทึกข้อมูลใบหน้าเรียบร้อยแล้ว</p>
          </div>
          <div class="flex justify-center mt-4">
            <button @click="goBack" class="btn-primary">กลับไปรายการพนักงาน</button>
          </div>
        </Modal>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppLayout from '../../layouts/AppLayout.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Camera from '../../components/Camera.vue'
import Modal from '../../components/Modal.vue'

const route = useRoute()
const router = useRouter()
const cameraRef = ref(null)

const loading = ref(true)
const capturing = ref(false)
const registering = ref(false)
const showSuccess = ref(false)
const employee = ref(null)
const capturedPhotos = ref([])

const photoPositions = ['ตรงหน้า', 'ซ้าย 45°', 'ขวา 45°', 'มองขึ้น', 'มองลง']

const currentPositionLabel = computed(() => {
  const index = capturedPhotos.value.length
  return index < 5 ? `ตำแหน่งที่ ${index + 1}: ${photoPositions[index]}` : 'ถ่ายภาพครบทุกตำแหน่งแล้ว'
})

async function fetchEmployee() {
  try {
    const response = await axios.get(`/api/employees/${route.params.id}`)
    employee.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching employee:', error)
  } finally {
    loading.value = false
  }
}

function capturePhoto() {
  if (cameraRef.value) {
    capturing.value = true
    cameraRef.value.capture()
  }
}

function handlePhotoCaptured(imageData) {
  capturedPhotos.value.push({
    data: imageData,
    position: photoPositions[capturedPhotos.value.length]
  })
  capturing.value = false
}

async function registerFace() {
  registering.value = true
  try {
    await axios.post(`/api/employees/${route.params.id}/face`, {
      images: capturedPhotos.value.map(p => p.data)
    })
    showSuccess.value = true
  } catch (error) {
    console.error('Error registering face:', error)
    alert('เกิดข้อผิดพลาดในการลงทะเบียนใบหน้า')
  } finally {
    registering.value = false
  }
}

function goBack() {
  router.push('/employees')
}

onMounted(fetchEmployee)
</script>
