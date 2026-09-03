<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-100 via-indigo-50 to-purple-100 flex items-center justify-center p-3 sm:p-4 safe-area">
    <!-- Employee Login Button -->
    <a
      v-if="!showAdminLogin"
      href="/login"
      class="fixed top-3 left-3 sm:top-4 sm:left-4 z-50 px-3 py-1.5 sm:px-4 sm:py-2 bg-blue-500/90 backdrop-blur-sm rounded-lg shadow-sm border border-blue-600 text-white hover:bg-blue-600 hover:shadow-md transition-all text-xs sm:text-sm font-medium flex items-center gap-1.5"
    >
      <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
      </svg>
      เข้าสู่ระบบ
    </a>

    <!-- Admin Login Button -->
    <button
      v-if="!showAdminLogin"
      @click="showAdminLogin = true"
      class="fixed top-3 right-3 sm:top-4 sm:right-4 z-50 px-3 py-1.5 sm:px-4 sm:py-2 bg-white/80 backdrop-blur-sm rounded-lg shadow-sm border border-gray-200 text-gray-600 hover:bg-white hover:shadow-md transition-all text-xs sm:text-sm font-medium flex items-center gap-1.5"
    >
      <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
      HR / Admin
    </button>

    <!-- Admin Login Modal -->
    <div v-if="showAdminLogin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 animate-fadeIn">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold text-navy">เข้าสู่ระบบ HR / Admin</h3>
          <button @click="showAdminLogin = false; adminError = ''" class="p-1 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="adminError" class="mb-4 p-3 bg-red-50 rounded-lg border border-red-200">
          <p class="text-red-600 text-sm">{{ adminError }}</p>
        </div>

        <form @submit.prevent="handleAdminLogin" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้ใช้</label>
            <input v-model="adminForm.username" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="กรอกชื่อผู้ใช้" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่าน</label>
            <input v-model="adminForm.password" type="password" autocomplete="current-password" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="กรอกรหัสผ่าน" required />
          </div>
          <button type="submit" :disabled="adminLoading" class="w-full bg-navy hover:bg-slate-800 disabled:bg-navy/50 text-white py-2.5 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2">
            <span v-if="adminLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ adminLoading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}</span>
          </button>
        </form>
      </div>
    </div>

    <div class="w-full max-w-lg">
      <!-- Header -->
      <div class="text-center mb-6 sm:mb-8">
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-blue-500/10 px-6 py-3 sm:px-8 sm:py-4 mb-3 sm:mb-4 inline-block">
          <p class="text-xl sm:text-2xl font-bold text-navy tabular-nums">{{ currentTime }}</p>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-navy mb-1 sm:mb-2">ระบบเช็คเวลาเข้า-ออกงาน</h1>
        <p class="text-sm sm:text-base text-blue-600 font-medium">ETC Group</p>
      </div>

      <!-- Offline Queue Status Bar -->
      <Transition name="slide-down">
        <div v-if="queuedCount > 0" class="mb-4 animate-fadeIn">
          <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-300 rounded-xl px-4 py-3 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
                <span class="text-sm text-amber-800 font-medium">
                  ข้อมูล {{ queuedCount }} รายการ รอส่งให้ระบบฯ ส่วนกลาง
                </span>
              </div>
              <button
                @click="syncQueuedData"
                :disabled="syncingQueue"
                class="ml-3 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 text-white text-xs font-medium rounded-lg transition-colors flex items-center gap-1.5"
              >
                <svg v-if="!syncingQueue" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span v-if="syncingQueue" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                {{ syncingQueue ? 'กำลังส่ง...' : 'ส่งตอนนี้' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Step 1: Company Selection -->
      <div v-if="step === 1" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <h2 class="text-lg sm:text-xl font-semibold text-navy mb-4 sm:mb-6 text-center">เลือกบริษัท</h2>
          <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <button
              v-for="company in companies"
              :key="company.id"
              @click="selectCompany(company)"
              class="company-btn p-4 sm:p-6 rounded-xl transition-all duration-200 text-center group touch-target"
              :style="companyStyles[company.code_prefix] || 'background: linear-gradient(135deg, #64748b, #334155); color: white; border: 2px solid rgba(100,116,139,0.5);'"
            >
              <div class="w-14 h-14 sm:w-20 sm:h-20 mx-auto mb-2 sm:mb-3 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110 group-active:scale-105 overflow-hidden">
                <img
                  v-if="company.logo_url"
                  :src="company.logo_url"
                  :alt="company.name"
                  class="w-full h-full object-contain p-1"
                />
                <span v-else class="text-white text-xl sm:text-2xl font-bold">{{ company.name.charAt(0) }}</span>
              </div>
              <p class="font-bold text-sm sm:text-base">{{ company.name }}</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Search Employee -->
      <div v-if="step === 2" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 1" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <h2 class="text-lg sm:text-xl font-semibold text-navy">{{ selectedCompany?.name }}</h2>
          </div>

          <div class="mb-4 sm:mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">ค้นหาพนักงาน</label>
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                inputmode="text"
                class="input-field text-base sm:text-lg py-3"
                placeholder="พิมพ์ชื่อหรือรหัสพนักงาน..."
                @focus="$event.target.select()"
                autofocus
              />
              <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <div v-if="!searchQuery" class="text-center py-6 sm:py-8 text-gray-400 text-sm sm:text-base">
            พิมพ์ชื่อหรือรหัสพนักงานเพื่อค้นหา
          </div>

          <div v-else-if="filteredEmployees.length > 0" class="space-y-2 max-h-[50vh] sm:max-h-80 overflow-y-auto custom-scrollbar">
            <div
              v-for="emp in filteredEmployees"
              :key="emp.id"
              class="flex items-center gap-2"
            >
              <button
                @click="selectEmployee(emp)"
                class="flex-1 p-3 sm:p-4 rounded-xl border border-blue-100 bg-white hover:border-blue-400 hover:bg-blue-50 hover:shadow-md active:bg-blue-100 transition-all duration-200 flex items-center gap-3 sm:gap-4 text-left touch-target"
              >
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shrink-0 shadow-sm">
                  <span class="text-white font-bold text-sm sm:text-base">{{ emp.name.charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-navy text-sm sm:text-base truncate">{{ emp.name }}</p>
                  <p class="text-xs sm:text-sm text-gray-500">{{ emp.employee_code }} | {{ emp.department }}</p>
                </div>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
              <button
                v-if="emp.face_data_count > 0"
                @click.stop="confirmReregister(emp)"
                class="shrink-0 w-10 h-10 sm:w-12 sm:h-12 rounded-xl border border-orange-200 bg-orange-50 hover:bg-orange-100 active:bg-orange-200 transition-all duration-200 flex items-center justify-center touch-target"
                title="ลงทะเบียนใบหน้าใหม่"
              >
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>
            </div>
          </div>

          <div v-else-if="searchQuery && !loading" class="text-center py-6 sm:py-8 text-gray-500 text-sm sm:text-base">
            ไม่พบพนักงานที่ค้นหา
          </div>
        </div>
      </div>

      <!-- Step 2.5: Scan Type Selection (for remote employees) -->
      <div v-if="step === 2.5" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 2" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <h2 class="text-lg sm:text-xl font-semibold text-navy">เลือกวิธีสแกน</h2>
          </div>

          <div class="text-center mb-4 sm:mb-6">
            <p class="text-base sm:text-lg font-semibold text-navy">{{ selectedEmployee?.name }}</p>
            <p class="text-sm text-gray-500">{{ selectedEmployee?.employee_code }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <button
              @click="startOfficeScan"
              class="p-4 sm:p-6 rounded-xl border-2 border-green-200 bg-gradient-to-b from-green-50 to-white hover:from-green-100 hover:border-green-400 active:from-green-200 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">🏢</div>
              <p class="font-bold text-navy text-sm sm:text-base">สแกนที่ออฟฟิศ</p>
              <p class="text-xs sm:text-sm text-green-600">Check-in ตามปกติ</p>
            </button>
            <button
              @click="startRemoteScan"
              class="p-4 sm:p-6 rounded-xl border-2 border-blue-200 bg-gradient-to-b from-blue-50 to-white hover:from-blue-100 hover:border-blue-400 active:from-blue-200 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">📍</div>
              <p class="font-bold text-navy text-sm sm:text-base">สแกนนอกสถานที่</p>
              <p class="text-xs sm:text-sm text-blue-600">ระหว่างเดินทาง</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2.7: Face Registration (self-service) -->
      <div v-if="step === 2.7" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <!-- Top bar -->
          <div class="flex items-center justify-between mb-3">
            <button @click="step = 2" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <div class="text-center">
              <p class="text-sm sm:text-base font-semibold text-navy">{{ selectedEmployee?.name }}</p>
              <p class="text-xs text-gray-500">{{ selectedEmployee?.employee_code }}</p>
            </div>
            <div class="w-16"></div>
          </div>

          <!-- Progress dots (clickable to re-take) -->
          <div class="flex gap-1.5 mb-3">
            <button v-for="(pos, i) in 5" :key="i"
              @click="clickFaceRegDot(i)"
              :class="[
                'flex-1 h-2 rounded-full transition-all duration-300 cursor-pointer hover:opacity-80',
                faceRegResults[i] === 'success' ? 'bg-green-500' :
                faceRegResults[i] === 'error' ? 'bg-red-400' :
                i === faceRegCurrentPosition ? 'bg-blue-400 animate-pulse' :
                'bg-gray-200'
              ]"
              :title="faceRegPositions[i]"
            ></button>
          </div>

          <!-- Current position text -->
          <p class="text-center text-sm font-medium mb-3" :class="faceRegDetecting ? 'text-blue-600' : 'text-gray-600'">
            <template v-if="faceRegAllDone">
              ✅ เก็บข้อมูลเรียบร้อยทุกรูป
            </template>
            <template v-else-if="faceRegDetecting">
              <span class="inline-flex items-center gap-1">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                กำลังตรวจสอบใบหน้า...
              </span>
            </template>
            <template v-else-if="faceRegDetectError">
              <span class="text-red-500">{{ faceRegDetectError }}</span>
            </template>
            <template v-else>
              ตำแหน่งที่ {{ faceRegCurrentPosition + 1 }}: {{ faceRegPositions[faceRegCurrentPosition] }}
              <span v-if="faceRegResults[faceRegCurrentPosition] === 'success'" class="text-green-500 ml-1">(ถ่ายใหม่)</span>
            </template>
          </p>

          <!-- Camera / Captured preview (full width) -->
          <div v-if="!faceRegAllDone" class="relative mb-4">
            <Camera v-if="!faceRegCapturing" ref="faceRegCameraRef" hide-controls @captured="handleFaceRegCaptured" />

            <div v-if="faceRegCapturing" class="aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
              <LoadingSpinner />
            </div>

            <!-- Capture button - big and prominent -->
            <div class="flex justify-center mt-4">
              <button
                v-if="!faceRegCapturing && !faceRegDetecting"
                @click="captureFaceRegPhoto"
                class="w-20 h-20 rounded-full bg-gradient-to-br from-red-500 to-red-600 text-white flex items-center justify-center shadow-xl shadow-red-500/40 active:scale-95 transition-all duration-150 border-4 border-white"
              >
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </button>
            </div>

            <!-- Retake button (only on error) -->
            <div v-if="faceRegDetectError && !faceRegDetecting" class="flex justify-center mt-3">
              <button @click="retakeFaceRegPhoto" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-gray-700 font-medium text-sm transition-colors">
                ถ่ายใหม่
              </button>
            </div>
          </div>

          <!-- All 5 done - submit -->
          <div v-if="faceRegAllDone" class="text-center mt-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
              <p class="text-green-600 font-medium text-sm">✅ เก็บข้อมูลเรียบร้อยทุกรูป</p>
            </div>
            <button
              @click="registerFace"
              :disabled="faceRegRegistering"
              class="btn-primary text-base px-6 py-3 touch-target"
            >
              {{ faceRegRegistering ? 'กำลังบันทึก...' : 'บันทึกลงทะเบียนใบหน้า' }}
            </button>
          </div>

          <div v-if="scanningError" class="mt-3 p-3 bg-red-50 rounded-lg text-center">
            <p class="text-red-600 text-sm">{{ scanningError }}</p>
          </div>
        </div>
      </div>

      <!-- Step 3: Face Recognition -->
      <div v-if="step === 3" class="animate-fadeIn">
        <div class="card p-3 sm:p-5">
          <!-- Top bar: back + title + employee name -->
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <button @click="step = 2" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <div class="text-center">
              <p class="text-sm sm:text-base font-semibold text-navy">{{ selectedEmployee?.name }}</p>
              <p class="text-xs text-gray-500">{{ selectedEmployee?.employee_code }}</p>
            </div>
            <div class="w-16"></div>
          </div>

          <!-- Location input for remote scan -->
          <div v-if="scanType === 'remote_scan'" class="mb-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานที่ (ไม่บังคับ)</label>
            <input v-model="customLocationName" type="text" inputmode="text" class="input-field text-base" placeholder="เช่น โรงแรมABC, สำนักงานลูกค้า" />
          </div>

          <!-- Map + GPS Status for office scan -->
          <div v-if="scanType === 'office_scan' && officeLocation" class="mb-2">
            <!-- GPS Status Bar -->
            <div class="flex items-center justify-between bg-white rounded-xl p-2 shadow-sm border border-gray-100 mb-1.5">
              <div class="flex items-center gap-2">
                <div v-if="gpsStatus === 'acquiring'" class="w-2.5 h-2.5 rounded-full bg-yellow-400 animate-pulse"></div>
                <div v-else-if="gpsStatus === 'found' && gpsReady" class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                <div v-else-if="gpsStatus === 'found' && !gpsReady" class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></div>
                <div v-else class="w-2.5 h-2.5 rounded-full bg-gray-400"></div>
                <span class="text-xs font-medium text-gray-600">
                  <template v-if="gpsStatus === 'acquiring'">กำลังระบุตำแหน่ง...</template>
                  <template v-else-if="gpsStatus === 'found' && distanceToOffice !== null">
                    ห่าง {{ Math.round(distanceToOffice) }} ม.
                    <span v-if="gpsReady" class="text-green-600">(ในรัศมี)</span>
                    <span v-else class="text-red-600">(เกินรัศมี)</span>
                  </template>
                  <template v-else-if="gpsStatus === 'error'">ไม่สามารถระบุตำแหน่งได้</template>
                </span>
              </div>
              <span class="text-[10px] text-gray-400">รัศมี {{ officeLocation.radius_meters }}ม.</span>
            </div>
            <!-- Map -->
            <div ref="mapContainer" class="w-full h-28 sm:h-36 rounded-xl overflow-hidden shadow-sm border border-gray-200"></div>
          </div>

          <!-- Camera Area -->
          <div class="relative mb-2 sm:mb-3">
            <FaceScanner
              :employee-id="selectedEmployee?.id"
              :scan-type="scanType"
              :scan-mode="scanMode"
              :trigger-scan="triggerScan"
              :current-latitude="currentLatitude"
              :current-longitude="currentLongitude"
              @verified="handleVerified"
              @failed="handleFailed"
              @error="handleError"
            />
          </div>

          <!-- Scan Button -->
          <div v-if="!triggerScan" class="mb-2">
            <button
              v-if="scanType === 'office_scan' && officeLocation"
              @click="triggerScan = true"
              :disabled="!gpsReady"
              :class="gpsReady
                ? 'from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 shadow-lg cursor-pointer'
                : 'from-gray-300 to-gray-400 cursor-not-allowed shadow-none'"
              class="w-full py-3 sm:py-4 rounded-xl bg-gradient-to-r text-white font-bold text-base sm:text-lg active:scale-95 transition-all touch-target"
            >
              {{ gpsReady ? 'สแกนใบหน้าเพื่อยืนยันตัวตน' : 'กรุณาเข้าใกล้สถานที่เช็คอิน' }}
            </button>
            <button
              v-else
              @click="triggerScan = true"
              class="w-full py-3 sm:py-4 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-bold text-base sm:text-lg shadow-lg active:scale-95 transition-all touch-target"
            >
              สแกนใบหน้าเพื่อยืนยันตัวตน
            </button>
          </div>

          <div v-if="scanningError" class="p-2 sm:p-3 bg-red-50 rounded-lg text-center">
            <p class="text-red-600 text-xs sm:text-sm">{{ scanningError }}</p>
            <button @click="retryScan" class="mt-2 text-blue-500 active:text-blue-600 font-medium text-sm touch-target">
              ลองใหม่
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3.5: Action Selection Menu (after face verify) -->
      <div v-if="step === 3.5" class="animate-fadeIn">
        <div class="card p-4 sm:p-6">
          <!-- Top bar -->
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <button @click="step = 3; triggerScan = false; capturedImage = null" class="text-blue-500 active:text-blue-600 flex items-center gap-1 touch-target">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              <span class="text-sm sm:text-base">กลับ</span>
            </button>
            <div class="text-center">
              <p class="text-sm sm:text-base font-semibold text-navy">{{ selectedEmployee?.name }}</p>
              <p class="text-xs text-green-600 font-medium">✓ ยืนยันตัวตนสำเร็จ</p>
            </div>
            <div class="w-16"></div>
          </div>

          <h2 class="text-lg sm:text-xl font-semibold text-navy mb-4 sm:mb-6 text-center">เลือกรายการ</h2>

          <!-- PDPA Consent for remote scan -->
          <div v-if="scanType === 'remote_scan'" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" v-model="pdpaConsent" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span class="text-xs sm:text-sm text-gray-700 leading-relaxed">
                ข้าพเจ้า consent ให้บริษัทบันทึกข้อมูลตำแหน่ง (GPS) ของข้าพเจ้าเฉพาะขณะปฏิบัติงานนอกสถานที่ เพื่อวัตถุประสงค์ในการยืนยันตำแหน่งปฏิบัติงานเท่านั้น โดยข้อมูลจะถูกเก็บรักษาตามนโยบาย PDPA ของบริษัท
              </span>
            </label>
          </div>

          <!-- GPS Status for office scan -->
          <div v-if="scanType === 'office_scan' && officeLocation" class="mb-3">
            <div class="flex items-center justify-between bg-white rounded-xl p-2 shadow-sm border border-gray-100">
              <div class="flex items-center gap-2">
                <div v-if="gpsStatus === 'acquiring'" class="w-2.5 h-2.5 rounded-full bg-yellow-400 animate-pulse"></div>
                <div v-else-if="gpsStatus === 'found' && gpsReady" class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                <div v-else-if="gpsStatus === 'found' && !gpsReady" class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></div>
                <div v-else class="w-2.5 h-2.5 rounded-full bg-gray-400"></div>
                <span class="text-xs font-medium text-gray-600">
                  <template v-if="gpsStatus === 'acquiring'">กำลังระบุตำแหน่ง...</template>
                  <template v-else-if="gpsStatus === 'found' && distanceToOffice !== null">
                    ห่าง {{ Math.round(distanceToOffice) }} ม.
                    <span v-if="gpsReady" class="text-green-600">(ในรัศมี)</span>
                    <span v-else class="text-red-600">(เกินรัศมี)</span>
                  </template>
                  <template v-else-if="gpsStatus === 'error'">ไม่สามารถระบุตำแหน่งได้</template>
                </span>
              </div>
              <span class="text-[10px] text-gray-400">รัศมี {{ officeLocation.radius_meters }}ม.</span>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <button
              @click="handleActionSelect('check_in')"
              :disabled="actionLoading || (scanType === 'office_scan' && !gpsReady)"
              class="p-4 sm:p-6 rounded-xl border-2 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed
                border-green-200 bg-gradient-to-b from-green-50 to-white hover:from-green-100 hover:border-green-400 active:from-green-200"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">🟢</div>
              <p class="font-bold text-navy text-sm sm:text-base">เข้างาน</p>
              <p class="text-xs sm:text-sm text-green-600">Check-in</p>
            </button>

            <button
              @click="handleActionSelect('check_out')"
              :disabled="actionLoading || (scanType === 'office_scan' && !gpsReady)"
              class="p-4 sm:p-6 rounded-xl border-2 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed
                border-red-200 bg-gradient-to-b from-red-50 to-white hover:from-red-100 hover:border-red-400 active:from-red-200"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">🔴</div>
              <p class="font-bold text-navy text-sm sm:text-base">ออกงาน</p>
              <p class="text-xs sm:text-sm text-red-600">Check-out</p>
            </button>

            <button
              v-if="verifiedEmployeeData?.has_ot"
              @click="handleActionSelect('ot_start')"
              :disabled="actionLoading || (scanType === 'office_scan' && !gpsReady)"
              class="p-4 sm:p-6 rounded-xl border-2 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed
                border-yellow-200 bg-gradient-to-b from-yellow-50 to-white hover:from-yellow-100 hover:border-yellow-400 active:from-yellow-200"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">⚡</div>
              <p class="font-bold text-navy text-sm sm:text-base">ทำโอที</p>
              <p class="text-xs sm:text-sm text-yellow-600">Start OT</p>
            </button>

            <button
              v-if="verifiedEmployeeData?.has_ot"
              @click="handleActionSelect('ot_end')"
              :disabled="actionLoading || (scanType === 'office_scan' && !gpsReady)"
              class="p-4 sm:p-6 rounded-xl border-2 transition-all duration-200 text-center touch-target shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed
                border-purple-200 bg-gradient-to-b from-purple-50 to-white hover:from-purple-100 hover:border-purple-400 active:from-purple-200"
            >
              <div class="text-3xl sm:text-4xl mb-2 sm:mb-3">🏁</div>
              <p class="font-bold text-navy text-sm sm:text-base">ออกโอที</p>
              <p class="text-xs sm:text-sm text-purple-600">End OT</p>
            </button>
          </div>

          <div v-if="scanningError" class="mt-3 p-3 bg-red-50 rounded-lg text-center">
            <p class="text-red-600 text-xs sm:text-sm">{{ scanningError }}</p>
          </div>
        </div>
      </div>

      <!-- Step 4: Result -->
      <div v-if="step === 4" class="animate-fadeIn">
        <div class="card p-4 sm:p-6 text-center py-8 sm:py-12">
          <div
            :class="[
              'w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-4 sm:mb-6 rounded-full flex items-center justify-center shadow-lg',
              result.success ? 'bg-gradient-to-br from-green-500 to-green-700 shadow-green-600/40' : 'bg-gradient-to-br from-red-400 to-rose-600 shadow-red-500/30'
            ]"
          >
            <svg
              v-if="result.success"
              class="w-10 h-10 sm:w-12 sm:h-12 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              stroke-width="3"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg
              v-else
              class="w-10 h-10 sm:w-12 sm:h-12 text-red-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>

          <h2
            :class="[
              'text-xl sm:text-2xl font-bold mb-2 whitespace-pre-line',
              result.success ? 'text-green-700' : 'text-red-600'
            ]"
          >
            {{ result.message }}
          </h2>

          <p class="text-gray-500 mb-2 text-sm sm:text-base">{{ selectedEmployee?.name }}</p>
          <p class="text-gray-400 text-sm mb-2">{{ result.time }}</p>
          <p v-if="result.location" class="text-blue-500 text-sm mb-6 sm:mb-8">📍 {{ result.location }}</p>
          <p v-else class="text-gray-400 text-sm mb-6 sm:mb-8">🏢 ออฟฟิศ</p>

          <button
            @click="reset"
            class="btn-primary text-base sm:text-lg px-6 sm:px-8 py-3 touch-target"
          >
            เริ่มใหม่
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Re-register Modal -->
    <div v-if="showReregisterModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
      <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl animate-fadeIn">
        <div class="text-center">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-orange-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </div>
          <h3 class="text-lg font-bold text-navy mb-2">ลงทะเบียนใบหน้าใหม่?</h3>
          <p class="text-sm text-gray-500 mb-1">{{ reregisterEmployee?.name }}</p>
          <p class="text-xs text-gray-400 mb-6">ข้อมูลใบหน้าเดิมจะถูกลบ และต้องลงทะเบียนใหม่ 5 รูป</p>
          <div class="flex gap-3">
            <button
              @click="showReregisterModal = false"
              class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-600 font-medium hover:bg-gray-50 active:bg-gray-100 transition touch-target"
            >
              ยกเลิก
            </button>
            <button
              @click="doReregister"
              :disabled="reregisterLoading"
              class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-medium hover:bg-orange-600 active:bg-orange-700 transition disabled:opacity-50 touch-target"
            >
              {{ reregisterLoading ? 'กำลังลบ...' : 'ยืนยัน' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import api from '../../services/api'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import FaceScanner from '../../components/FaceScanner.vue'
import Camera from '../../components/Camera.vue'
import { setCurrentUser, setToken } from '../../store'
import offlineQueue from '../../services/offlineQueue'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
})

const router = useRouter()

const step = ref(1)
const selectedCompany = ref(null)
const selectedEmployee = ref(null)
const searchQuery = ref('')
const employees = ref([])
const companies = ref([])
const loading = ref(false)
const scanningError = ref('')
const result = ref({ success: false, message: '', time: '', location: '' })
const scanType = ref('office_scan')
const customLocationName = ref('')
const triggerScan = ref(false)
const currentTime = ref('--:--:--')
const currentDate = ref('')
const serverTimeStr = ref(null)
const fetchTimeLocal = ref(Date.now())

const mapContainer = ref(null)
let map = null
let officeMarker = null
let userMarker = null
let radiusCircle = null
const officeLocation = ref(null)
const currentLatitude = ref(null)
const currentLongitude = ref(null)
const currentAccuracy = ref(null)
const distanceToOffice = ref(null)
const gpsStatus = ref('acquiring')
const gpsReady = computed(() => {
  if (!officeLocation.value || distanceToOffice.value === null) return false
  return distanceToOffice.value <= officeLocation.value.radius_meters
})

const faceRegCameraRef = ref(null)
const faceRegCapturing = ref(false)
const faceRegPhotos = ref([])
const faceRegRegistering = ref(false)
const faceRegPositions = ['มองตรง', 'หันซ้าย', 'หันขวา', 'มองขึ้น', 'มองลง']
const faceRegResults = ref([])
const faceRegDetecting = ref(false)
const faceRegDetectError = ref('')
const faceRegAllDone = ref(false)
const faceRegEncodings = ref([])
const faceRegCurrentPosition = ref(0)

const showReregisterModal = ref(false)
const reregisterEmployee = ref(null)
const reregisterLoading = ref(false)

const queuedCount = ref(0)
const syncingQueue = ref(false)

const scanMode = ref('verify_only')  // 'verify_only', 'check_in', or 'check_out'
const capturedImage = ref(null)
const verifiedEmployeeData = ref(null)
const selectedAction = ref(null)
const actionLoading = ref(false)
const verificationToken = ref(null)
const pdpaConsent = ref(false)

// Admin/HR Login
const showAdminLogin = ref(false)
const adminLoading = ref(false)
const adminError = ref('')
const adminForm = ref({ username: '', password: '' })

async function fetchServerTime() {
  try {
    const res = await api.get('/api/time')
    serverTimeStr.value = res.data.time
  } catch {
    serverTimeStr.value = null
  }
}

function updateClock() {
  let bangkokNow
  if (serverTimeStr.value) {
    bangkokNow = new Date(serverTimeStr.value)
    const elapsed = Date.now() - fetchTimeLocal.value
    bangkokNow = new Date(bangkokNow.getTime() + elapsed)
  } else {
    bangkokNow = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Bangkok' }))
  }
  const pad = (n) => String(n).padStart(2, '0')
  currentTime.value = `${bangkokNow.getFullYear()}-${pad(bangkokNow.getMonth() + 1)}-${pad(bangkokNow.getDate())} ${pad(bangkokNow.getHours())}:${pad(bangkokNow.getMinutes())}:${pad(bangkokNow.getSeconds())}`
  currentDate.value = bangkokNow.toLocaleDateString('th-TH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Bangkok' })
}

let clockInterval = null

const companyStyles = {
  'ETC': 'background: linear-gradient(135deg, #10b981, #047857); color: white; border: 2px solid rgba(52,211,153,0.5); box-shadow: 0 10px 25px rgba(16,185,129,0.25);',
  'STC': 'background: linear-gradient(135deg, #a855f7, #7e22ce); color: white; border: 2px solid rgba(168,85,247,0.5); box-shadow: 0 10px 25px rgba(168,85,247,0.25);',
  'ETE': 'background: linear-gradient(135deg, #f97316, #c2410c); color: white; border: 2px solid rgba(251,146,60,0.5); box-shadow: 0 10px 25px rgba(249,115,22,0.25);',
  'NTC': 'background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: 2px solid rgba(96,165,250,0.5); box-shadow: 0 10px 25px rgba(59,130,246,0.25);',
}

const companyOrder = ['ETC', 'STC', 'ETE', 'NTC']

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return []
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter(
    emp => emp.name.toLowerCase().includes(q) || (emp.employee_code && emp.employee_code.toLowerCase().includes(q))
  )
})

