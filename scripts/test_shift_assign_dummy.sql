-- =====================================================
-- DUMMY DATA: ทดสอบระบบ มอบหมายกะ工作
-- =====================================================

-- 1. ดู employee_shifts ตัวอย่างของแต่ละบริษัท
SELECT e.company_id, c.name as company_name, e.employee_code, e.name as emp_name,
       ws.group_number, ws.start_time, ws.end_time
FROM employee_shifts es
JOIN employees e ON es.employee_id = e.id
JOIN companies c ON e.company_id = c.id
JOIN work_shifts ws ON es.work_shift_id = ws.id
WHERE e.company_id IN (1,2,3,4)
AND es.start_date IS NULL
ORDER BY e.company_id, e.employee_code
LIMIT 20;

-- 2. แสดงพนักงานที่มี available shifts มากกว่า 1 กะ (กลุ่มที่ต้อง assign)
SELECT e.company_id, c.name as company_name, e.employee_code, e.name as emp_name,
       COUNT(es.id) as shift_count,
       GROUP_CONCAT(CONCAT('WC', LPAD(ws.group_number + 1, 4, '0'), '(', ws.start_time, '-', ws.end_time, ')') SEPARATOR ', ') as available_shifts
FROM employee_shifts es
JOIN employees e ON es.employee_id = e.id
JOIN companies c ON e.company_id = c.id
JOIN work_shifts ws ON es.work_shift_id = ws.id
WHERE e.company_id IN (1,2,3,4)
AND es.start_date IS NULL
GROUP BY e.id, e.company_id, c.name, e.employee_code, e.name
HAVING shift_count > 1
ORDER BY e.company_id, e.employee_code
LIMIT 20;
