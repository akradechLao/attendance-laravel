-- ============================================
-- ETC1992 Employee Assignment
-- Run AFTER Step 1 (office_locations import)
-- ============================================

-- Step 2a: ดูพนักงาน ETC1992 ทั้งหมด (company_id = 1)
SELECT e.id, e.employee_code, e.first_name, e.last_name, e.nickname
FROM employees e
WHERE e.company_id = 1
ORDER BY e.employee_code;

-- Step 2b: ดู office_locations ทั้งหมด
SELECT id, name FROM office_locations WHERE company_id = 1 ORDER BY id;

-- ============================================
-- Step 3: Assign พนักงานแต่ละคนกับสาขา
-- แก้ไข employee_code และ location_id ตามจริง
-- ============================================

-- ตัวอย่าง: assign แบบ manual
-- INSERT INTO employee_office_locations (employee_id, office_location_id, created_at, updated_at) VALUES
-- (1, 22, NOW(), NOW()),   -- พนักงาน ID 1 → ETC 999
-- (2, 25, NOW(), NOW());   -- พนักงาน ID 2 → ETC 683

-- ============================================
-- Step 4: ตรวจสอบผลลัพธ์
-- ============================================
SELECT
    ol.name as branch,
    COUNT(eol.employee_id) as employee_count
FROM office_locations ol
LEFT JOIN employee_office_locations eol ON ol.id = eol.office_location_id
WHERE ol.company_id = 1
GROUP BY ol.id, ol.name
ORDER BY employee_count DESC;