onMounted(async () => {
  fetchTimeLocal.value = Date.now()
  await fetchServerTime()
  updateClock()
  clockInterval = setInterval(updateClock, 1000)
  setInterval(async () => {
    fetchTimeLocal.value = Date.now()
    await fetchServerTime()
  }, 300000)
  await fetchCompanies()

  // Offline queue: auto-sync + status listener
  offlineQueue.startAutoSync()
  offlineQueue.onStatusChange((count) => {
    queuedCount.value = count
  })
  offlineQueue.onSyncComplete((item) => {
    if (item.type === 'face_register') {
      faceRegPhotos.value = []
      faceRegEncodings.value = []
      faceRegResults.value = []
      faceRegAllDone.value = false
      faceRegCurrentPosition.value = 0
      faceRegCapturing.value = false
      faceRegDetecting.value = false
      scanningError.value = ''
    }
  })
})

onUnmounted(() => {
  if (gpsWatchId !== null) {
    navigator.geolocation.clearWatch(gpsWatchId)
    gpsWatchId = null
  }
  if (map) { map.remove(); map = null }
})

async function fetchCompanies() {
  try {
    const response = await api.get('/api/companies')
    const all = response.data.data?.data || response.data.data || []
    companies.value = companyOrder
      .map(name => all.find(c => c.code_prefix === name))
      .filter(Boolean)
  } catch (error) {
    console.error('Error fetching companies:', error)
    companies.value = [
      { id: 2, name: 'Eastern Thai Consulting 1992 Co.,Ltd.', code_prefix: 'ETC' },
      { id: 4, name: 'STC', code_prefix: 'STC' },
      { id: 3, name: 'ETECH', code_prefix: 'ETE' },
      { id: 1, name: 'NTC', code_prefix: 'NTC' }
    ]
  }
}

