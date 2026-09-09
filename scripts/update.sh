#!/bin/bash

# ============================================
# Attendance ETC1992 - Update Script
# สำหรับอัพเดตโค้ดหลังแก้ไข
# ============================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo ""
echo "==========================================="
echo "  Attendance ETC1992 - Update"
echo "==========================================="
echo ""

# ============================================
# Configuration
# ============================================
# ต้องรันด้วยสิทธิ์ root (sudo bash update.sh) - Step 2/4/5 เขียนไฟล์ในโปรเจกต์
# แล้ว Step 6 จะ chown กลับให้ APP_USER ตอนท้าย
APP_DIR="/www/wwwroot/attendance.northernthai.co.th"

# เว็บนี้รันด้วย user "www" ผ่าน aaPanel + OpenLiteSpeed - ไม่ใช่ "www-data"
# (นั่นคือ php8.4-fpm ของระบบปฏิบัติการที่ติดตั้งไว้เฉยๆ ไม่ได้ให้บริการเว็บนี้จริง)
APP_USER="www"

# PHP 8.4 ตัวจริงที่ php-fpm ของเว็บนี้ใช้ - ไม่ใช่ /usr/bin/php (นั่นคือ PHP 8.3 ของระบบ)
PHP_BIN="/www/server/php/84/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"

if [ "$(id -u)" -ne 0 ]; then
    log_error "ต้องรันด้วย sudo หรือ root: sudo bash $0"
    exit 1
fi
if [ ! -x "$PHP_BIN" ]; then
    log_error "ไม่พบ PHP ที่ $PHP_BIN - เช็ค path ของ aaPanel PHP 8.4 อีกครั้ง (ls /www/server/php/)"
    exit 1
fi

# ============================================
# Step 1: Pull โค้ดล่าสุด
# ============================================
log_info "Step 1: Pulling latest code..."

cd $APP_DIR

# บันทึก .env ไว้ก่อน
cp .env .env.backup

git pull origin main

log_success "Code updated"

echo ""

# ============================================
# Step 2: ติดตั้ง Composer Dependencies (ถ้ามีการเปลี่ยนแปลง)
# ============================================
log_info "Step 2: Installing Composer dependencies..."

$PHP_BIN $COMPOSER_BIN install --optimize-autoloader --no-dev

log_success "Composer dependencies installed"

echo ""

# ============================================
# Step 3: Run Migration (ถ้ามี migration ใหม่)
# ============================================
log_info "Step 3: Running database migration..."

$PHP_BIN artisan migrate --force 2>/dev/null || true

log_success "Database migrated"

echo ""

# ============================================
# Step 4: Build Frontend
# ============================================
log_info "Step 4: Building frontend..."

npm install
npm run build

log_success "Frontend built"

echo ""

# ============================================
# Step 5: Optimize Laravel
# ============================================
log_info "Step 5: Optimizing Laravel..."

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

log_success "Laravel optimized"

echo ""

# ============================================
# Step 6: ตั้งค่า Permissions
# ============================================
log_info "Step 6: Setting permissions..."

chown -R $APP_USER:$APP_USER $APP_DIR
find $APP_DIR/storage -type d -exec chmod 775 {} \;
find $APP_DIR/storage -type f -exec chmod 664 {} \;
find $APP_DIR/bootstrap/cache -type d -exec chmod 775 {} \;
find $APP_DIR/bootstrap/cache -type f -exec chmod 664 {} \;

log_success "Permissions set"

echo ""

# ============================================
# Step 7: Restart Services (ถ้าจำเป็น)
# ============================================
log_info "Step 7: Restarting services..."

# Restart PHP-FPM (aaPanel เก็บ php-fpm แยกตามเวอร์ชันไว้ที่ /etc/init.d/php-fpm-84
# ไม่ใช่ systemd service ชื่อ php8.4-fpm - อันนั้นเป็นของระบบปฏิบัติการที่ไม่ได้ใช้)
/etc/init.d/php-fpm-84 restart 2>/dev/null || systemctl restart php-fpm-84 2>/dev/null || true

# Restart Face API (ถ้า running)
systemctl restart face-api 2>/dev/null || true

log_success "Services restarted"

echo ""

# ============================================
# สรุป
# ============================================
echo "==========================================="
echo -e "${GREEN}Update Complete!${NC}"
echo "==========================================="
echo ""
echo "URL: https://attendance.northernthai.co.th"
echo ""
echo "==========================================="
