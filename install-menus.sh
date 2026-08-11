#!/bin/bash
# ============================================
# Install Permission & Change Password Menus
# Run on server:
#   cd /www/wwwroot/attendance.northernthai.co.th
#   bash install-menus.sh
# ============================================

set -e

echo "========================================="
echo "  Installing Permission & Change Password"
echo "========================================="
echo ""

# ============================================
# 1. Create PermissionController.php
# ============================================
echo "[1/5] Creating PermissionController.php..."

cat > app/Http/Controllers/Api/PermissionController.php << 'CONTROLLER'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::where('company_id', $request->user()->company_id ?? 1)
            ->select('id', 'employee_code', 'name', 'nickname', 'position', 'role', 'is_active', 'department', 'division')
            ->orderBy('employee_code')
            ->get();

        return response()->json($employees);
    }

    public function updateRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|in:employee,admin,super_admin']);

        $employee = Employee::findOrFail($id);

        if ($employee->id === $request->user()->id) {
            return response()->json(['message' => 'ไม่สามารถเปลี่ยนสิทธิ์ตัวเองได้'], 400);
        }

        $employee->update(['role' => $request->role]);

        return response()->json(['message' => 'อัพเดทสิทธิ์สำเร็จ', 'employee' => $employee]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => $request->is_active]);

        return response()->json(['message' => 'อัพเดทสถานะสำเร็จ', 'employee' => $employee]);
    }

    public function resetPassword(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['password' => Hash::make('password')]);

        return response()->json(['message' => 'รีเซ็ตรหัสผ่านสำเร็จ (รหัสผ่านใหม่: password)']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'รหัสผ่านเดิมไม่ถูกต้อง'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
    }
}
CONTROLLER

echo "  ✓ Done"
echo ""

# ============================================
# 2. Create Permission.vue
# ============================================
echo "[2/5] Creating Permission.vue..."

mkdir -p resources/js/pages