function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLon = (lon2 - lon1) * Math.PI / 180
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon/2) * Math.sin(dLon/2)
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
}

async function fetchOfficeLocation(employeeId) {
  try {
    const res = await api.get('/api/employee/' + employeeId + '/office-location')
    officeLocation.value = res.data.data
  } catch { officeLocation.value = null }
}

function initMap() {
  if (!mapContainer.value) return
  if (map) { map.remove(); map = null; officeMarker = null; userMarker = null; radiusCircle = null }

  const lat = officeLocation.value?.latitude || 13.7563
  const lng = officeLocation.value?.longitude || 100.5018
  map = L.map(mapContainer.value, { zoomControl: false, attributionControl: false }).setView([lat, lng], 16)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map)

  if (officeLocation.value) {
    officeMarker = L.marker([officeLocation.value.latitude, officeLocation.value.longitude]).addTo(map)
      .bindPopup('🏢 ' + officeLocation.value.name)
    radiusCircle = L.circle([officeLocation.value.latitude, officeLocation.value.longitude], {
      radius: officeLocation.value.radius_meters,
      color: '#3b82f6',
      fillColor: '#3b82f6',
      fillOpacity: 0.1,
      weight: 2,
    }).addTo(map)
  }

  if (currentLatitude.value && currentLongitude.value) {
    updateUserMarker()
    map.fitBounds([[currentLatitude.value, currentLongitude.value], [officeLocation.value.latitude, officeLocation.value.longitude]])
  }
}

