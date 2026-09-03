-- =====================================================
-- FIX: Close open rounds + fix date for NTC 007/008
-- Run on server after migration
-- =====================================================

-- 1. แสดง open rounds ที่ต้องปิด
SELECT a.id, e.employee_code, e.name, a.date, a.check_in, a.round_no
FROM attendance_logs a
JOIN employees e ON a.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
AND a.check_out IS NULL
ORDER BY a.date, a.round_no;

-- 2. ปิด open rounds ของ 007/008 (ตั้ง check_out = check_in + 8 ชม.)
UPDATE attendance_logs a
JOIN employees e ON a.emp_id = e.id
SET a.check_out = ADDTIME(a.check_in, '08:00:00'),
    a.updated_at = NOW()
WHERE e.employee_code IN ('007', '008')
AND a.check_out IS NULL;

-- 3. แสดง attendance_logs ทั้งหมดของ 007/008 หลังแก้
SELECT e.employee_code, e.name, a.date, a.check_in, a.check_out, a.check_in_status, a.late_minutes, a.round_no
FROM attendance_logs a
JOIN employees e ON a.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
ORDER BY a.date DESC, a.round_no DESC;

-- 4. แสดง LateForcedLeave ทั้งหมดของ 007/008 หลัง migration
SELECT e.employee_code, e.name, lfl.date, lfl.late_minutes, lfl.status
FROM late_forced_leaves lfl
JOIN employees e ON lfl.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
ORDER BY lfl.date DESC;
