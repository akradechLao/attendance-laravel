-- =====================================================
-- Diagnostic Query: NTC จิรายุ(007) / พีรภาส(008)
-- =====================================================

-- 1. ตรวจสอบ employee_shifts ของ 007/008 (ควรมี WC0015 = group 14 = 07:00-16:00)
SELECT e.employee_code, e.name, ws.group_number, ws.start_time, ws.end_time, es.start_date, es.end_date
FROM employees e
LEFT JOIN employee_shifts es ON e.id = es.employee_id
LEFT JOIN work_shifts ws ON es.work_shift_id = ws.id
WHERE e.employee_code IN ('007', '008');

-- 2. ตรวจสอบ office_location ของ NTC
SELECT ol.id, ol.name, ol.work_start_time, ol.work_end_time
FROM office_locations ol
WHERE ol.id = (SELECT office_location_id FROM employees WHERE employee_code = '001' LIMIT 1);

-- 3. ตรวจสอบ attendance_logs ล่าสุดของ 007/008
SELECT e.employee_code, e.name, a.date, a.check_in, a.check_out, a.check_in_status, a.late_minutes, a.round_no
FROM attendance_logs a
JOIN employees e ON a.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
ORDER BY a.date DESC, a.round_no DESC
LIMIT 20;

-- 4. ตรวจสอบ LateForcedLeave ของ 007/008
SELECT e.employee_code, e.name, lfl.date, lfl.late_minutes, lfl.status
FROM late_forced_leaves lfl
JOIN employees e ON lfl.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
ORDER BY lfl.date DESC;

-- 5. ตรวจสอบ attendance_logs ที่ยังไม่ได้เช็คเอาท์ (check_out IS NULL)
SELECT e.employee_code, e.name, a.date, a.check_in, a.round_no
FROM attendance_logs a
JOIN employees e ON a.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
AND a.check_out IS NULL
ORDER BY a.date DESC;

-- 6. ตรวจสอบ shift_schedules ของ 007/008 (ควรว่างหลังลบ)
SELECT e.employee_code, ss.work_date, ss.shift_code
FROM shift_schedules ss
JOIN employees e ON ss.emp_id = e.id
WHERE e.employee_code IN ('007', '008')
ORDER BY ss.work_date DESC;