function updateUserMarker() {
  if (!map || !currentLatitude.value || !currentLongitude.value) return
  if (userMarker) { map.removeLayer(userMarker) }
  userMarker = L.circleMarker([currentLatitude.value, currentLongitude.value], {
    radius: 8,
    color: '#2563eb',
    fillColor: '#3b82f6',
    fillOpacity: 1,
    weight: 3,
  }).addTo(map).bindPopup('📱 คุณอยู่ที่นี่')
}

function getCurrentPosition() {
  if (!navigator.geolocation) { gpsStatus.value = 'error'; return }
  gpsStatus.value = 'acquiring'
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      currentLatitude.value = pos.coords.latitude
      currentLongitude.value = pos.coords.longitude
      currentAccuracy.value = pos.coords.accuracy
      gpsStatus.value = 'found'
      if (officeLocation.value) {
        distanceToOffice.value = calculateDistance(
          pos.coords.latitude, pos.coords.longitude,
          officeLocation.value.latitude, officeLocation.value.longitude
        )
      }
      updateUserMarker()
      if (map && officeLocation.value) {
        map.fitBounds([[pos.coords.latitude, pos.coords.longitude], [officeLocation.value.latitude, officeLocation.value.longitude]])
      }
    },
    (err) => { gpsStatus.value = 'error'; console.error('GPS error:', err) },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
  )
}

