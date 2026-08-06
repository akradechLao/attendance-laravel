#!/bin/bash

# ============================================
# Attendance ETC1992 - First Deployment Script
# สำหรับ aaPanel + Cloudflare
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
echo "  Attendance ETC1992 - First Deployment"
echo "  for aaPanel + Cloudflare"
echo "==========================================="
echo ""

# ============================================
# Configuration - แก้ไขตรงนี้
# ============================================
APP_DIR="/www/wwwroot/attendance.northernthai.co.th"
REPO_URL="https://github.com/akradechLao/attandance-laravel.git"
DOMAIN="attendance.northernthai.co.th"

# Database (แก้ไขให้ตรงกับที่ตั้งค่าใน aaPanel)
DB_NAME="sql_attendance_northernthai_co_th"
DB_USER="sql_attendance_northernthai_co_th"
DB_PASS="c66bc5b516ce"  # ← แก้ไขตรงนี้

# ============================================
# Step 1: Clone โค้ด
# ============================================
log_info "Step 1: Cloning repository..."

if [ -d "$APP_DIR" ]; then
    log_warning "Directory exists, backing up..."
    mv $APP_DIR ${APP_DIR}_backup_$(date +%Y%m%d_%H%M%S)
fi

git clone $REPO_URL $APP_DIR
cd $APP_DIR

log_success "Repository cloned"

echo ""

# ============================================
# Step 2: ติดตั้ง Composer Dependencies
# ============================================
log_info "Step 2: Installing Composer dependencies..."

composer install --optimize-autoloader --no-dev

log_success "Composer dependencies installed"

echo ""

# ============================================
# Step 3: ตั้งค่า Environment
# ============================================
log_info "Step 3: Setting up environment..."

cp .env.example .env
php artisan key:generate

# แก้ไข .env
sed -i 's/APP_NAME=.*/APP_NAME="Attendance ETC1992"/' .env
sed -i 's/APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env

sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/DB_PORT=.*/DB_PORT=3306/' .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env

sed -i 's|FACE_RECOGNITION_API_URL=.*|FACE_RECOGNITION_API_URL=http://127.0.0.1:8000|' .env
sed -i "s/SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS=$DOMAIN/" .env

sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
sed -i 's/CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env

log_success "Environment configured"

echo ""

# ============================================
# Step 4: Run Database Migration
# ============================================
log_info "Step 4: Running database migration..."

php artisan migrate --force

log_success "Database migrated"

echo ""

# ============================================
# Step 5: Build Frontend
# ============================================
log_info "Step 5: Building frontend..."

npm install
npm run build

log_success "Frontend built"

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
# Step 7: Optimize Laravel
# ============================================
log_info "Step 7: Optimizing Laravel..."

php artisan config:cache
php artisan route:cache
php artisan view:cache

log_success "Laravel optimized"

echo ""

# ============================================
# สรุป
# ============================================
echo "==========================================="
echo -e "${GREEN}Deployment Complete!${NC}"
echo "==========================================="
echo ""
echo "URL: https://$DOMAIN"
echo ""
echo "Next steps:"
echo "1. เปิด https://$DOMAIN ใน browser"
echo "2. ตั้งค่า SSL ผ่าน aaPanel"
echo "3. ตั้งค่า Python Face API"
echo "4. ตั้งค่า Cloudflare DNS"
echo ""
echo "==========================================="
