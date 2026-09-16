<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">จุดเช็คอิน/เช็คเอาท์ สำหรับพนักงาน</h1>
          <p class="text-gray-500">กำหนดตำแหน่งจุดอ้างอิง (ระยะรัศมี 200 เมตร) และจัดกลุ่มพนักงาน</p>
        </div>
        <button @click="openCreateForm" class="btn-primary flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M12 5v14M5 12h14" /></svg>
          เพิ่มสถานที่
        </button>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

      <template v-else>
        <!-- Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="card !p-4">
            <p class="text-xs text-gray-500">พื้นที่ทั้งหมด</p>
            <p class="text-2xl font-bold text-navy mt-0.5">{{ locations.length }}<span class="text-sm font-medium text-gray-400 ml-1">แห่ง</span></p>
          </div>
          <div class="card !p-4">
            <p class="text-xs text-gray-500">เปิดใช้งาน</p>
            <p class="text-2xl font-bold text-emerald-600 mt-0.5">{{ activeLocationCount }}<span class="text-sm font-medium text-gray-400 ml-1">แห่ง</span></p>
          </div>
          <div class="card !p-4 col-span-2 sm:col-span-2">
            <p class="text-xs text-gray-500">พนักงานที่ถูกมอบหมายทั้งหมด</p>
            <p class="text-2xl font-bold text-blue-600 mt-0.5">{{ totalAssignedEmployees }}<span class="text-sm font-medium text-gray-400 ml-1">คน</span></p>
          </div>
        </div>

        <div v-for="company in companies" :key="company.id" class="space-y-3">
          <h2 class="text-base font-semibold text-navy flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold shadow-sm" :style="companyStyle(company.code_prefix)">{{ company.code_prefix?.charAt(0) }}</span>
            {{ company.name }}
            <span class="text-xs font-normal text-gray-400">{{ getLocationsByCompany(company.id).length }} พื้นที่</span>
          </h2>

          <div v-if="getLocationsByCompany(company.id).length === 0" class="card text-sm text-gray-400 py-4 text-center">ยังไม่มีสถานที่</div>

          <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div v-for="loc in getLocationsByCompany(company.id)" :key="loc.id"
              class="card !p-4 transition-shadow hover:shadow-lg"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                  <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-[18px] h-[18px] text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                  </div>
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <h3 class="font-semibold text-navy truncate">{{ loc.name }}</h3>
                      <span class="inline-flex items-center gap-1 text-[11px] font-medium px-1.5 py-0.5 rounded-full"
                        :class="loc.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="loc.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                        {{ loc.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                      </span>
                    </div>
                    <p v-if="loc.address" class="text-xs text-gray-400 mt-0.5 truncate">{{ loc.address }}</p>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-1.5 mt-3">
                <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-50 rounded-lg px-2 py-1">
                  <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" /><circle cx="12" cy="12" r="3" stroke-width="2" /></svg>
                  รัศมี {{ loc.radius_meters }} ม.
                </span>
                <span v-if="loc.work_start_time" class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-50 rounded-lg px-2 py-1">
                  <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7v5l3 3" /></svg>
                  {{ loc.work_start_time }}-{{ loc.work_end_time }}
                </span>
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-600 bg-blue-50 rounded-lg px-2 py-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" stroke-width="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" /></svg>
                  {{ loc.assigned_employees_count || 0 }} คน
                </span>
              </div>

              <div class="flex gap-1.5 mt-3 pt-3 border-t border-gray-100">
                <button @click="openAssignModal(loc)" class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" stroke-width="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 8v6M22 11h-6" /></svg>
                  จัดกลุ่ม
                </button>
                <button @click="openPatternModal(loc)" class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 text-xs font-medium bg-violet-50 text-violet-600 rounded-lg hover:bg-violet-100 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2" /><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18" stroke-width="2" /></svg>
                  รูปแบบกะ
                </button>
                <button @click="openEditForm(loc)" class="inline-flex items-center justify-center px-2.5 py-1.5 text-gray-500 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors" title="แก้ไข">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z" /></svg>
                </button>
                <button @click="deleteLocation(loc)" class="inline-flex items-center justify-center px-2.5 py-1.5 text-red-500 bg-red-50 rounded-lg hover:bg-red-100 transition-colors" title="ลบ">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6" /></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showForm = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-bold text-navy mb-4">{{ editingId ? 'แก้ไขสถานที่' : 'เพิ่มสถานที่ใหม่' }}</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท *</label>
              <select v-model="form.company_id" class="input-field w-full" :disabled="editingId">
                <option value="">เลือกบริษัท</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานที่ *</label>
              <input v-model="form.name" type="text" class="input-field w-full" placeholder="เช่น สำนักงานใหญ่" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
              <input v-model="form.address" type="text" class="input-field w-full" placeholder="ที่อยู่ละเอียด" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้างาน</label>
                <input v-model="form.work_start_time" type="time" class="input-field w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเลิกงาน</label>
                <input v-model="form.work_end_time" type="time" class="input-field w-full" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">รัศมีเช็คอิน (เมตร) *</label>
              <input v-model.number="form.radius_meters" type="number" class="input-field w-full" min="10" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded" />
              <label for="is_active" class="text-sm text-gray-700">เปิดใช้งาน</label>
            </div>

            <!-- Map -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">เลือกพิกัดจากแผนที่ (คลิกเพื่อกำหนดตำแหน่ง)</label>
              <div ref="mapContainer" class="w-full h-64 rounded-lg border border-gray-300 z-0"></div>
              <div class="flex gap-4 mt-2">
                <div class="flex-1">
                  <label class="text-xs text-gray-500">ละติจูด</label>
                  <input v-model="form.latitude" type="text" class="input-field w-full text-sm" readonly />
                </div>
                <div class="flex-1">
                  <label class="text-xs text-gray-500">ลองจิจูด</label>
                  <input v-model="form.longitude" type="text" class="input-field w-full text-sm" readonly />
                </div>
              </div>
            </div>

            <div class="flex gap-3 justify-end pt-2 border-t">
              <button @click="showForm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">ยกเลิก</button>
              <button @click="saveLocation" :disabled="saving" class="btn-primary text-sm">
                {{ saving ? 'กำลังบันทึก...' : (editingId ? 'บันทึก' : 'เพิ่ม') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Employees Modal -->
    <div v-if="showAssign" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 animate-fadeIn" @click.self="showAssign = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-1">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" stroke-width="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 8v6M22 11h-6" /></svg>
              </div>
              <h3 class="text-lg font-bold text-navy">จัดกลุ่มพนักงาน — {{ assignLocation?.name }}</h3>
            </div>
            <button @click="showAssign = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <p class="text-xs text-gray-400 mb-5">พนักงานที่ยังไม่ถูกจัดกลุ่มพื้นที่ไหน จะใช้พื้นที่หลักของบริษัทเป็นค่าเริ่มต้นให้อัตโนมัติ</p>

          <!-- Assigned employees -->
          <div class="mb-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">พนักงานในพื้นที่นี้ ({{ assignedEmployees.length }} คน)</h4>
            <div v-if="assignedEmployees.length === 0" class="text-sm text-gray-400 py-4 text-center border border-dashed rounded-xl">ยังไม่มีพนักงานในพื้นที่นี้</div>
            <div v-else class="border border-gray-100 rounded-xl divide-y divide-gray-100 max-h-48 overflow-y-auto custom-scrollbar">
              <div v-for="emp in assignedEmployees" :key="emp.id" class="flex items-center justify-between px-3 py-2 hover:bg-gray-50/60">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ emp.name?.charAt(0) }}</span>
                  <div class="min-w-0">
                    <span class="text-sm font-medium text-navy">{{ emp.name }}</span>
                    <span class="text-xs text-gray-400 ml-1.5">{{ emp.employee_code }}</span>
                    <span v-if="emp.division" class="text-xs text-gray-400">· {{ emp.division }}</span>
                  </div>
                </div>
                <button @click="removeEmployee(emp)" class="text-xs text-red-500 hover:text-red-700 font-medium shrink-0 ml-2">ลบ</button>
              </div>
            </div>
          </div>

          <!-- Search and add employees -->
          <div>
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">เพิ่มพนักงาน</h4>
            <div class="relative mb-2">
              <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2" /><path stroke-linecap="round" stroke-width="2" d="M21 21l-4.35-4.35" /></svg>
              <input v-model="assignSearch" @input="searchUnassigned" type="text" class="input-field w-full" placeholder="ค้นหาชื่อ, รหัส, แผนก..." />
            </div>
            <div v-if="unassignedEmployees.length === 0" class="text-sm text-gray-400 py-4 text-center border border-dashed rounded-xl">ไม่พบพนักงาน</div>
            <div v-else class="border border-gray-100 rounded-xl divide-y divide-gray-100 max-h-64 overflow-y-auto custom-scrollbar">
              <div v-for="emp in unassignedEmployees" :key="emp.id" class="flex items-center justify-between px-3 py-2 hover:bg-gray-50/60">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold flex items-center justify-center shrink-0">{{ emp.name?.charAt(0) }}</span>
                  <div class="min-w-0">
                    <span class="text-sm font-medium text-navy">{{ emp.name }}</span>
                    <span class="text-xs text-gray-400 ml-1.5">{{ emp.employee_code }}</span>
                    <span v-if="emp.division" class="text-xs text-gray-400">· {{ emp.division }}</span>
                  </div>
                </div>
                <button @click="assignEmployee(emp)" class="text-xs text-blue-600 hover:text-blue-800 font-medium shrink-0 ml-2">+ เพิ่ม</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Shift Pattern Modal -->
    <div v-if="showPattern" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 animate-fadeIn" @click.self="showPattern = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-1">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2" /><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18" stroke-width="2" /></svg>
              </div>
              <h3 class="text-lg font-bold text-navy">รูปแบบกะประจำพื้นที่ — {{ patternLocation?.name }}</h3>
            </div>
            <button @click="showPattern = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <p class="text-xs text-gray-400 mb-5">กำหนดกะและวันที่ต้องเข้างานของพื้นที่นี้ไว้ล่วงหน้า ระบบจะจดจำและใช้สร้างตารางกะให้อัตโนมัติทุกเดือน โดยจะไม่ทับวันที่มีการมอบหมาย/คำขอปรับเปลี่ยนอยู่แล้ว</p>

          <!-- Existing patterns -->
          <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">รูปแบบที่ตั้งไว้</h4>
          <div v-if="patterns.length === 0" class="text-sm text-gray-400 py-4 text-center border border-dashed rounded-xl mb-5">ยังไม่มีรูปแบบกะ</div>
          <div v-else class="space-y-2 mb-5">
            <div v-for="p in patterns" :key="p.id" class="flex items-center gap-3 border border-gray-100 rounded-xl px-3 py-2.5">
              <div class="w-14 h-11 rounded-lg flex flex-col items-center justify-center shrink-0"
                :class="p.work_shift?.is_overnight ? 'bg-indigo-600' : 'bg-blue-600'">
                <span class="text-[10px] font-bold text-white/70">กะ {{ p.work_shift?.group_number }}</span>
                <span class="text-xs font-bold text-white">{{ p.work_shift?.work_hours || 8 }} ชม.</span>
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-navy">
                  {{ p.work_shift?.start_time?.substring(0,5) }}–{{ p.work_shift?.end_time?.substring(0,5) }} น.
                  <span v-if="!p.is_active" class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-500 font-normal">ปิดใช้งาน</span>
                </div>
                <div class="flex gap-1 mt-1.5">
                  <span v-for="d in weekDays" :key="d.value" class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-semibold"
                    :class="p.days_of_week?.includes(d.value) ? 'bg-blue-100 text-blue-700' : 'text-gray-300'">
                    {{ d.short }}
                  </span>
                </div>
                <p v-if="p.effective_start_date" class="text-[11px] text-gray-400 mt-1">
                  ตั้งแต่ {{ p.effective_start_date }}{{ p.effective_end_date ? ' ถึง ' + p.effective_end_date : '' }}
                </p>
              </div>
              <button @click="deletePattern(p)" class="text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6" /></svg>
              </button>
            </div>
          </div>

          <!-- Add new pattern -->
          <div class="border border-dashed border-gray-200 rounded-xl p-4">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">เพิ่มรูปแบบกะ</h4>
            <div class="space-y-3.5">
              <div>
                <label class="block text-xs text-gray-500 mb-1.5">กะ</label>
                <div class="flex gap-2 overflow-x-auto pb-1 custom-scrollbar">
                  <button v-for="s in workShifts" :key="s.id" type="button" @click="patternForm.work_shift_id = s.id"
                    class="shrink-0 px-3 py-2 rounded-lg border text-center transition-colors"
                    :class="patternForm.work_shift_id === s.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                    <div class="text-[11px] font-semibold" :class="patternForm.work_shift_id === s.id ? 'text-blue-700' : 'text-gray-500'">กะ {{ s.group_number }}</div>
                    <div class="text-xs font-medium text-navy whitespace-nowrap">{{ s.start_time }}-{{ s.end_time }}</div>
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1.5">วันที่ต้องเข้างาน</label>
                <div class="flex gap-1.5">
                  <button v-for="d in weekDays" :key="d.value" type="button"
                    @click="toggleDay(d.value)"
                    class="flex-1 py-2 rounded-lg text-xs font-semibold transition-colors"
                    :class="patternForm.days_of_week.includes(d.value) ? 'bg-blue-600 text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'">
                    {{ d.short }}
                  </button>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1.5">มีผลตั้งแต่ (ไม่ระบุ = ทันที)</label>
                  <input v-model="patternForm.effective_start_date" type="date" class="input-field w-full text-sm !pl-3" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1.5">มีผลถึง (ไม่ระบุ = ไม่สิ้นสุด)</label>
                  <input v-model="patternForm.effective_end_date" type="date" class="input-field w-full text-sm !pl-3" />
                </div>
              </div>
              <button @click="savePattern" :disabled="savingPattern" class="btn-primary text-sm w-full">
                {{ savingPattern ? 'กำลังบันทึก...' : '+ เพิ่มรูปแบบกะ' }}
              </button>
            </div>
          </div>

          <!-- Generate schedule for a month -->
          <div class="mt-5 rounded-xl p-4 bg-gradient-to-br from-blue-600 to-blue-700 text-white">
            <div class="flex items-center gap-2 mb-1">
              <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
              <h4 class="text-sm font-semibold">สร้างตารางกะจากรูปแบบ</h4>
            </div>
            <p class="text-xs text-blue-100/80 mb-3">ใช้รูปแบบด้านบนสร้างตารางกะให้พนักงานทุกคนในพื้นที่นี้ ข้ามวันที่มีการมอบหมาย/ปรับเปลี่ยนไว้แล้วให้อัตโนมัติ</p>
            <div class="flex items-end gap-2">
              <input v-model="generateMonth" type="month" class="text-sm rounded-lg px-3 py-2 text-navy flex-1" />
              <button @click="generateSchedule" :disabled="generating || patterns.length === 0" class="px-4 py-2 bg-white text-blue-700 text-sm font-semibold rounded-lg hover:bg-blue-50 disabled:opacity-50 shrink-0">
                {{ generating ? 'กำลังสร้าง...' : 'สร้างตารางกะ' }}
              </button>
            </div>
            <p v-if="generateResult" class="text-xs text-blue-50 bg-white/10 rounded-lg px-3 py-2 mt-3">{{ generateResult }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
})

const loading = ref(true)
const saving = ref(false)
const locations = ref([])
const companies = ref([])

const showForm = ref(false)
const editingId = ref(null)
const form = reactive({
  company_id: '',
  name: '',
  address: '',
  latitude: '',
  longitude: '',
  radius_meters: 200,
  work_start_time: '',
  work_end_time: '',
  is_active: true,
})

const showAssign = ref(false)
const assignLocation = ref(null)
const assignedEmployees = ref([])
const unassignedEmployees = ref([])
const assignSearch = ref('')

const showPattern = ref(false)
const patternLocation = ref(null)
const patterns = ref([])
const workShifts = ref([])
const savingPattern = ref(false)
const generating = ref(false)
const generateMonth = ref(new Date().toISOString().slice(0, 7))
const generateResult = ref('')
const patternForm = reactive({
  work_shift_id: '',
  days_of_week: [],
  effective_start_date: '',
  effective_end_date: '',
})

const weekDays = [
  { value: 1, label: 'จันทร์', short: 'จ' },
  { value: 2, label: 'อังคาร', short: 'อ' },
  { value: 3, label: 'พุธ', short: 'พ' },
  { value: 4, label: 'พฤหัสฯ', short: 'พฤ' },
  { value: 5, label: 'ศุกร์', short: 'ศ' },
  { value: 6, label: 'เสาร์', short: 'ส' },
  { value: 0, label: 'อาทิตย์', short: 'อา' },
]

function toggleDay(value) {
  const idx = patternForm.days_of_week.indexOf(value)
  if (idx === -1) patternForm.days_of_week.push(value)
  else patternForm.days_of_week.splice(idx, 1)
}

const mapContainer = ref(null)
let map = null
let marker = null

const companyColors = {
  ETC1992: 'linear-gradient(135deg, #10b981, #047857)',
  STC: 'linear-gradient(135deg, #a855f7, #7e22ce)',
  ETECH: 'linear-gradient(135deg, #f97316, #c2410c)',
  NTC: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
}

function companyStyle(code) {
  return companyColors[code] || 'background: linear-gradient(135deg, #64748b, #334155)'
}

const activeLocationCount = computed(() => locations.value.filter(l => l.is_active).length)
const totalAssignedEmployees = computed(() => locations.value.reduce((sum, l) => sum + (l.assigned_employees_count || 0), 0))

function getLocationsByCompany(companyId) {
  return locations.value.filter(l => l.company_id === companyId)
}

async function loadData() {
  loading.value = true
  try {
    const [locRes, compRes] = await Promise.all([
      api.get('/api/office-locations'),
      api.get('/api/companies'),
    ])
    locations.value = locRes.data.data || []
    companies.value = compRes.data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openCreateForm() {
  editingId.value = null
  Object.assign(form, {
    company_id: '',
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    radius_meters: 200,
    work_start_time: '',
    work_end_time: '',
    is_active: true,
  })
  showForm.value = true
  nextTick(() => initMap())
}

function openEditForm(loc) {
  editingId.value = loc.id
  Object.assign(form, {
    company_id: loc.company_id,
    name: loc.name,
    address: loc.address || '',
    latitude: loc.latitude,
    longitude: loc.longitude,
    radius_meters: loc.radius_meters,
    work_start_time: loc.work_start_time || '',
    work_end_time: loc.work_end_time || '',
    is_active: loc.is_active,
  })
  showForm.value = true
  nextTick(() => initMap())
}

async function saveLocation() {
  if (!form.company_id || !form.name || !form.latitude || !form.longitude) {
    alert('กรุณากรอกข้อมูลให้ครบถ้วน')
    return
  }
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/api/office-locations/${editingId.value}`, form)
    } else {
      await api.post('/api/office-locations', form)
    }
    showForm.value = false
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

async function deleteLocation(loc) {
  if (!confirm(`ต้องการลบ "${loc.name}" ใช่หรือไม่?`)) return
  try {
    await api.delete(`/api/office-locations/${loc.id}`)
    await loadData()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function openAssignModal(loc) {
  assignLocation.value = loc
  assignSearch.value = ''
  showAssign.value = true
  await loadAssignedEmployees(loc.id)
  await searchUnassigned()
}

async function loadAssignedEmployees(locId) {
  try {
    const res = await api.get(`/api/office-locations/${locId}/employees`)
    assignedEmployees.value = res.data.data || []
  } catch (e) {
    assignedEmployees.value = []
  }
}

async function searchUnassigned() {
  if (!assignLocation.value) return
  try {
    const params = assignSearch.value ? { search: assignSearch.value } : {}
    const res = await api.get(`/api/office-locations/${assignLocation.value.id}/unassigned`, { params })
    unassignedEmployees.value = res.data.data || []
  } catch (e) {
    unassignedEmployees.value = []
  }
}

async function assignEmployee(emp) {
  try {
    await api.post(`/api/office-locations/${assignLocation.value.id}/assign`, {
      employee_ids: [emp.id],
    })
    await loadAssignedEmployees(assignLocation.value.id)
    await searchUnassigned()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function removeEmployee(emp) {
  try {
    await api.post(`/api/office-locations/${assignLocation.value.id}/remove`, {
      employee_ids: [emp.id],
    })
    await loadAssignedEmployees(assignLocation.value.id)
    await searchUnassigned()
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function openPatternModal(loc) {
  patternLocation.value = loc
  generateResult.value = ''
  Object.assign(patternForm, { work_shift_id: '', days_of_week: [], effective_start_date: '', effective_end_date: '' })
  showPattern.value = true
  await Promise.all([loadPatterns(loc.id), loadWorkShifts()])
}

async function loadPatterns(locId) {
  try {
    const res = await api.get(`/api/office-locations/${locId}/shift-patterns`)
    patterns.value = res.data.data || []
  } catch (e) {
    patterns.value = []
  }
}

async function loadWorkShifts() {
  if (workShifts.value.length > 0) return
  try {
    const res = await api.get('/api/shift-schedules', { params: { month: generateMonth.value } })
    workShifts.value = res.data.work_shifts || []
  } catch (e) {
    workShifts.value = []
  }
}

async function savePattern() {
  if (!patternForm.work_shift_id || patternForm.days_of_week.length === 0) {
    alert('กรุณาเลือกกะและวันที่ต้องเข้างานอย่างน้อย 1 วัน')
    return
  }
  savingPattern.value = true
  try {
    await api.post(`/api/office-locations/${patternLocation.value.id}/shift-patterns`, {
      work_shift_id: patternForm.work_shift_id,
      days_of_week: patternForm.days_of_week,
      effective_start_date: patternForm.effective_start_date || null,
      effective_end_date: patternForm.effective_end_date || null,
    })
    Object.assign(patternForm, { work_shift_id: '', days_of_week: [], effective_start_date: '', effective_end_date: '' })
    await loadPatterns(patternLocation.value.id)
  } catch (e) {
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message))
  } finally {
    savingPattern.value = false
  }
}

async function deletePattern(p) {
  if (!confirm('ต้องการลบรูปแบบกะนี้ใช่หรือไม่?')) return
  try {
    await api.delete(`/api/office-locations/${patternLocation.value.id}/shift-patterns/${p.id}`)
    await loadPatterns(patternLocation.value.id)
  } catch (e) {
    alert('เกิดข้อผิดพลาด')
  }
}

async function generateSchedule() {
  generating.value = true
  generateResult.value = ''
  try {
    const res = await api.post(`/api/office-locations/${patternLocation.value.id}/shift-patterns/generate`, {
      month: generateMonth.value,
    })
    generateResult.value = res.data.message
  } catch (e) {
    generateResult.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    generating.value = false
  }
}

function initMap() {
  if (!mapContainer.value) return
  if (map) {
    map.remove()
    map = null
    marker = null
  }

  const lat = form.latitude || 13.7563
  const lng = form.longitude || 100.5018

  map = L.map(mapContainer.value).setView([lat, lng], 12)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map)

  if (form.latitude && form.longitude) {
    marker = L.marker([form.latitude, form.longitude], { draggable: true }).addTo(map)
    marker.on('dragend', (e) => {
      const pos = e.target.getLatLng()
      form.latitude = pos.lat.toFixed(8)
      form.longitude = pos.lng.toFixed(8)
    })
  }

  map.on('click', (e) => {
    const { lat, lng } = e.latlng
    form.latitude = lat.toFixed(8)
    form.longitude = lng.toFixed(8)
    if (marker) {
      marker.setLatLng([lat, lng])
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map)
      marker.on('dragend', (e) => {
        const pos = e.target.getLatLng()
        form.latitude = pos.lat.toFixed(8)
        form.longitude = pos.lng.toFixed(8)
      })
    }
  })

  setTimeout(() => map.invalidateSize(), 100)
}

onMounted(loadData)
</script>