let gpsWatchId = null
function startGpsWatch() {
  if (!navigator.geolocation) return
  gpsWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      currentLatitude.value = pos.coords.latitude
      currentLongitude.value = pos.coords.longitude
      currentAccuracy.value = pos.coords.accuracy
      gpsStatus.value = 'found'
      if (officeLocation.value) {
        distanceToOffice.value = calculateDistance(
          pos.coords.latitude, pos.coords.longitude,
          officeLocation.value.latitude, officeLocation.value.longitude
        )
      }
      updateUserMarker()
    },
    () => {},
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
  )
}

function selectCompany(company) {
  selectedCompany.value = company
  step.value = 2
  searchEmployees()
}

async function handleAdminLogin() {
  adminLoading.value = true
  adminError.value = ''
  try {
    const res = await api.post('/api/auth/login', {
      username: adminForm.value.username,
      password: adminForm.value.password
    })
    if (res.data.success) {
      const { data } = res.data
      setToken(data.token)
      setCurrentUser(data.user)
      showAdminLogin.value = false
      const role = data.user?.role
      if (role === 'super_admin') {
        router.push('/admin/company-settings')
      } else {
        router.push('/dashboard')
      }
    } else {
      adminError.value = res.data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
    }
  } catch (err) {
    adminError.value = err.response?.data?.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
  } finally {
    adminLoading.value = false
  }
}

