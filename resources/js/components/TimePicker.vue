<template>
  <div class="inline-block">
    <button
      type="button"
      @click="openPicker"
      class="input-field text-sm text-center font-mono tabular-nums"
      :class="btnClass"
    >
      {{ displayValue }}
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4"
        @click.self="close"
      >
        <div class="bg-white rounded-2xl shadow-xl p-5 w-[260px] select-none">
          <!-- พิมพ์แก้ไขตรงๆ ได้ -->
          <div class="flex items-center justify-center gap-1 mb-4">
            <input
              v-model="hourText"
              @focus="$event.target.select()"
              @blur="commitHourText"
              @keyup.enter="$event.target.blur()"
              inputmode="numeric"
              maxlength="2"
              class="w-14 text-center text-2xl font-bold tabular-nums border border-gray-200 rounded-lg py-1 focus:border-blue-500 focus:outline-none"
            />
            <span class="text-2xl font-bold text-gray-300">:</span>
            <input
              v-model="minuteText"
              @focus="$event.target.select()"
              @blur="commitMinuteText"
              @keyup.enter="$event.target.blur()"
              inputmode="numeric"
              maxlength="2"
              class="w-14 text-center text-2xl font-bold tabular-nums border border-gray-200 rounded-lg py-1 focus:border-blue-500 focus:outline-none"
            />
            <span class="text-xs text-gray-400 ml-1 self-end mb-2">24 ชม.</span>
          </div>

          <!-- หรือเลื่อนเลือกจากล้อตัวเลข -->
          <div class="flex justify-center gap-3 relative">
            <div class="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 h-9 bg-blue-50 rounded-lg z-0"></div>

            <div ref="hourWheel" tabindex="-1" class="wheel" @scroll="onScroll('hour')">
              <div class="wheel-pad"></div>
              <div
                v-for="h in 24" :key="'h' + h"
                class="wheel-item"
                :class="{ 'wheel-item--active': hour === h - 1 }"
                @click="selectAndScroll('hour', h - 1)"
              >{{ pad(h - 1) }}</div>
              <div class="wheel-pad"></div>
            </div>

            <div ref="minuteWheel" tabindex="-1" class="wheel" @scroll="onScroll('minute')">
              <div class="wheel-pad"></div>
              <div
                v-for="m in 60" :key="'m' + m"
                class="wheel-item"
                :class="{ 'wheel-item--active': minute === m - 1 }"
                @click="selectAndScroll('minute', m - 1)"
              >{{ pad(m - 1) }}</div>
              <div class="wheel-pad"></div>
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="close" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
            <button type="button" @click="confirm" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">เสร็จสิ้น</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' }, // "HH:mm", 24-hour
  btnClass: { type: String, default: 'w-24' },
})
const emit = defineEmits(['update:modelValue'])

const ITEM_H = 36 // ต้องตรงกับ .wheel-item { height } ใน <style> ด้านล่าง

const pad = (n) => String(n).padStart(2, '0')
const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n))

const [initH, initM] = (props.modelValue || '08:00').split(':').map((v) => parseInt(v, 10) || 0)
const hour = ref(initH)
const minute = ref(initM)
const hourText = ref(pad(initH))
const minuteText = ref(pad(initM))
const open = ref(false)
const hourWheel = ref(null)
const minuteWheel = ref(null)

const displayValue = computed(() => (props.modelValue ? `${pad(hour.value)}:${pad(minute.value)}` : '--:--'))

function scrollWheelTo(which, value, smooth = false) {
  const el = which === 'hour' ? hourWheel.value : minuteWheel.value
  if (!el) return
  if (smooth) el.scrollTo({ top: value * ITEM_H, behavior: 'smooth' })
  else el.scrollTop = value * ITEM_H
}

async function openPicker() {
  const [h, m] = (props.modelValue || '08:00').split(':').map((v) => parseInt(v, 10) || 0)
  hour.value = h
  minute.value = m
  hourText.value = pad(h)
  minuteText.value = pad(m)
  open.value = true
  await nextTick()
  scrollWheelTo('hour', h)
  scrollWheelTo('minute', m)
}

function close() {
  open.value = false
}

function confirm() {
  emit('update:modelValue', `${pad(hour.value)}:${pad(minute.value)}`)
  open.value = false
}

let scrollTimer = null
function onScroll(which) {
  const el = which === 'hour' ? hourWheel.value : minuteWheel.value
  const max = which === 'hour' ? 23 : 59
  clearTimeout(scrollTimer)
  scrollTimer = setTimeout(() => {
    const idx = clamp(Math.round(el.scrollTop / ITEM_H), 0, max)
    if (which === 'hour') { hour.value = idx; hourText.value = pad(idx) }
    else { minute.value = idx; minuteText.value = pad(idx) }
  }, 60)
}

function selectAndScroll(which, value) {
  if (which === 'hour') { hour.value = value; hourText.value = pad(value) }
  else { minute.value = value; minuteText.value = pad(value) }
  scrollWheelTo(which, value, true)
}

function commitHourText() {
  let v = parseInt(hourText.value, 10)
  if (isNaN(v)) v = hour.value
  v = clamp(v, 0, 23)
  hour.value = v
  hourText.value = pad(v)
  scrollWheelTo('hour', v, true)
}

function commitMinuteText() {
  let v = parseInt(minuteText.value, 10)
  if (isNaN(v)) v = minute.value
  v = clamp(v, 0, 59)
  minute.value = v
  minuteText.value = pad(v)
  scrollWheelTo('minute', v, true)
}
</script>

<style scoped>
.wheel {
  height: 180px; /* 5 แถวที่มองเห็น x ITEM_H */
  width: 64px;
  overflow-y: scroll;
  scroll-snap-type: y mandatory;
  scrollbar-width: none;
  position: relative;
  z-index: 1;
  outline: none;
}
.wheel::-webkit-scrollbar { display: none; }
.wheel-pad { height: 72px; } /* (180 - 36) / 2 - เว้นให้แถวแรก/แถวสุดท้ายเลื่อนมาอยู่กลางได้ */
.wheel-item {
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  scroll-snap-align: center;
  font-size: 15px;
  font-weight: 500;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}
.wheel-item--active {
  color: #1d4ed8;
  font-weight: 700;
  font-size: 17px;
}
</style>