cat > resources/js/pages/Permission.vue << 'VUE1'
<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">จัดการสิทธิ์พนักงาน</h1>
      <div class="flex gap-2">
        <input v-model="search" type="text" placeholder="ค้นหาชื่อหรือรหัส..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
        <select v-model="filterRole" class="px-4 py-2 border rounded-lg">
          <option value="">ทุกสิทธิ์</option>
          <option value="employee">พนักงาน</option>
          <option value="admin">HR</option>
          <option value="super_admin">ผู้ดูแลระบบ</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-blue-600">{{ employees.length }}</div>
        <div class="text-sm text-gray-500">พนักงานทั้งหมด</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-green-600">{{ employees.filter(e => e.is_active).length }}</div>
        <div class="text-sm text-gray-500">เปิดใช้งาน</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-3xl font-bold text-red-600">{{ employees.filter(e => !e.is_active).length }}</div>
        <div class="text-sm text-gray-500">ปิดใช้งาน</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">รหัส</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">ชื่อ-สกุล</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">ตำแหน่ง</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">สิทธิ์ปัจจุบัน</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">สถานะ</th>
            <th class="px-4 py-3 text-center text-sm font-medium text-gray-600">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in filteredEmployees" :key="emp.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ emp.employee_code }}</td>
            <td class="px-4 py-3">
              <div class="text-sm font-medium">{{ emp.name }}</div>
              <div v-if="emp.nickname" class="text-xs text-gray-400">{{ emp.nickname }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ emp.position }}</td>
            <td class="px-4 py-3">
              <select :value="emp.role" @change="updateRole(emp, $event.target.value)" class="text-sm border rounded px-2 py-1" :disabled="emp.id === currentUserId">
                <option value="employee">พนักงาน</option>
                <option value="admin">HR</option>
                <option value="super_admin">ผู้ดูแลระบบ</option>
              </select>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="toggleStatus(emp)" :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-3 py-1 rounded-full text-xs font-medium" :disabled="emp.id === currentUserId">
                {{ emp.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="resetPassword(emp)" class="text-orange-600 hover:text-orange-800 text-sm" :disabled="emp.id === currentUserId">
                รีเซ็ตรหัสผ่าน
              </button>
            </td>
          </tr>
          <tr v-if="filteredEmployees.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่พบข้อมูล</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="toast" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50">{{ toast }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const employees = ref([])
const search = ref('')
const filterRole = ref('')
const toast = ref('')
const currentUserId = ref(null)

const filteredEmployees = computed(() => {
  return employees.value.filter(emp => {
    const matchSearch = !search.value || emp.name.includes(search.value) || emp.employee_code?.includes(search.value) || emp.nickname?.includes(search.value)
    const matchRole = !filterRole.value || emp.role === filterRole.value
    return matchSearch && matchRole
  })
})

const showToast = (msg) => {
  toast.value = msg
  setTimeout(() => toast.value = '', 3000)
}

const updateRole = async (emp, newRole) => {
  try {
    await api.put(`/api/permissions/employees/${emp.id}/role`, { role: newRole })
    emp.role = newRole
    showToast(`อัพเดทสิทธิ์ ${emp.name} สำเร็จ`)
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

const toggleStatus = async (emp) => {
  const newStatus = !emp.is_active
  if (!confirm(`${newStatus ? 'เปิดใช้งาน' : 'ปิดใช้งาน'} ${emp.name}?`)) return
  try {
    await api.put(`/api/permissions/employees/${emp.id}/status`, { is_active: newStatus })
    emp.is_active = newStatus
    showToast('อัพเดทสถานะสำเร็จ')
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

const resetPassword = async (emp) => {
  if (!confirm(`รีเซ็ตรหัสผ่าน ${emp.name}? (รหัสผ่านใหม่: password)`)) return
  try {
    await api.post(`/api/permissions/employees/${emp.id}/reset-password`)
    showToast('รีเซ็ตรหัสผ่านสำเร็จ')
  } catch (e) {
    alert(e.response?.data?.message || 'เกิดข้อผิดพลาด')
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/api/permissions/employees')
    employees.value = res.data
  } catch (e) {
    console.error(e)
  }
})
</script>
VUE1

echo "  ✓ Done"
echo ""

# ============================================
# 3. Create ChangePassword.vue
# ============================================
echo "[3/5] Creating ChangePassword.vue..."

cat > resources/js/pages/ChangePassword.vue << 'VUE2'
<template>
  <div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-6">เปลี่ยนรหัสผ่าน</h1>
    <div class="bg-white rounded-lg shadow p-6">
      <form @submit.prevent="changePassword">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านปัจจุบัน</label>
            <input v-model="form.current_password" type="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
            <input v-model="form.new_password" type="password" required minlength="6" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
            <input v-model="form.new_password_confirmation" type="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" />
          </div>
          <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>
          <div v-if="success" class="text-green-600 text-sm">{{ success }}</div>
          <button type="submit" :disabled="loading" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'กำลังเปลี่ยน...' : 'เปลี่ยนรหัสผ่าน' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const form = ref({ current_password: '', new_password: '', new_password_confirmation: '' })
const loading = ref(false)
const error = ref('')
const success = ref('')

const changePassword = async () => {
  error.value = ''
  success.value = ''
  if (form.value.new_password !== form.value.new_password_confirmation) {
    error.value = 'รหัสผ่านใหม่ไม่ตรงกัน'
    return
  }
  if (form.value.new_password.length < 6) {
    error.value = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'
    return
  }
  loading.value = true
  try {
    await api.post('/api/permissions/change-password', form.value)
    success.value = 'เปลี่ยนรหัสผ่านสำเร็จ'
    form.value = { current_password: '', new_password: '', new_password_confirmation: '' }
  } catch (e) {
    error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด'
  } finally {
    loading.value = false
  }
}
</script>
VUE2

echo "  ✓ Done"
echo ""

# ============================================
# 4. Add Routes to api.php
# ============================================
echo "[4/5] Adding routes to api.php..."

# Check if routes already exist
if grep -q "PermissionController" routes/api.php; then
    echo "  ⚠ Routes already exist, skipping..."
else
    cat >> routes/api.php << 'ROUTES'

// Permission routes
use App\Http\Controllers\Api\PermissionController;

Route::middleware('auth:sanctum')->prefix('api/permissions')->group(function () {
    Route::get('/employees', [PermissionController::class, 'index']);
    Route::put('/employees/{id}/role', [PermissionController::class, 'updateRole']);
    Route::put('/employees/{id}/status', [PermissionController::class, 'updateStatus']);
    Route::post('/employees/{id}/reset-password', [PermissionController::class, 'resetPassword']);
    Route::post('/change-password', [PermissionController::class, 'changePassword']);
});
ROUTES
    echo "  ✓ Done"
fi
echo ""

# ============================================
# 5. Build Frontend
# ============================================
echo "[5/5] Building frontend..."

# Check if node_modules exists
if [ -d "node_modules" ]; then
    npm run build
    echo "  ✓ Done"
else
    echo "  ⚠ node_modules not found, run 'npm install' first"
fi
echo ""

# ============================================
# Summary
# ============================================
echo "========================================="
echo "  ✅ Installation Complete!"
echo "========================================="
echo ""
echo "Files created:"
echo "  - app/Http/Controllers/Api/PermissionController.php"
echo "  - resources/js/pages/Permission.vue"
echo "  - resources/js/pages/ChangePassword.vue"
echo "  - routes/api.php (updated)"
echo ""
echo "New menus:"
echo "  👤 User: เปลี่ยนรหัสผ่าน"
echo "  👔 HR/Admin: จัดการสิทธิ์ + เปลี่ยนรหัสผ่าน"
echo ""
echo "Don't forget to add menu items to your sidebar!"
echo "========================================="