async function searchEmployees() {
  if (!selectedCompany.value) return
  loading.value = true
  try {
    const response = await api.post('/api/employee/auth/search', {
      company_id: selectedCompany.value.id,
      query: ''
    })
    employees.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Error searching employees:', error)
  } finally {
    loading.value = false
  }
}

function confirmReregister(employee) {
  reregisterEmployee.value = employee
  showReregisterModal.value = true
}

async function doReregister() {
  if (!reregisterEmployee.value) return
  reregisterLoading.value = true
  try {
    await api.delete(`/api/employees/${reregisterEmployee.value.id}/face-data`)
    showReregisterModal.value = false
    selectedEmployee.value = reregisterEmployee.value
    scanningError.value = ''
    faceRegPhotos.value = []
    faceRegResults.value = []
    faceRegDetecting.value = false
    faceRegDetectError.value = ''
    faceRegAllDone.value = false
    faceRegEncodings.value = []
    faceRegCurrentPosition.value = 0
    step.value = 2.7
  } catch (e) {
    scanningError.value = e.response?.data?.message || 'ไม่สามารถลบข้อมูลใบหน้าได้'
    showReregisterModal.value = false
  } finally {
    reregisterLoading.value = false
  }
}

async function selectEmployee(employee) {
  selectedEmployee.value = employee
  scanningError.value = ''

  try {
    const faceRes = await api.get(`/api/employees/${employee.id}/face-data`)
    const faceData = faceRes.data.data || []
    const faceCount = faceData.length

    if (faceCount < 5) {
      const registeredToday = faceData.some(f => {
        const created = new Date(f.created_at)
        const today = new Date()
        return created.toDateString() === today.toDateString()
      })

      if (registeredToday) {
        scanningError.value = 'ลงทะเบียนใบหน้าวันนี้แล้ว กรุณากลับมาลงทะเบียนใหม่วันถัดไป'
        step.value = 2
        return
      }

      faceRegPhotos.value = []
      faceRegResults.value = []
      faceRegDetecting.value = false
      faceRegDetectError.value = ''
      faceRegAllDone.value = false
      faceRegEncodings.value = []
      faceRegCurrentPosition.value = 0
      step.value = 2.7
      return
    }
  } catch (e) {
    console.error('Face data error:', e)
  }

  try {
    const res = await api.post('/api/remote/check-active', {
      employee_id: employee.id
    })
    if (res.data.data?.has_remote_assignment) {
      step.value = 2.5
    } else {
      scanType.value = 'office_scan'
      scanMode.value = 'verify_only'
      step.value = 3
      await fetchOfficeLocation(employee.id)
      await nextTick()
      initMap()
      getCurrentPosition()
      startGpsWatch()
    }
  } catch {
    scanType.value = 'office_scan'
    scanMode.value = 'verify_only'
    step.value = 3
    await fetchOfficeLocation(employee.id)
    await nextTick()
    initMap()
    getCurrentPosition()
    startGpsWatch()
  }
}

