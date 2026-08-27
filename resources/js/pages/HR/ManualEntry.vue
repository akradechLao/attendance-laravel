<template>
  <div class="max-w-7xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">บันทึกข้อมูลด้วยมือ</h1>
      <p class="text-gray-500 mt-1">เพิ่ม แก้ไข ลบ ข้อมูลเข้างาน _OT กะ ลางาน สำหรับพนักงาน</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200 mb-6">
      <button v-for="tab in tabs" :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-3 text-sm font-medium border-b-2 transition-colors',
          activeTab === tab.key
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
        ]">
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน</label>
          <select v-model="filters.emp_id" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">ทุกคน</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
              {{ emp.employee_code }} - {{ emp.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">จากวันที่</label>
          <input type="date" v-model="filters.date_from" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ถึงวันที่</label>
          <input type="date" v-model="filters.date_to" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="flex items-end">
          <button @click="loadData" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600">
            ค้นหา
          </button>
          <button @click="resetFilters" class="ml-2 bg-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">
            รีเซ็ต
          </button>
        </div>
      </div>
    </div>

    <!-- Add Button -->
    <div class="mb-4">
      <button @click="openForm()" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-600 flex items-center gap-2">
        <span>+</span> เพิ่มข้อมูลใหม่
      </button>
    </div>

    <!-- Attendance Table -->
    <div v-if="activeTab === 'attendance'" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">รหัส</th>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">วันที่</th>
            <th class="px-4 py-3 text-left">เข้า</th>
            <th class="px-4 py-3 text-left">ออก</th>
            <th class="px-4 py-3 text-left">สถานะ</th>
            <th class="px-4 py-3 text-left">บันทึกโดย</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in tableData" :key="item.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.employee?.employee_code }}</td>
            <td class="px-4 py-3">{{ item.employee?.name }}</td>
            <td class="px-4 py-3">{{ formatDate(item.date) }}</td>
            <td class="px-4 py-3">{{ item.check_in }}</td>
            <td class="px-4 py-3">{{ item.check_out || '-' }}</td>
            <td class="px-4 py-3">
              <span :class="statusClass(item.check_in_status)">{{ statusLabel(item.check_in_status) }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ item.adjustment_note }}</td>
            <td class="px-4 py-3 text-center">
              <button @click="openForm(item)" class="text-blue-500 hover:text-blue-700 mr-2">แก้ไข</button>
              <button @click="confirmDelete('attendance', item.id)" class="text-red-500 hover:text-red-700">ลบ</button>
            </td>
          </tr>
          <tr v-if="tableData.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OT Table -->
    <div v-if="activeTab === 'ot'" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">รหัส</th>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">วันที่</th>
            <th class="px-4 py-3 text-left">เวลาเริ่ม</th>
            <th class="px-4 py-3 text-left">เวลาสิ้นสุด</th>
            <th class="px-4 py-3 text-left">เหตุผล</th>
            <th class="px-4 py-3 text-left">สถานะ</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in tableData" :key="item.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.employee?.employee_code }}</td>
            <td class="px-4 py-3">{{ item.employee?.name }}</td>
            <td class="px-4 py-3">{{ formatDate(item.date) }}</td>
            <td class="px-4 py-3">{{ item.start_time }}</td>
            <td class="px-4 py-3">{{ item.end_time }}</td>
            <td class="px-4 py-3 text-xs max-w-[200px] truncate">{{ item.reason }}</td>
            <td class="px-4 py-3">
              <span :class="otStatusClass(item.status)">{{ otStatusLabel(item.status) }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="openForm(item)" class="text-blue-500 hover:text-blue-700 mr-2">แก้ไข</button>
              <button @click="confirmDelete('ot', item.id)" class="text-red-500 hover:text-red-700">ลบ</button>
            </td>
          </tr>
          <tr v-if="tableData.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Shift Table -->
    <div v-if="activeTab === 'shift'" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <!-- Import Section -->
      <div class="p-4 border-b bg-gray-50">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-gray-700">นำเข้าตารางกะจาก CSV</h3>
            <p class="text-xs text-gray-500 mt-1">คอลัมน์: employee_code, work_date (YYYY-MM-DD), shift_code, day_type (working/holiday/day_off)</p>
          </div>
          <div class="flex gap-2">
            <button @click="downloadShiftTemplate" class="px-3 py-1.5 text-xs bg-gray-200 rounded-lg hover:bg-gray-300">ดาวน์โหลด template</button>
            <button @click="exportShiftsCSV" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700">ส่งออก CSV</button>
            <label class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer">
              เลือกไฟล์ CSV
              <input type="file" accept=".csv,.txt" class="hidden" @change="handleShiftImport" :disabled="importing" />
            </label>
          </div>
        </div>
        <div v-if="importResult" class="mt-2 p-2 rounded text-xs" :class="importResult.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
          {{ importResult.message }}
          <div v-if="importResult.errors?.length" class="mt-1 text-red-600">
            <p v-for="(err, i) in importResult.errors" :key="i">{{ err }}</p>
          </div>
        </div>
      </div>

      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">รหัส</th>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">วันที่</th>
            <th class="px-4 py-3 text-left">รหัสกะ</th>
            <th class="px-4 py-3 text-left">ประเภทวัน</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in tableData" :key="item.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.employee?.employee_code }}</td>
            <td class="px-4 py-3">{{ item.employee?.name }}</td>
            <td class="px-4 py-3">{{ formatDate(item.work_date) }}</td>
            <td class="px-4 py-3">{{ item.shift_code }}</td>
            <td class="px-4 py-3">
              <span :class="dayTypeClass(item.day_type)">{{ dayTypeLabel(item.day_type) }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="openForm(item)" class="text-blue-500 hover:text-blue-700 mr-2">แก้ไข</button>
              <button @click="confirmDelete('shift', item.id)" class="text-red-500 hover:text-red-700">ลบ</button>
            </td>
          </tr>
          <tr v-if="tableData.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Leave Table -->
    <div v-if="activeTab === 'leave'" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">รหัส</th>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">ประเภทลา</th>
            <th class="px-4 py-3 text-left">ตั้งแต่</th>
            <th class="px-4 py-3 text-left">ถึง</th>
            <th class="px-4 py-3 text-left">วัน</th>
            <th class="px-4 py-3 text-left">สถานะ</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in tableData" :key="item.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.employee?.employee_code }}</td>
            <td class="px-4 py-3">{{ item.employee?.name }}</td>
            <td class="px-4 py-3">{{ item.leaveType?.name }}</td>
            <td class="px-4 py-3">{{ formatDate(item.start_date) }}</td>
            <td class="px-4 py-3">{{ formatDate(item.end_date) }}</td>
            <td class="px-4 py-3">{{ item.total_days }}</td>
            <td class="px-4 py-3">
              <span :class="leaveStatusClass(item.status)">{{ leaveStatusLabel(item.status) }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="openForm(item)" class="text-blue-500 hover:text-blue-700 mr-2">แก้ไข</button>
              <button @click="confirmDelete('leave', item.id)" class="text-red-500 hover:text-red-700">ลบ</button>
            </td>
          </tr>
          <tr v-if="tableData.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- WFH Table -->
    <div v-if="activeTab === 'wfh'" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">รหัส</th>
            <th class="px-4 py-3 text-left">ชื่อ</th>
            <th class="px-4 py-3 text-left">วันที่</th>
            <th class="px-4 py-3 text-left">เหตุผล</th>
            <th class="px-4 py-3 text-left">สถานะ</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in tableData" :key="item.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.employee?.employee_code }}</td>
            <td class="px-4 py-3">{{ item.employee?.name }}</td>
            <td class="px-4 py-3">{{ formatDate(item.date) }}</td>
            <td class="px-4 py-3 text-xs max-w-[200px] truncate">{{ item.reason }}</td>
            <td class="px-4 py-3">
              <span :class="wfhStatusClass(item.status)">{{ wfhStatusLabel(item.status) }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="openForm(item)" class="text-blue-500 hover:text-blue-700 mr-2">แก้ไข</button>
              <button @click="confirmDelete('wfh', item.id)" class="text-red-500 hover:text-red-700">ลบ</button>
            </td>
          </tr>
          <tr v-if="tableData.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in pagination.last_page" :key="p"
        @click="goToPage(p)"
        :class="['px-3 py-1 rounded text-sm', p === pagination.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300']">
        {{ p }}
      </button>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="closeForm">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-bold mb-4">{{ isEditing ? 'แก้ไขข้อมูล' : 'เพิ่มข้อมูลใหม่' }} {{ tabLabel }}</h3>

          <!-- Attendance Form -->
          <template v-if="activeTab === 'attendance'">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
                <select v-model="form.emp_id" :disabled="isEditing" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกพนักงาน</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_code }} - {{ emp.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ *</label>
                <input type="date" v-model="form.date" class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเข้า *</label>
                  <input type="time" v-model="form.check_in" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาออก</label>
                  <input type="time" v-model="form.check_out" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select v-model="form.check_in_status" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="on_time">ปกติ</option>
                  <option value="late">สาย</option>
                  <option value="early">มาเร็ว</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                <input type="text" v-model="form.note" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="บันทึกโดย HR (Manual Entry)" />
              </div>
            </div>
          </template>

          <!-- OT Form -->
          <template v-if="activeTab === 'ot'">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
                <select v-model="form.emp_id" :disabled="isEditing" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกพนักงาน</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_code }} - {{ emp.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ *</label>
                <input type="date" v-model="form.date" class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาเริ่ม *</label>
                  <input type="time" v-model="form.start_time" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">เวลาสิ้นสุด *</label>
                  <input type="time" v-model="form.end_time" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
                <input type="text" v-model="form.reason" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="บันทึกโดย HR (Manual Entry)" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select v-model="form.status" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="approved">อนุมัติแล้ว</option>
                  <option value="pending">รออนุมัติ</option>
                </select>
              </div>
            </div>
          </template>

          <!-- Shift Form -->
          <template v-if="activeTab === 'shift'">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
                <select v-model="form.emp_id" :disabled="isEditing" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกพนักงาน</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_code }} - {{ emp.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ *</label>
                <input type="date" v-model="form.work_date" class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสกะ *</label>
                <select v-model="form.shift_code" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option v-for="s in shiftCodes" :key="s.code" :value="s.code">{{ s.code }} - {{ s.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทวัน</label>
                <select v-model="form.day_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="working">วันทำงาน</option>
                  <option value="holiday">วันหยุดนักขัตฤกษ์</option>
                  <option value="day_off">วันหยุด</option>
                </select>
              </div>
            </div>
          </template>

          <!-- Leave Form -->
          <template v-if="activeTab === 'leave'">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
                <select v-model="form.emp_id" :disabled="isEditing" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกพนักงาน</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_code }} - {{ emp.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทลา *</label>
                <select v-model="form.leave_type_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกประเภทลา</option>
                  <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่วันที่ *</label>
                  <input type="date" v-model="form.start_date" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">ถึงวันที่ *</label>
                  <input type="date" v-model="form.end_date" class="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
                <input type="text" v-model="form.reason" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="บันทึกโดย HR (Manual Entry)" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select v-model="form.status" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="approved">อนุมัติแล้ว</option>
                  <option value="pending">รออนุมัติ</option>
                </select>
              </div>
            </div>
          </template>

          <!-- WFH Form -->
          <template v-if="activeTab === 'wfh'">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">พนักงาน *</label>
                <select v-model="form.emp_id" :disabled="isEditing" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">เลือกพนักงาน</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_code }} - {{ emp.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ (วันเสาร์) *</label>
                <input type="date" v-model="form.date" class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
                <input type="text" v-model="form.reason" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="บันทึกโดย HR (Manual Entry)" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select v-model="form.status" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="approved">อนุมัติแล้ว</option>
                  <option value="pending">รออนุมัติ</option>
                </select>
              </div>
            </div>
          </template>

          <!-- Error -->
          <div v-if="formError" class="mt-4 bg-red-50 text-red-600 px-4 py-3 rounded-lg text-sm">{{ formError }}</div>

          <!-- Buttons -->
          <div class="flex justify-end gap-3 mt-6">
            <button @click="closeForm" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">ยกเลิก</button>
            <button @click="saveForm" :disabled="saving" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
              {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showDeleteConfirm = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold mb-2">ยืนยันการลบ</h3>
        <p class="text-gray-600 text-sm">ต้องการลบข้อมูลนี้ใช่หรือไม่?</p>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">ยกเลิก</button>
          <button @click="doDelete" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">ลบ</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import api from '@/services/api'

const activeTab = ref('attendance')
const tabs = [
  { key: 'attendance', label: 'เข้างาน', icon: '🕐' },
  { key: 'ot', label: 'OT', icon: '⏰' },
  { key: 'shift', label: 'กะ', icon: '🔄' },
  { key: 'leave', label: 'ลา', icon: '📅' },
  { key: 'wfh', label: 'WFH', icon: '🏠' },
]

const tabLabel = computed(() => tabs.find(t => t.key === activeTab.value)?.label || '')

const employees = ref([])
const leaveTypes = ref([])
const tableData = ref([])
const pagination = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)

const filters = ref({ emp_id: '', date_from: '', date_to: '' })

const showForm = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const form = ref({})
const formError = ref('')
const saving = ref(false)

const showDeleteConfirm = ref(false)
const deleteTarget = ref({ type: '', id: null })
const importing = ref(false)
const importResult = ref(null)

const shiftCodes = [
  { code: 'WC0001', label: '07:30-16:30' },
  { code: 'WC0002', label: '08:00-17:00' },
  { code: 'WC0003', label: '16:00-01:00' },
  { code: 'WC0004', label: '00:00-09:00' },
  { code: 'WC0005', label: '09:00-18:00' },
  { code: 'WC0006', label: '20:00-05:00' },
  { code: 'WC007', label: '21:00-06:00' },
  { code: 'WC008', label: '08:00-16:30' },
  { code: 'WC009', label: '16:00-00:30' },
  { code: 'WC010', label: '00:00-08:30' },
  { code: 'WC011', label: '08:00-20:00' },
  { code: 'WC012', label: '20:00-08:00' },
  { code: 'WC013', label: '16:00-00:00' },
  { code: 'WC014', label: '00:00-08:00' },
  { code: 'WC015', label: '07:00-16:00' },
  { code: 'WC016', label: '19:00-04:00' },
]

onMounted(async () => {
  const [empRes, ltRes] = await Promise.all([
    axios.get('/api/employees'),
    axios.get('/api/leave/types')
  ])
  employees.value = empRes.data.data || empRes.data
  leaveTypes.value = ltRes.data.data || ltRes.data
  loadData()
})

watch(activeTab, () => { loadData() })

async function loadData() {
  loading.value = true
  try {
    const params = { ...filters.value }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })

    const url = `/api/manual/${activeTab.value}`
    const res = await axios.get(url, { params })
    tableData.value = res.data.data?.data || res.data.data || []
    pagination.value = {
      current_page: res.data.data?.current_page || 1,
      last_page: res.data.data?.last_page || 1,
    }
  } catch (e) {
    console.error(e)
    tableData.value = []
  }
  loading.value = false
}

function resetFilters() {
  filters.value = { emp_id: '', date_from: '', date_to: '' }
  loadData()
}

function goToPage(p) {
  filters.value.page = p
  loadData()
}

function openForm(item = null) {
  formError.value = ''
  if (item) {
    isEditing.value = true
    editingId.value = item.id
    form.value = { ...item }
  } else {
    isEditing.value = false
    editingId.value = null
    form.value = getDefaultForm()
  }
  showForm.value = true
}

function getDefaultForm() {
  switch (activeTab.value) {
    case 'attendance':
      return { emp_id: '', date: '', check_in: '08:00', check_out: '', check_in_status: 'on_time', note: '' }
    case 'ot':
      return { emp_id: '', date: '', start_time: '18:00', end_time: '21:00', reason: '', status: 'approved' }
    case 'shift':
      return { emp_id: '', work_date: '', shift_code: 'WC0002', day_type: 'working' }
    case 'leave':
      return { emp_id: '', leave_type_id: '', start_date: '', end_date: '', reason: '', status: 'approved' }
    case 'wfh':
      return { emp_id: '', date: '', reason: '', status: 'approved' }
  }
}

function closeForm() {
  showForm.value = false
  form.value = {}
  formError.value = ''
}

async function saveForm() {
  formError.value = ''
  saving.value = true
  try {
    const url = isEditing.value
      ? `/api/manual/${activeTab.value}/${editingId.value}`
      : `/api/manual/${activeTab.value}`
    const method = isEditing.value ? 'put' : 'post'
    await axios[method](url, form.value)
    closeForm()
    loadData()
  } catch (e) {
    formError.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  }
  saving.value = false
}

function confirmDelete(type, id) {
  deleteTarget.value = { type, id }
  showDeleteConfirm.value = true
}

async function doDelete() {
  try {
    await axios.delete(`/api/manual/${deleteTarget.value.type}/${deleteTarget.value.id}`)
    showDeleteConfirm.value = false
    loadData()
  } catch (e) {
    alert('ลบไม่สำเร็จ: ' + (e.response?.data?.message || ''))
  }
}

function formatDate(d) {
  if (!d) return '-'
  const dt = new Date(d)
  return dt.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function statusClass(s) {
  return { on_time: 'bg-green-100 text-green-700', late: 'bg-red-100 text-red-700', early: 'bg-blue-100 text-blue-700' }[s] || 'bg-gray-100 text-gray-700'
}
function statusLabel(s) {
  return { on_time: 'ปกติ', late: 'สาย', early: 'มาเร็ว' }[s] || s || '-'
}

function otStatusClass(s) {
  return { approved: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'
}
function otStatusLabel(s) {
  return { approved: 'อนุมัติ', pending: 'รออนุมัติ', rejected: 'ปฏิเสธ' }[s] || s || '-'
}

function dayTypeClass(t) {
  return { working: 'bg-green-100 text-green-700', holiday: 'bg-blue-100 text-blue-700', day_off: 'bg-gray-100 text-gray-700' }[t] || 'bg-gray-100 text-gray-700'
}
function dayTypeLabel(t) {
  return { working: 'วันทำงาน', holiday: 'วันหยุดนักขัตฤกษ์', day_off: 'วันหยุด' }[t] || t || '-'
}

function leaveStatusClass(s) {
  return { approved: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'
}
function leaveStatusLabel(s) {
  return { approved: 'อนุมัติ', pending: 'รออนุมัติ', rejected: 'ปฏิเสธ' }[s] || s || '-'
}

function wfhStatusClass(s) {
  return { approved: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'
}
function wfhStatusLabel(s) {
  return { approved: 'อนุมัติ', pending: 'รออนุมัติ', rejected: 'ปฏิเสธ' }[s] || s || '-'
}

const companyId = computed(() => {
  try { return JSON.parse(localStorage.getItem('user'))?.company_id || 1 } catch { return 1 }
})

function downloadShiftTemplate() {
  const csv = 'employee_code,work_date,shift_code,day_type\n001,2026-09-01,WC0002,working\n001,2026-09-02,WC0002,holiday\n001,2026-09-03,WC0002,day_off\n'
  const blob = new Blob([csv], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'shift_schedule_template.csv'
  link.click()
}

function exportShiftsCSV() {
  if (!tableData.value.length) return alert('ไม่มีข้อมูลที่จะส่งออก')
  const headers = ['employee_code', 'employee_name', 'work_date', 'shift_code', 'day_type']
  const rows = tableData.value.map(r => [
    r.employee?.employee_code || '',
    r.employee?.name || '',
    r.work_date?.slice(0, 10) || '',
    r.shift_code || '',
    r.day_type || ''
  ])
  const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `shifts_export_${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
}

async function handleShiftImport(event) {
  const file = event.target.files[0]
  if (!file) return
  importing.value = true
  importResult.value = null
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('company_id', companyId.value)
    const res = await api.post('/api/manual/import-shift', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = res.data
    loadData()
  } catch (e) {
    importResult.value = { success: false, message: e.response?.data?.message || 'เกิดข้อผิดพลาด' }
  } finally {
    importing.value = false
    event.target.value = ''
  }
}
</script>
