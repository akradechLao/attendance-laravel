# คู่มือ Deploy บน aaPanel + Cloudflare (ฟรี)

## ภาพรวม

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   User ──▶ Cloudflare (Free) ──▶ Your Server               │
│              │                    │                         │
│              ├── DNS              ├── aaPanel               │
│              ├── SSL              │   ├── Nginx             │
│              ├── CDN              │   ├── PHP 8.4           │
│              └── DDoS Protection  │   ├── MariaDB 11       │
│                                   │   └── Python 3.10+      │
│                                   │                         │
│                                   └── Laravel App           │
│                                       ├── Vue.js Frontend   │
│                                       └── Python Face API   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 1: ตั้งค่า aaPanel

### 1.1 ติดตั้ง aaPanel (ถ้ายังไม่ได้ติดตั้ง)

```bash
# SSH เข้า server
ssh root@YOUR_SERVER_IP

# ติดตั้ง aaPanel
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh
sudo bash install.sh aapanel
```

### 1.2 Login เข้า aaPanel

```
URL: http://YOUR_SERVER_IP:8888/XXXXXX
Username: admin
Password: (กำหนดเองตอนติดตั้ง)
```

### 1.3 ติดตั้ง Software ผ่าน aaPanel

```
ไปที่ App Store → ติดตั้ง:
├── Nginx (เวอร์ชันล่าสุด)
├── PHP 8.4
├── MariaDB (หรือ MySQL)
└── PHP Composer
```

### 1.4 สร้าง Database

```
ไปที่ Database → เพิ่ม:
├── Database Name: attendance_northernthai
├── User: attendance_user
└── Password: YOUR_PASSWORD
```

### 1.5 เพิ่ม PHP Project

```
ไปที่ Website → PHP Project → เพิ่ม:
├── Domain: attendance.northernthai.co.th
├── Root: /www/wwwroot/attendance.northernthai.co.th/public
└── PHP: 8.4
```

---

## Phase 2: Deploy Laravel

### 2.1 SSH เข้า Server

```bash
ssh root@YOUR_SERVER_IP
```

### 2.2 Clone โค้ด

```bash
cd /www/wwwroot
git clone https://github.com/akradechLao/attandance-laravel.git attendance.northernthai.co.th
cd attendance.northernthai.co.th
```

### 2.3 ติดตั้ง Composer Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### 2.4 ตั้งค่า Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 2.5 แก้ไขไฟล์ .env

```bash
nano .env
```

แก้ไข:

```
APP_NAME="Attendance ETC1992"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://attendance.northernthai.co.th

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_northernthai
DB_USERNAME=attendance_user
DB_PASSWORD=YOUR_PASSWORD

FACE_RECOGNITION_API_URL=http://127.0.0.1:8000

SANCTUM_STATEFUL_DOMAINS=attendance.northernthai.co.th
```

### 2.6 Run Migration

```bash
php artisan migrate --force
```

### 2.7 Build Frontend

```bash
npm install
npm run build
```

### 2.8 ตั้งค่า Permissions

```bash
chown -R www-data:www-data /www/wwwroot/attendance.northernthai.co.th
chmod -R 755 /www/wwwroot/attendance.northernthai.co.th/storage
chmod -R 755 /www/wwwroot/attendance.northernthai.co.th/bootstrap/cache
```

---

## Phase 3: ตั้งค่า Python Face API

### 3.1 ติดตั้ง Python Dependencies

```bash
cd /www/wwwroot/attendance.northernthai.co.th/python
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python setup_models.py
```

### 3.2 ตั้งค่า systemd Service

```bash
tee /etc/systemd/system/face-api.service > /dev/null <<EOF
[Unit]
Description=Face Recognition API
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/www/wwwroot/attendance.northernthai.co.th/python
ExecStart=/www/wwwroot/attendance.northernthai.co.th/python/venv/bin/python face_api.py
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable face-api
systemctl start face-api
```

---

## Phase 4: ตั้งค่า Cloudflare

### 4.1 สมัคร Cloudflare

```
1. ไปที่ https://dash.cloudflare.com/sign-up
2. สมัครบัญชีใหม่
```

### 4.2 เพิ่ม Domain

```
1. Login Cloudflare
2. กด "Add a site"
3. ใส่ Domain: northernthai.co.th
4. เลือก Free plan
```

### 4.3 ตั้งค่า DNS

```
ไปที่ DNS → Records → Add record:

Type: A
Name: attendance
Content: YOUR_SERVER_IP
Proxy: ปิด (DNS only) ← สำคัญ!
```

### 4.4 เปลี่ยน Nameserver

```
1. คัดลอก Nameserver จาก Cloudflare
   - ns1.cloudflare.com
   - ns2.cloudflare.com

2. ไปที่ Domain Registrar (ที่ซื้อ domain)
   - เปลี่ยน Nameserver เป็นของ Cloudflare
```