function startOfficeScan() {
  scanType.value = 'office_scan'
  scanMode.value = 'verify_only'
  step.value = 3
  fetchOfficeLocation(selectedEmployee.value?.id).then(() => {
    nextTick(() => {
      initMap()
      getCurrentPosition()
      startGpsWatch()
    })
  })
}

function startRemoteScan() {
  scanType.value = 'remote_scan'
  scanMode.value = 'verify_only'
  step.value = 3
}

function captureFaceRegPhoto() {
  if (faceRegCameraRef.value) {
    faceRegDetectError.value = ''
    faceRegCapturing.value = true
    faceRegCameraRef.value.capture()
  }
}

async function handleFaceRegCaptured(imageData) {
  faceRegDetecting.value = true

  // ─── Client-side quality validation ───
  try {
    const img = new Image()
    img.src = imageData
    await new Promise((resolve, reject) => {
      img.onload = resolve
      img.onerror = reject
    })

    if (img.width < 480 || img.height < 480) {
      faceRegResults.value[faceRegCurrentPosition.value] = 'error'
      faceRegDetectError.value = 'ภาพคมชัดไม่พอ กรุณาใช้กล้องความละเอียดสูงกว่านี้'
      faceRegCapturing.value = false
      faceRegDetecting.value = false
      return
    }

    // ตรวจ brightness
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    const sampleSize = 100
    canvas.width = sampleSize
    canvas.height = sampleSize
    ctx.drawImage(img, 0, 0, img.width, img.height, 0, 0, sampleSize, sampleSize)
    const imageDataObj = ctx.getImageData(0, 0, sampleSize, sampleSize)
    const data = imageDataObj.data
    let totalBrightness = 0
    for (let i = 0; i < data.length; i += 4) {
      totalBrightness += (data[i] + data[i + 1] + data[i + 2]) / 3
    }
    const avgBrightness = totalBrightness / (sampleSize * sampleSize)

    if (avgBrightness < 30) {
      faceRegResults.value[faceRegCurrentPosition.value] = 'error'
      faceRegDetectError.value = 'ภาพมืดเกินไป กรุณาถ่ายในที่ที่มีแสงสว่างเพียงพอ'
      faceRegCapturing.value = false
      faceRegDetecting.value = false
      return
    }
    if (avgBrightness > 230) {
      faceRegResults.value[faceRegCurrentPosition.value] = 'error'
      faceRegDetectError.value = 'ภาพสว่างเกินไป กรุณาหลีกเลี่ยงแสงโดยตรง'
      faceRegCapturing.value = false
      faceRegDetecting.value = false
      return
    }
  } catch (e) {
    // skip image validation if Image API not available
  }

  try {
    const res = await api.post('/api/face/detect', { image: imageData }, { timeout: 15000 })
    if (res.data.detected) {
      const pos = faceRegCurrentPosition.value
      faceRegPhotos.value[pos] = imageData
      faceRegEncodings.value[pos] = res.data.encoding
      faceRegResults.value[pos] = 'success'
      faceRegDetectError.value = ''
      faceRegCapturing.value = false
      faceRegDetecting.value = false

      if (faceRegResults.value.filter(r => r === 'success').length >= 5) {
        faceRegAllDone.value = true
      } else {
        const nextEmpty = faceRegResults.value.findIndex((r, i) => i >= faceRegPhotos.value.length || r !== 'success')
        faceRegCurrentPosition.value = nextEmpty >= 0 ? nextEmpty : faceRegPhotos.value.length
      }
    } else {
      faceRegResults.value[faceRegCurrentPosition.value] = 'error'
      faceRegDetectError.value = res.data.message || 'ไม่พบใบหน้า กรุณาถ่ายใหม่'
      faceRegCapturing.value = false
      faceRegDetecting.value = false
    }
  } catch (err) {
    faceRegResults.value[faceRegCurrentPosition.value] = 'error'
    faceRegDetectError.value = 'ไม่สามารถตรวจสอบใบหน้าได้ กรุณาถ่ายใหม่'
    faceRegCapturing.value = false
    faceRegDetecting.value = false
  }
}

function retakeFaceRegPhoto() {
  faceRegDetectError.value = ''
  if (faceRegCameraRef.value) {
    faceRegCameraRef.value.retake()
  }
}

function clickFaceRegDot(index) {
  if (faceRegDetecting.value || faceRegCapturing.value) return
  faceRegDetectError.value = ''
  faceRegCurrentPosition.value = index
  if (faceRegCameraRef.value) {
    faceRegCameraRef.value.retake()
  }
}

