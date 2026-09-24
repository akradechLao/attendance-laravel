<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-navy">สิทธิ์อนุมัติเสริม</h1>
          <p class="text-gray-500">มอบสิทธิ์ให้หัวหน้ารอง/ผู้แทน อนุมัติ ลา / โอที / WFH / สลับเวร / ร้องขอเข้ากะ ได้เพิ่มเติม</p>
        </div>
        <button
          v-if="approverId"
          @click="save"
          :disabled="saving || !hasChanges"
          class="btn-primary disabled:opacity-50"
        >
          {{ saving ? 'กำลังบันทึก...' : 'บันทึก' }}
        </button>
      </div>

      <!-- Approver picker -->
      <div class="card">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1 min-w-[220px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">เลือกผู้อนุมัติ</label>
            <select v-model.number="approverId" class="input-field" @change="onApproverChange">
              <option :value="0">-- เลือกผู้ได้รับสิทธิ์ --</option>
              <option v-for="e in approverOptions" :key="e.id" :value="e.id">
                {{ e.employee_code }} - {{ e.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ฝ่าย</label>
            <select v-model="selectedDivision" class="input-field" @change="onDivisionChange">
              <option value="">ทุกฝ่าย</option>
              <option v-for="d in divisions" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">แผนก</label>
            <select v-model="selectedDepartment" class="input-field" @change="loadCandidates">
              <option value="">ทุกแผนก</option>
              <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">ค้นหา</label>
            <input
              v-model="searchQuery"
              type="text"
              class="input-field w-full"
              placeholder="รหัส หรือ ชื่อ..."
              @input="debounceSearch"
            />
          </div>
        </div>
        <p v-if="approver" class="mt-3 text-sm text-gray-500">
          ผู้อนุมัติ: <span class="font-medium text-navy">{{ approver.employee_code }} - {{ approver.name }}</span>
          · สายบังคับบัญชา {{ chainCount }} คน (ไม่ต้องติ๊กซ้ำ)
          · สิทธิ์เสริม {{ delegatedCount }} คน
        </p>
      </div>

      <div v-if="!approverId" class="card text-center py-10 text-gray-400">
        เลือกผู้อนุมัติเพื่อจัดการสิทธิ์
      </div>

      <template v-else>
        <!-- Batch bar -->
        <div v-if="selectedIds.length > 0" class="card !p-3 bg-blue-50 border-blue-200 flex items-center justify-between flex-wrap gap-2">
          <span class="text-sm font-medium text-blue-700">เลือก {{ selectedIds.length }} คน</span>
          <div class="flex gap-2 flex-wrap items-center">
            <span class="text-xs text-blue-700">เปิดทั้งแถว:</span>
            <button @click="batchSetType('can_leave', true)" class="px-2 py-1 text-xs bg-white border border-blue-300 rounded hover:bg-blue-100">ลา</button>
            <button @click="batchSetType('can_ot', true)" class="px-2 py-1 text-xs bg-white border border-blue-300 rounded hover:bg-blue-100">โอที</button>
            <button @click="batchSetType('can_wfh', true)" class="px-2 py-1 text-xs bg-white border border-blue-300 rounded hover:bg-blue-100">WFH</button>
            <button @click="batchSetType('can_shift_swap', true)" class="px-2 py-1 text-xs bg-white border border-blue-300 rounded hover:bg-blue-100">สลับเวร</button>
            <button @click="batchSetType('can_shift_request', true)" class="px-2 py-1 text-xs bg-white border border-blue-300 rounded hover:bg-blue-100">เข้ากะ</button>
            <button @click="batchAllTypes(true)" class="px-2 py-1 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700">ทั้งหมด</button>
            <button @click="batchAllTypes(false)" class="px-2 py-1 text-xs bg-red-50 text-red-600 border border-red-200 rounded hover:bg-red-100">ล้าง</button>
            <button @click="selectedIds = []" class="px-2 py-1 text-xs bg-white text-gray-500 border border-gray-200 rounded hover:bg-gray-50">ยกเลิก</button>
          </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-gray-500">กำลังโหลด...</div>

        <div v-else class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-center px-3 py-3 w-10">
                    <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="rounded" />
                  </th>
                  <th class="text-left px-3 py-3 text-sm font-semibold text-gray-600">รหัส-ชื่อ</th>
                  <th class="text-left px-3 py-3 text-sm font-semibold text-gray-600">ตำแหน่ง</th>
                  <th class="text-left px-3 py-3 text-sm font-semibold text-gray-600">ฝ่าย</th>
                  <th class="text-left px-3 py-3 text-sm font-semibold text-gray-600">แผนก</th>
                  <th class="text-center px-3 py-3 text-sm font-semibold text-gray-600">สถานะ</th>
                  <th class="text-center px-2 py-3 text-sm font-semibold text-gray-600">ลา</th>
                  <th class="text-center px-2 py-3 text-sm font-semibold text-gray-600">โอที</th>
                  <th class="text-center px-2 py-3 text-sm font-semibold text-gray-600">WFH</th>
                  <th class="text-center px-2 py-3 text-sm font-semibold text-gray-600">สลับเวร</th>
                  <th class="text-center px-2 py-3 text-sm font-semibold text-gray-600">เข้ากะ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr
                  v-for="emp in candidates"
                  :key="emp.id"
                  class="hover:bg-gray-50"
                  :class="selectedIds.includes(emp.id) ? 'bg-blue-50' : ''"
                >
                  <td class="text-center px-3 py-2">
                    <input
                      v-if="!emp.is_chain"
                      type="checkbox"
                      :checked="selectedIds.includes(emp.id)"
                      @change="toggleSelect(emp)"
                      class="rounded"
                    />
                  </td>
                  <td class="px-3 py-2">
                    <div class="font-medium text-navy text-sm">{{ emp.employee_code }} - {{ emp.name }}</div>
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-600">{{ emp.position || '-' }}</td>
                  <td class="px-3 py-2 text-sm text-gray-600">{{ emp.division || '-' }}</td>
                  <td class="px-3 py-2 text-sm text-gray-600">{{ emp.department || '-' }}</td>
                  <td class="px-3 py-2 text-center">
                    <span
                      v-if="emp.is_chain"
                      class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 whitespace-nowrap"
                    >ตามสายบังคับบัญชา</span>
                    <span
                      v-else-if="isDelegated(emp)"
                      class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700 whitespace-nowrap"
                    >สิทธิ์เสริม</span>
                    <span v-else class="text-gray-300 text-xs">-</span>
                  </td>
                  <td v-for="col in typeCols" :key="col.key" class="px-2 py-2 text-center">
                    <input
                      type="checkbox"
                      v-model="grants[emp.id][col.key]"
                      :disabled="emp.is_chain"
                      class="rounded"
                      @change="touch(emp.id)"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="candidates.length === 0" class="text-center py-8 text-gray-400 text-sm">
            ไม่พบพนักงาน
          </div>
        </div>

        <div class="flex justify-between items-center text-sm text-gray-500">
          <span>{{ candidates.length }} คน</span>
          <button
            @click="save"
            :disabled="saving || !hasChanges"
            class="btn-primary disabled:opacity-50"
          >
            {{ saving ? 'กำลังบันทึก...' : 'บันทึกสิทธิ์' }}
          </button>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../../services/api'
import AppLayout from '../../layouts/AppLayout.vue'

const loading = ref(false)
const saving = ref(false)
const approverOptions = ref([])
const approverId = ref(0)
const candidates = ref([])
const divisions = ref([])
const departments = ref([])
const selectedDivision = ref('')
const selectedDepartment = ref('')
const searchQuery = ref('')
const selectedIds = ref([])
const grants = reactive({})
const dirty = reactive({})
const initial = reactive({})

const typeCols = [
  { key: 'can_leave' },
  { key: 'can_ot' },
  { key: 'can_wfh' },
  { key: 'can_shift_swap' },
  { key: 'can_shift_request' },
]

const approver = computed(() => approverOptions.value.find(e => e.id === approverId.value) || null)
const chainCount = computed(() => candidates.value.filter(e => e.is_chain).length)
const delegatedCount = computed(() => candidates.value.filter(e => !e.is_chain && isDelegated(e)).length)
const isAllSelected = computed(() => {
  const selectable = candidates.value.filter(e => !e.is_chain)
  return selectable.length > 0 && selectable.every(e => selectedIds.value.includes(e.id))
})
const hasChanges = computed(() => Object.keys(dirty).length > 0)

function emptyGrant() {
  return { can_leave: false, can_ot: false, can_wfh: false, can_shift_swap: false, can_shift_request: false }
}

function ensureGrant(id) {
  if (!grants[id]) grants[id] = emptyGrant()
  return grants[id]
}

function isDelegated(emp) {
  const g = grants[emp.id]
  if (!g) return false
  return typeCols.some(c => g[c.key])
}

function touch(id) {
  dirty[id] = true
}

function snapshot() {
  Object.keys(initial).forEach(k => delete initial[k])
  Object.keys(grants).forEach(id => {
    initial[id] = { ...grants[id] }
  })
}

function computeDirty() {
  Object.keys(dirty).forEach(k => delete dirty[k])
  Object.keys(grants).forEach(id => {
    const a = grants[id]
    const b = initial[id]
    if (!b) {
      if (typeCols.some(c => a[c.key])) dirty[id] = true
      return
    }
    if (typeCols.some(c => !!a[c.key] !== !!b[c.key])) dirty[id] = true
  })
}

function toggleSelect(emp) {
  const idx = selectedIds.value.indexOf(emp.id)
  if (idx === -1) selectedIds.value.push(emp.id)
  else selectedIds.value.splice(idx, 1)
  computeDirty()
}

function toggleAll() {
  const selectable = candidates.value.filter(e => !e.is_chain)
  if (isAllSelected.value) {
    const ids = new Set(selectable.map(e => e.id))
    selectedIds.value = selectedIds.value.filter(id => !ids.has(id))
  } else {
    const ids = new Set(selectedIds.value)
    selectable.forEach(e => { if (!ids.has(e.id)) selectedIds.value.push(e.id) })
  }
}

function batchSetType(key, value) {
  selectedIds.value.forEach(id => {
    const emp = candidates.value.find(e => e.id === id)
    if (!emp || emp.is_chain) return
    ensureGrant(id)[key] = value
  })
  computeDirty()
}

function batchAllTypes(value) {
  selectedIds.value.forEach(id => {
    const emp = candidates.value.find(e => e.id === id)
    if (!emp || emp.is_chain) return
    typeCols.forEach(c => { ensureGrant(id)[c.key] = value })
  })
  computeDirty()
}

let searchTimer = null
function debounceSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadCandidates(), 300)
}

function onDivisionChange() {
  selectedDepartment.value = ''
  loadCandidates()
}

function onApproverChange() {
  selectedIds.value = []
  loadCandidates()
}

async function loadApprovers() {
  try {
    const res = await api.get('/api/approval-rights/candidates', {
      params: { search: '', per_page: 500 },
    })
    // Full list of possible approvers = all active employees in scope
    approverOptions.value = res.data.data || []
  } catch (e) {
    console.error(e)
  }
}

async function loadCandidates() {
  if (!approverId.value) {
    candidates.value = []
    return
  }
  loading.value = true
  try {
    const res = await api.get('/api/approval-rights/candidates', {
      params: {
        approver_id: approverId.value,
        division: selectedDivision.value || undefined,
        department: selectedDepartment.value || undefined,
        search: searchQuery.value || undefined,
      },
    })
    candidates.value = res.data.data || []
    divisions.value = res.data.filters?.divisions || []
    departments.value = res.data.filters?.departments || []

    Object.keys(grants).forEach(k => delete grants[k])
    candidates.value.forEach(emp => {
      const g = ensureGrant(emp.id)
      g.can_leave = !!emp.can_leave
      g.can_ot = !!emp.can_ot
      g.can_wfh = !!emp.can_wfh
      g.can_shift_swap = !!emp.can_shift_swap
      g.can_shift_request = !!emp.can_shift_request
      if (emp.is_chain) {
        typeCols.forEach(c => { g[c.key] = false })
      }
    })
    snapshot()
    Object.keys(dirty).forEach(k => delete dirty[k])
    selectedIds.value = []
  } catch (e) {
    console.error(e)
    alert('โหลดข้อมูลไม่สำเร็จ: ' + (e.response?.data?.message || e.message))
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!approverId.value || saving.value) return
  saving.value = true
  try {
    const payload = {
      approver_id: approverId.value,
      grants: candidates.value
        .filter(e => !e.is_chain)
        .map(e => ({
          employee_id: e.id,
          ...ensureGrant(e.id),
        })),
    }
    await api.put('/api/approval-rights', payload)
    await loadCandidates()
    alert('บันทึกสิทธิ์อนุมัติสำเร็จ')
  } catch (e) {
    alert('บันทึกไม่สำเร็จ: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}

watch(candidates, computeDirty, { deep: true })

onMounted(async () => {
  await loadApprovers()
})
</script>
