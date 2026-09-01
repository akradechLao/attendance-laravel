/**
 * Offline Queue — ระบบเก็บข้อมูลออฟไลน์แล้วส่งอัตโนมัติเมื่อเน็ตกลับมา
 *
 * ใช้ localStorage เป็น persistent storage ( survives page refresh )
 * ใช้ API POST ส่งข้อมูลทันที ถ้า fail จะเก็บลง queue แล้วลองส่งใหม่อัตโนมัติ
 */

const QUEUE_KEY = 'attendance_offline_queue'

let _onStatusChange = null
let _onSyncComplete = null
let _syncing = false

// ─── Queue CRUD ───────────────────────────────────────────────

function getQueue() {
  try {
    const raw = localStorage.getItem(QUEUE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function saveQueue(queue) {
  localStorage.setItem(QUEUE_KEY, JSON.stringify(queue))
  if (_onStatusChange) _onStatusChange(queue.length)
}

function enqueue(item) {
  const queue = getQueue()
  queue.push({
    ...item,
    _queuedAt: new Date().toISOString(),
    _id: Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
  })
  saveQueue(queue)
  return queue.length
}

function removeById(id) {
  const queue = getQueue().filter(i => i._id !== id)
  saveQueue(queue)
}

function getCount() {
  return getQueue().length
}

function clear() {
  saveQueue([])
}

// ─── Sync ─────────────────────────────────────────────────────

async function processQueue() {
  if (_syncing) return { synced: 0, failed: 0 }
  _syncing = true

  const queue = getQueue()
  if (queue.length === 0) {
    _syncing = false
    return { synced: 0, failed: 0 }
  }

  let synced = 0
  let failed = 0

  for (const item of queue) {
    try {
      await sendItem(item)
      removeById(item._id)
      if (_onSyncComplete) _onSyncComplete(item)
      synced++
    } catch {
      failed++
    }
  }

  _syncing = false
  if (_onStatusChange) _onStatusChange(getQueue().length)
  return { synced, failed }
}

async function sendItem(item) {
  const { type, url, data } = item

  if (type === 'face_register') {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data),
    })
    if (!resp.ok) {
      const json = await resp.json().catch(() => null)
      throw new Error(json?.message || `HTTP ${resp.status}`)
    }
    return resp.json()
  }

  throw new Error(`Unknown queue item type: ${type}`)
}

// ─── Auto-sync on network reconnect ───────────────────────────

function startAutoSync() {
  if (typeof window === 'undefined') return

  window.addEventListener('online', () => {
    processQueue()
  })
}

// ─── Status listener ──────────────────────────────────────────

function onStatusChange(callback) {
  _onStatusChange = callback
  callback(getCount())
}

function onSyncComplete(callback) {
  _onSyncComplete = callback
}

// ─── Public API ───────────────────────────────────────────────

export default {
  enqueue,
  removeById,
  getCount,
  clear,
  processQueue,
  startAutoSync,
  onStatusChange,
  onSyncComplete,
}
