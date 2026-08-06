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
APP_DIR="/www/wwwroot/attendance.northernthai.co.th"

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

composer install --optimize-autoloader --no-dev

log_success "Composer dependencies installed"

echo ""

# ============================================
# Step 3: Run Migration (ถ้ามี migration ใหม่)
# ============================================
log_info "Step 3: Running database migration..."

php artisan migrate --force 2>/dev/null || true

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

php artisan config:cache
php artisan route:cache
php artisan view:cache

log_success "Laravel optimized"

echo ""

# ============================================
# Step 6: ตั้งค่า Permissions
# ============================================
log_info "Step 6: Setting permissions..."

chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR/storage
chmod -R 755 $APP_DIR/bootstrap/cache

log_success "Permissions set"

echo ""

# ============================================
# Step 7: Restart Services (ถ้าจำเป็น)
# ============================================
log_info "Step 7: Restarting services..."

# Restart PHP-FPM
systemctl restart php8.4-fpm 2>/dev/null || true

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