async function registerFace() {
  const successfulEncodings = faceRegEncodings.value.filter((_, i) => faceRegResults.value[i] === 'success')
  if (successfulEncodings.length < 5) return
  faceRegRegistering.value = true
  try {
    await api.post(`/api/employees/${selectedEmployee.value.id}/face`, {
      encodings: successfulEncodings
    })
    try {
      const res = await api.post('/api/remote/check-active', {
        employee_id: selectedEmployee.value.id
      })
      if (res.data.data?.has_remote_assignment) {
        step.value = 2.5
      } else {
        scanType.value = 'office_scan'
        scanMode.value = 'verify_only'
        step.value = 3
      }
    } catch {
      scanType.value = 'office_scan'
      scanMode.value = 'verify_only'
      step.value = 3
    }
  } catch (error) {
    // ─── Offline queue: เก็บลง localStorage แล้วส่งอัตโนมัติเมื่อเน็ตกลับมา ───
    const isNetworkError = !error.response || error.code === 'ECONNABORTED' || error.message?.includes('Network Error')
    if (isNetworkError) {
      const queueUrl = `/api/employees/${selectedEmployee.value.id}/face`
      offlineQueue.enqueue({
        type: 'face_register',
        url: queueUrl,
        data: { encodings: successfulEncodings },
      })
      queuedCount.value = offlineQueue.getCount()
      scanningError.value = '网络ไม่เสถียร ข้อมูลถูกบันทึกแล้ว จะส่งอัตโนมัติเมื่อเน็ตกลับมา'
    } else {
      scanningError.value = error.response?.data?.message || 'เกิดข้อผิดพลาดในการลงทะเบียนใบหน้า'
    }
  } finally {
    faceRegRegistering.value = false
  }
}

async function syncQueuedData() {
  if (syncingQueue.value || queuedCount.value === 0) return
  syncingQueue.value = true
  try {
    const syncResult = await offlineQueue.processQueue()
    if (syncResult.synced > 0) {
      scanningError.value = ''
      result.value = {
        success: true,
        message: `✓ ส่งข้อมูล ${syncResult.synced} รายการ สำเร็จ`,
        time: new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }),
        location: null,
      }
      step.value = 4
    }
  } finally {
    syncingQueue.value = false
  }
}

function handleVerified(data) {
  // If verify_only mode, show action menu
  if (scanMode.value === 'verify_only') {
    capturedImage.value = data.image || null
    verifiedEmployeeData.value = data.data?.employee || selectedEmployee.value
    verificationToken.value = data.data?.verification_token || null
    scanningError.value = ''
    triggerScan.value = false
    step.value = 3.5
    return
  }

  const now = new Date()
  const timeStr = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })

  result.value = {
    success: true,
    message: data.message || (scanMode.value === 'check_in' ? '✓ เช็คอินสำเร็จ' : '✓ เช็คเอาท์สำเร็จ'),
    time: timeStr,
    location: scanType.value === 'remote_scan' ? customLocationName.value : null
  }
  step.value = 4
}

function handleFailed() {
  triggerScan.value = false
  scanningError.value = 'ไม่สามารถยืนยันตัวตนได้ กรุณาลองใหม่'
}

function handleError(message) {
  triggerScan.value = false
  scanningError.value = message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'
}

async function handleActionSelect(type) {
  if (actionLoading.value) return
  if (scanType.value === 'remote_scan' && !pdpaConsent.value) {
    scanningError.value = 'กรุณายินยอมตามนโยบาย PDPA ก่อนปฏิบัติงาน'
    return
  }
  actionLoading.value = true
  scanningError.value = ''
  selectedAction.value = type

  try {
    if (!capturedImage.value) {
      throw new Error('ไม่พบข้อมูลภาพสแกน กรุณาสแกนใหม่')
    }

    const response = await api.post('/api/face/verify', {
      employee_id: selectedEmployee.value.id,
      image: capturedImage.value,
      type: type,
      latitude: currentLatitude.value,
      longitude: currentLongitude.value,
      verification_token: verificationToken.value,
      pdpa_consent: scanType.value === 'remote_scan' ? pdpaConsent.value : true,
    })

    if (response.data.success) {
      const now = new Date()
      const timeStr = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
      const actionLabels = {
        check_in: '✓ เช็คอินสำเร็จ',
        check_out: '✓ เช็คเอาท์สำเร็จ',
        ot_start: '✓ เริ่มทำโอทีสำเร็จ',
        ot_end: '✓ ออกโอทีสำเร็จ',
      }

      result.value = {
        success: true,
        message: response.data.message || actionLabels[type] || 'สำเร็จ',
        time: timeStr,
        location: scanType.value === 'remote_scan' ? customLocationName.value : null,
      }
      step.value = 4
    } else {
      scanningError.value = response.data.message || 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่'
    }
  } catch (error) {
    scanningError.value = error.response?.data?.message || error.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'
  } finally {
    actionLoading.value = false
  }
}

function retryScan() {
  scanningError.value = ''
  triggerScan.value = false
  capturedImage.value = null
  verifiedEmployeeData.value = null
  step.value = 3
}

function reset() {
  step.value = 1
  selectedCompany.value = null
  selectedEmployee.value = null
  searchQuery.value = ''
  employees.value = []
  scanningError.value = ''
  result.value = { success: false, message: '', time: '', location: '' }
  scanType.value = 'office_scan'
  scanMode.value = 'verify_only'
  capturedImage.value = null
  verifiedEmployeeData.value = null
  verificationToken.value = null
  selectedAction.value = null
  actionLoading.value = false
  customLocationName.value = ''
  pdpaConsent.value = false
  faceRegPhotos.value = []
  faceRegCapturing.value = false
  faceRegResults.value = []
  faceRegDetecting.value = false
  faceRegDetectError.value = ''
  faceRegAllDone.value = false
  faceRegEncodings.value = []
  faceRegCurrentPosition.value = 0
  currentLatitude.value = null
  currentLongitude.value = null
  currentAccuracy.value = null
  distanceToOffice.value = null
  gpsStatus.value = 'acquiring'
  officeLocation.value = null
  if (gpsWatchId !== null) {
    navigator.geolocation.clearWatch(gpsWatchId)
    gpsWatchId = null
  }
  if (map) { map.remove(); map = null; officeMarker = null; userMarker = null; radiusCircle = null }
}
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>