### 4.5 ตั้งค่า SSL

```
ไปที่ SSL/TLS → Overview:
 เลือก: Full (Strict)
```

---

## Phase 5: ตั้งค่า SSL ผ่าน aaPanel

```
ไปที่ Website → PHP Project → SSL:

1. เลือก Domain: attendance.northernthai.co.th
2. เลือก Let's Encrypt
3. กด Apply
```

---

## Phase 6: ตั้งค่า Auto Deploy

### 6.1 ตั้งค่า Webhook

```bash
# รัน script ตั้งค่า
bash scripts/setup-auto-deploy.sh
```

### 6.2 ตั้งค่า GitHub Webhook

```
1. ไปที่ GitHub Repo:
   https://github.com/akradechLao/attandance-laravel

2. ไปที่ Settings → Webhooks → Add webhook

3. ตั้งค่า:
   - Payload URL: https://attendance.northernthai.co.th/webhook.php
   - Content type: application/json
   - Secret: (ใส่ secret ที่ได้จาก script)
   - Events: Just the push event

4. กด Add webhook
```

### 6.3 ทดสอบ Auto Deploy

```bash
# บนเครื่อง dev
cd D:\attendance-laravel
echo "// test" >> README.md
git add .
git commit -m "test auto deploy"
git push origin main

# ตรวจสอบ log บน server
tail -f /var/log/auto-deploy.log
```

---

## Phase 7: ทดสอบ

### 7.1 ทดสอบเข้าเว็บ

```
https://attendance.northernthai.co.th
```

### 7.2 ทดสอบ Kiosk

```
https://attendance.northernthai.co.th/employee
```

### 7.3 ทดสอบ Face API

```
https://attendance.northernthai.co.th/face/health
```

### 7.4 ทดสอบ Login

```
https://attendance.northernthai.co.th/login
```

---

## Checklist

```
aaPanel
 ☐ aaPanel ติดตั้งแล้ว
 ☐ Nginx ติดตั้งแล้ว
 ☐ PHP 8.4 ติดตั้งแล้ว
 ☐ MariaDB ติดตั้งแล้ว
 ☐ Database สร้างแล้ว
 ☐ PHP Project เพิ่มแล้ว

Server
 ☐ Clone โค้ดแล้ว
 ☐ composer install แล้ว
 ☐ .env ตั้งค่าแล้ว
 ☐ Migration รันแล้ว
 ☐ npm install + build แล้ว
 ☐ Permissions ตั้งค่าแล้ว

Python Face API
 ☐ Virtual environment สร้างแล้ว
 ☐ Dependencies ติดตั้งแล้ว
 ☐ Models ดาวน์โหลดแล้ว
 ☐ systemd service ตั้งค่าแล้ว

Cloudflare
 ☐ Account สร้างแล้ว
 ☐ Domain เพิ่มแล้ว
 ☐ DNS A record ตั้งค่าแล้ว
 ☐ Nameserver เปลี่ยนแล้ว
 ☐ SSL: Full (Strict)

SSL
 ☐ Let's Encrypt ตั้งค่าแล้ว

Auto Deploy
 ☐ Webhook script ตั้งค่าแล้ว
 ☐ GitHub webhook ตั้งค่าแล้ว
 ☐ ทดสอบ auto deploy แล้ว

Testing
 ☐ Login ได้
 ☐ Kiosk ทำงาน
 ☐ Face Recognition ทำงาน
 ☐ Check-in/out ได้

Go Live
 ☐ Training แล้ว
 ☐ Backup ตั้งค่าแล้ว
 ☐ Go live!
```

---

## อัพเดตโค้ดหลังแก้ไข

### วิธี Manual

```bash
# บนเครื่อง dev
git add .
git commit -m "แก้ไข..."
git push origin main

# บน server (ถ้าไม่ได้ตั้ง auto deploy)
cd /www/wwwroot/attendance.northernthai.co.th
git pull origin main
composer install
npm run build
php artisan config:cache
chown -R www-data:www-data .
```

### วิธี Auto (ตั้งค่าแล้ว)

```bash
# บนเครื่อง dev
git add .
git commit -m "แก้ไข..."
git push origin main

# Server update อัตโนมัติ!
```

---

## ปัญหาที่พบบ่อย

### 1. เข้าเว็บไม่ได้

```bash
# ตรวจสอบ Nginx
systemctl status nginx

# ตรวจสอบ logs
tail -f /var/log/nginx/error.log
```

### 2. Face API ไม่ทำงาน

```bash
# ตรวจสอบ service
systemctl status face-api

# Restart
systemctl restart face-api
```

### 3. Database Error

```bash
# ตรวจสอบ connection
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'Connected' : 'Failed';"
```

### 4. Permission Error

```bash
chown -R www-data:www-data /www/wwwroot/attendance.northernthai.co.th
chmod -R 755 /www/wwwroot/attendance.northernthai.co.th/storage
```
