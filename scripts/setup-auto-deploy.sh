#!/bin/bash

# ============================================
# Attendance ETC1992 - Auto Deploy Webhook
# ตั้งค่าครั้งเดียว → push ปุ๊บ server update อัตโนมัติ
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
echo "  Auto Deploy Setup"
echo "==========================================="
echo ""

# ============================================
# Configuration
# ============================================
APP_DIR="/www/wwwroot/attendance.northernthai.co.th"
WEBHOOK_SECRET=$(openssl rand -hex 32)

# ============================================
# Step 1: สร้าง Deploy Script
# ============================================
log_info "Step 1: Creating deploy script..."

tee $APP_DIR/deploy.sh > /dev/null <<'DEPLOY_EOF'
#!/bin/bash

APP_DIR="/www/wwwroot/attendance.northernthai.co.th"
LOG_FILE="/var/log/auto-deploy.log"

echo "$(date): Deploy started" >> $LOG_FILE

cd $APP_DIR

# Backup .env
cp .env .env.backup

# Pull latest code
git pull origin main 2>> $LOG_FILE

# Install dependencies
composer install --optimize-autoloader --no-dev 2>> $LOG_FILE

# Run migrations
php artisan migrate --force 2>> $LOG_FILE

# Build frontend
npm install 2>> $LOG_FILE
npm run build 2>> $LOG_FILE

# Optimize
php artisan config:cache 2>> $LOG_FILE
php artisan route:cache 2>> $LOG_FILE
php artisan view:cache 2>> $LOG_FILE

# Set permissions
chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR/storage
chmod -R 755 $APP_DIR/bootstrap/cache

# Restart services
systemctl restart php8.4-fpm 2>/dev/null
systemctl restart face-api 2>/dev/null

echo "$(date): Deploy completed" >> $LOG_FILE
DEPLOY_EOF

chmod +x $APP_DIR/deploy.sh

log_success "Deploy script created"

echo ""

# ============================================
# Step 2: สร้าง Webhook Receiver
# ============================================
log_info "Step 2: Creating webhook receiver..."

tee $APP_DIR/webhook.php > /dev/null <<'WEBHOOK_EOF'
<?php
/**
 * GitHub Webhook Receiver for Auto Deploy
 * 
 * วิธีใช้:
 * 1. เซ็ต webhook ใน GitHub repo
 * 2. URL: https://attendance.northernthai.co.th/webhook.php
 * 3. Content type: application/json
 * 4. Secret: (ใส่ secret ที่สร้างไว้)
 */

$secret = 'YOUR_WEBHOOK_SECRET'; // ← แก้ไขตรงนี้

// รับ payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// ตรวจสอบ signature
if ($secret) {
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
}

// ตรวจสอบ event
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event !== 'push') {
    echo json_encode(['status' => 'ignored', 'event' => $event]);
    exit;
}

// ตรวจสอบ branch
$data = json_decode($payload, true);
$branch = $data['ref'] ?? '';
if ($branch !== 'refs/heads/main') {
    echo json_encode(['status' => 'ignored', 'branch' => $branch]);
    exit;
}

// รัน deploy script
$output = [];
$returnCode = 0;
exec('bash /www/wwwroot/attendance.northernthai.co.th/deploy.sh 2>&1', $output, $returnCode);

// ส่ง response
$response = [
    'status' => $returnCode === 0 ? 'success' : 'error',
    'message' => $returnCode === 0 ? 'Deploy completed' : 'Deploy failed',
    'output' => implode("\n", array_slice($output, -20))
];

http_response_code($returnCode === 0 ? 200 : 500);
echo json_encode($response);
WEBHOOK_EOF

log_success "Webhook receiver created"

echo ""

# ============================================
# Step 3: แสดง Webhook Secret
# ============================================
log_info "Step 3: Webhook Secret (สำหรับ GitHub)"

echo ""
echo "==========================================="
echo -e "${YELLOW}Webhook Secret:${NC}"
echo "$WEBHOOK_SECRET"
echo "==========================================="
echo ""
echo "บันทึก secret นี้ไว้ สำหรับตั้งค่าใน GitHub"
echo ""

# ============================================
# Step 4: แสดง instructions
# ============================================
echo "==========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "==========================================="
echo ""
echo "ขั้นตอนต่อไป:"
echo ""
echo "1. ไปที่ GitHub Repo:"
echo "   https://github.com/akradechLao/attandance-laravel"
echo ""
echo "2. ไปที่ Settings → Webhooks → Add webhook"
echo ""
echo "3. ตั้งค่า:"
echo "   - Payload URL: https://$DOMAIN/webhook.php"
echo "   - Content type: application/json"
echo "   - Secret: $WEBHOOK_SECRET"
echo "   - Events: Just the push event"
echo ""
echo "4. กด Add webhook"
echo ""
echo "==========================================="
