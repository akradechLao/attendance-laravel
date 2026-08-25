<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <router-link
            to="/login"
            class="px-4 py-2 rounded-xl bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium transition-colors shadow-sm"
          >
            เข้าสู่ระบบ
          </router-link>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg shadow">
            {{ initials }}
          </div>
          <div>
            <p class="text-gray-800 font-semibold">{{ store.user?.name }}</p>
            <p class="text-blue-600 text-sm">{{ store.user?.company?.name }}</p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-red-50 text-gray-500 hover:text-red-500 text-sm font-medium transition-colors"
        >
          ออกจากระบบ
        </button>
      </div>
    </header>

    <!-- Main Menu -->
    <main class="max-w-4xl mx-auto px-4 py-8">
      <!-- Greeting -->
      <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-1">
          สวัสดีครับ {{ store.user?.nickname || store.user?.name }}
        </h1>
        <p class="text-gray-500">เลือกเมนูที่ต้องการ</p>
      </div>

      <!-- Warning Banner -->
      <div v-if="warnings.length > 0" class="mb-6">
        <div v-for="(w, i) in warnings" :key="i"
          :class="w.severity === 'high' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-amber-50 border-amber-200 text-amber-700'"
          class="p-3 rounded-xl border text-sm font-medium mb-2 flex items-center gap-2">
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          {{ w.message }}
        </div>
      </div>

      <!-- Announcement Banner -->
      <div v-if="announcements.length > 0" class="mb-6">
        <div v-for="ann in announcements.slice(0, 2)" :key="ann.id"
          :class="ann.priority === 'urgent' ? 'bg-red-50 border-red-200' : ann.priority === 'important' ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'"
          class="p-3 rounded-xl border text-sm mb-2">
          <p class="font-semibold" :class="ann.priority === 'urgent' ? 'text-red-700' : ann.priority === 'important' ? 'text-amber-700' : 'text-blue-700'">
            {{ ann.priority === 'urgent' ? '!! ' : ann.priority === 'important' ? '! ' : '' }}{{ ann.title }}
          </p>
          <p class="text-gray-600 text-xs mt-1">{{ ann.body.substring(0, 100) }}{{ ann.body.length > 100 ? '...' : '' }}</p>
        </div>
        <router-link v-if="announcements.length > 2" to="/employee/announcements" class="text-blue-500 text-xs font-medium mt-1 inline-block">
          ดูประกาศทั้งหมด ({{ announcements.length }}) →
        </router-link>
      </div>

      <!-- Primary Menu Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-8">
        <!-- สรุปวันนี้ -->
        <router-link to="/employee/dashboard" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">สรุปวันนี้</h2>
            <p class="text-gray-400 text-xs mt-1">สถานะ & สถิติ</p>
          </div>
        </router-link>

        <!-- สแกนเข้า/ออกงาน -->
        <router-link to="/employee" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-indigo-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">สแกนเข้า/ออกงาน</h2>
            <p class="text-gray-400 text-xs mt-1">เช็คอิน เช็คเอาท์</p>
          </div>
        </router-link>

        <!-- ประวัติเข้างาน -->
        <router-link to="/employee/history" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-cyan-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ประวัติเข้างาน</h2>
            <p class="text-gray-400 text-xs mt-1">ดูย้อนหลัง</p>
          </div>
        </router-link>

        <!-- สถิติของฉัน -->
        <router-link to="/employee/stats" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-purple-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">สถิติของฉัน</h2>
            <p class="text-gray-400 text-xs mt-1">สรุปเข้างาน</p>
          </div>
        </router-link>

        <!-- ขอลางาน -->
        <router-link to="/employee/leave" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-blue-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอลางาน</h2>
            <p class="text-gray-400 text-xs mt-1">ลาพักผ่อน ลากิจ ลาป่วย</p>
            <div v-if="pendingCounts.leave > 0" class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
              <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.leave }}
            </div>
          </div>
        </router-link>

        <!-- ขอโอที -->
        <router-link to="/employee/ot" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-amber-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอโอที</h2>
            <p class="text-gray-400 text-xs mt-1">ทำโอทีนอกเวลา</p>
            <div v-if="pendingCounts.ot > 0" class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
              <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.ot }}
            </div>
          </div>
        </router-link>

        <!-- ขอปฏิบัติงานนอกสถานที่ -->
        <router-link to="/employee/wfh" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-emerald-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอ WFH</h2>
            <p class="text-gray-400 text-xs mt-1">ปฏิบัติงานนอกสถานที่</p>
            <div v-if="pendingCounts.wfh > 0" class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium border border-amber-200">
              <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
              รออนุมัติ {{ pendingCounts.wfh }}
            </div>
          </div>
        </router-link>

        <!-- ขอย้ายเวร -->
        <router-link to="/employee/shift-swap" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-pink-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ขอย้ายเวร</h2>
            <p class="text-gray-400 text-xs mt-1">สลับเวรกับเพื่อน</p>
          </div>
        </router-link>

        <!-- ตารางเวร -->
        <router-link to="/employee/schedule" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-teal-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ตารางเวร</h2>
            <p class="text-gray-400 text-xs mt-1">ดูเวรตัวเอง</p>
          </div>
        </router-link>

        <!-- ปฏิทินวันหยุด -->
        <router-link to="/employee/holidays" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-rose-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ปฏิทินวันหยุด</h2>
            <p class="text-gray-400 text-xs mt-1">วันหยุด & สิทธิลา</p>
          </div>
        </router-link>

        <!-- สรุปการลาทีม -->
        <router-link to="/employee/team-leave" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-indigo-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">สรุปการลาทีม</h2>
            <p class="text-gray-400 text-xs mt-1">ใครลาวันไหน</p>
          </div>
        </router-link>

        <!-- ประกาศ / กระดานข่าว -->
        <router-link to="/employee/announcements" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-orange-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ประกาศ</h2>
            <p class="text-gray-400 text-xs mt-1">กระดานข่าวบริษัท</p>
          </div>
        </router-link>

        <!-- ข้อมูลส่วนตัว -->
        <router-link to="/employee/profile" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-slate-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">ข้อมูลส่วนตัว</h2>
            <p class="text-gray-400 text-xs mt-1">ดูข้อมูลพนักงาน</p>
          </div>
        </router-link>

        <!-- สลิปเงินเดือน -->
        <router-link to="/employee/payslip" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-green-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">สลิปเงินเดือน</h2>
            <p class="text-gray-400 text-xs mt-1">ดูสลิปเงินเดือน</p>
          </div>
        </router-link>

        <!-- เปลี่ยนรหัสผ่าน -->
        <router-link to="/employee/change-password" class="block group">
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-center">
            <div class="w-12 h-12 sm:w-14 sm:h-14 mx-auto rounded-2xl bg-gray-500 flex items-center justify-center mb-3 shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
            </div>
            <h2 class="font-bold text-gray-800 text-sm sm:text-base">เปลี่ยนรหัสผ่าน</h2>
            <p class="text-gray-400 text-xs mt-1">อัพเดทรหัสผ่าน</p>
          </div>
        </router-link>
      </div>

      <!-- Pending Summary -->
      <div v-if="pendingCounts.leave + pendingCounts.ot + pendingCounts.wfh > 0" class="bg-white rounded-2xl p-6 border border-gray-200 shadow-md mb-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 text-center">รายการที่รออนุมัติ</h3>
        <div class="grid grid-cols-3 gap-4">
          <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-blue-50 flex items-center justify-center mb-2 border border-blue-200">
              <span class="text-xl font-bold text-blue-600">{{ pendingCounts.leave }}</span>
            </div>
            <p class="text-gray-500 text-xs font-medium">ลางาน</p>
          </div>
          <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-amber-50 flex items-center justify-center mb-2 border border-amber-200">
              <span class="text-xl font-bold text-amber-500">{{ pendingCounts.ot }}</span>
            </div>
            <p class="text-gray-500 text-xs font-medium">โอที</p>
          </div>
          <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-50 flex items-center justify-center mb-2 border border-emerald-200">
              <span class="text-xl font-bold text-emerald-500">{{ pendingCounts.wfh }}</span>
            </div>
            <p class="text-gray-500 text-xs font-medium">WFH</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import store, { logout } from '../../store'
import axios from 'axios'

const router = useRouter()
const pendingCounts = ref({ leave: 0, ot: 0, wfh: 0 })
const warnings = ref([])
const announcements = ref([])

const initials = computed(() => {
  const name = store.user?.name || 'E'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function handleLogout() {
  logout()
  router.push('/login')
}

onMounted(async () => {
  const token = localStorage.getItem('token')
  if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  }
  try {
    const [pendingRes, warnRes, annRes] = await Promise.allSettled([
      axios.get('/api/employee/requests/pending-count'),
      axios.get('/api/employee/warnings'),
      axios.get('/api/announcements'),
    ])
    if (pendingRes.status === 'fulfilled' && pendingRes.value.data.success) {
      pendingCounts.value = pendingRes.value.data.data
    }
    if (warnRes.status === 'fulfilled' && warnRes.value.data.success) {
      warnings.value = warnRes.value.data.data.warnings || []
    }
    if (annRes.status === 'fulfilled' && annRes.value.data.success) {
      announcements.value = annRes.value.data.data || []
    }
  } catch (e) {
    // ignore
  }
})
</script>
