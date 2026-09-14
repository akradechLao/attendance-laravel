const THAI_MONTHS_SHORT = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']

/** "2026-09-14" -> "14 ก.ย." */
export function shortThaiDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr + (dateStr.length <= 10 ? 'T00:00:00' : ''))
  if (isNaN(d)) return dateStr
  return `${d.getDate()} ${THAI_MONTHS_SHORT[d.getMonth()]}`
}

/** "08:00:00" / "08:00" -> "08:00" */
export function shortTime(timeStr) {
  if (!timeStr) return null
  return timeStr.slice(0, 5)
}

/**
 * คู่วันที่+เวลาเช็คอิน/เช็คเอาท์แบบย่อ สำหรับแสดงในตาราง (ประหยัดพื้นที่กว่าแยกคอลัมน์วันที่)
 * เช็คเอาท์ที่เวลาน้อยกว่าเช็คอิน (ข้ามเที่ยงคืน) จะเลื่อนวันที่แสดงผล +1 วันให้อัตโนมัติ
 */
export function checkInPair(date, checkIn) {
  const time = shortTime(checkIn)
  if (!time) return { date: null, time: null }
  return { date: shortThaiDate(date), time }
}

export function checkOutPair(date, checkIn, checkOut) {
  const time = shortTime(checkOut)
  if (!time) return { date: null, time: null }
  let d = date
  if (checkIn && checkOut < checkIn) {
    const parsed = new Date(date + 'T00:00:00')
    parsed.setDate(parsed.getDate() + 1)
    d = parsed.toISOString().slice(0, 10)
  }
  return { date: shortThaiDate(d), time }
}
