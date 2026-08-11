#!/bin/bash
# ============================================
# STC Location Import
# Company ID: 4 (STC)
# Run: sudo bash import-stc-locations.sh
# ============================================

set -e

DB_USER="sql_attendance_northernthai_co_th"
DB_PASS="c66bc5b516ce"
DB_NAME="sql_attendance_northernthai_co_th"
COMPANY_ID=4
RADIUS=200

echo "========================================="
echo "  STC Location Import"
echo "  Company ID: $COMPANY_ID"
echo "  Radius: ${RADIUS}m"
echo "========================================="
echo ""

# Step 1: Create 3 office locations
echo "STEP 1: Creating office locations..."

mysql -u $DB_USER -p$DB_PASS --skip-ssl $DB_NAME -e "
INSERT INTO office_locations (company_id, name, latitude, longitude, radius_meters, is_active, created_at, updated_at) VALUES
($COMPANY_ID, 'STC สำนักงาน (โรงปรับปรุงคุณภาพน้ำเทศบาลนครภูเก็ต)', 7.86481, 98.39439, $RADIUS, 1, NOW(), NOW()),
($COMPANY_ID, 'STC สำนักงานสาขาเชิงทะเล', 8.01347, 98.32608, $RADIUS, 1, NOW(), NOW()),
($COMPANY_ID, 'STC ตลาดสดสาธารณะ 1', 7.88292, 98.38575, $RADIUS, 1, NOW(), NOW());
"

echo "  ✓ Created 3 locations"
echo ""

# Step 2: Get location IDs
echo "STEP 2: Getting location IDs..."

LOC1=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "SELECT id FROM office_locations WHERE company_id = $COMPANY_ID AND name LIKE '%สำนักงาน (โรงปรับปรุง%' LIMIT 1;")
LOC2=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "SELECT id FROM office_locations WHERE company_id = $COMPANY_ID AND name LIKE '%สาขาเชิงทะเล%' LIMIT 1;")
LOC3=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "SELECT id FROM office_locations WHERE company_id = $COMPANY_ID AND name LIKE '%ตลาดสด%' LIMIT 1;")

echo "  สำนักงาน STC (จุดที่ 1): ID = $LOC1"
echo "  สาขาเชิงทะเล (จุดที่ 2): ID = $LOC2"
echo "  ตลาดสดสาธารณะ (จุดที่ 3): ID = $LOC3"
echo ""

# Step 3: Assign employees to จุดที่ 2 (สาขาเชิงทะเล) - 9 people
echo "STEP 3: Assigning employees to สาขาเชิงทะเล (จุดที่ 2)..."

for NAME in "ไกรสาร รอดแก้ว" "วุฒิชัย ดำริห์" "วรวัช เอียดปู" "รพีภัทร พันชั่ง" "พิพัฒน์ สุรินทร์" "มูฮัมหมัดรอสดี อะหมัด" "อรรถพล เงางาม" "พงษ์ศักดิ์ เพ็ชรพันธ์" "กรรณิกา แก้วสามเขียว"; do
    # Try with prefix first, then without
    EMP_ID=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "SELECT id FROM employees WHERE company_id = $COMPANY_ID AND (name LIKE '%$NAME%' OR name REGEXP 'นาย $NAME|นางสาว $NAME|นาง $NAME') LIMIT 1;")
    if [ -n "$EMP_ID" ]; then
        mysql -u $DB_USER -p$DB_PASS --skip-ssl $DB_NAME -e "INSERT IGNORE INTO employee_office_locations (employee_id, office_location_id, created_at, updated_at) VALUES ($EMP_ID, $LOC2, NOW(), NOW());"
        echo "  ✓ $NAME → สาขาเชิงทะเล"
    else
        echo "  ✗ $NAME - ไม่พบในระบบ"
    fi
done
echo ""

# Step 4: Assign employees to จุดที่ 3 (ตลาดสดสาธารณะ) - 3 people
echo "STEP 4: Assigning employees to ตลาดสดสาธารณะ (จุดที่ 3)..."

for NAME in "พลสรรค์ อินทร์นุรักษ์" "วสันต์ สาธุภาค" "ณัฐนนท์ ผสมทรัพย์"; do
    EMP_ID=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "SELECT id FROM employees WHERE company_id = $COMPANY_ID AND (name LIKE '%$NAME%' OR name REGEXP 'นาย $NAME|นางสาว $NAME|นาง $NAME') LIMIT 1;")
    if [ -n "$EMP_ID" ]; then
        mysql -u $DB_USER -p$DB_PASS --skip-ssl $DB_NAME -e "INSERT IGNORE INTO employee_office_locations (employee_id, office_location_id, created_at, updated_at) VALUES ($EMP_ID, $LOC3, NOW(), NOW());"
        echo "  ✓ $NAME → ตลาดสดสาธารณะ"
    else
        echo "  ✗ $NAME - ไม่พบในระบบ"
    fi
done
echo ""

# Step 5: Assign remaining employees to จุดที่ 1 (สำนักงาน STC)
echo "STEP 5: Assigning remaining employees to สำนักงาน STC (จุดที่ 1)..."

# Get all STC employees not yet assigned
REMAINING=$(mysql -u $DB_USER -p$DB_PASS --skip-ssl -N $DB_NAME -e "
SELECT e.id FROM employees e
WHERE e.company_id = $COMPANY_ID
AND e.id NOT IN (SELECT employee_id FROM employee_office_locations WHERE office_location_id IN ($LOC2, $LOC3))
ORDER BY e.employee_code;
")

COUNT=0
for EMP_ID in $REMAINING; do
    mysql -u $DB_USER -p$DB_PASS --skip-ssl $DB_NAME -e "INSERT IGNORE INTO employee_office_locations (employee_id, office_location_id, created_at, updated_at) VALUES ($EMP_ID, $LOC1, NOW(), NOW());"
    COUNT=$((COUNT + 1))
done
echo "  ✓ Assigned $COUNT employees to สำนักงาน STC"
echo ""

# Step 6: Verification
echo "========================================="
echo "  VERIFICATION"
echo "========================================="
mysql -u $DB_USER -p$DB_PASS --skip-ssl $DB_NAME -e "
SELECT ol.name as branch, COUNT(eol.employee_id) as employees
FROM office_locations ol
LEFT JOIN employee_office_locations eol ON ol.id = eol.office_location_id
WHERE ol.company_id = $COMPANY_ID
GROUP BY ol.id, ol.name
ORDER BY ol.id;
"

echo ""
echo "========================================="
echo "  ✅ STC Import Complete!"
echo "========================================="
