# Attendance ETC1992 - Deployment Guide

## Prerequisites

| Component | Version | Purpose |
|-----------|---------|---------|
| PHP | 8.4+ | Laravel backend |
| MySQL/MariaDB | 10.6+ | Database |
| Nginx | Latest | Web server |
| Python | 3.10+ | Face recognition API |
| Node.js | 18+ | Frontend build |
| Composer | 2.x | PHP dependencies |

## Quick Start

```bash
# 1. Clone repository
git clone https://github.com/your-repo/attendance-laravel.git
cd attendance-laravel

# 2. Run deployment script
chmod +x scripts/deploy.sh
sudo ./scripts/deploy.sh

# 3. Setup Nginx
chmod +x scripts/setup-nginx.sh
sudo ./scripts/setup-nginx.sh

# 4. Setup SSL
chmod +x scripts/setup-ssl.sh
sudo ./scripts/setup-ssl.sh
```

## Server Setup (Ubuntu 22.04/24.04)

### 1. Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y git curl wget unzip software-properties-common

# Create deployment user (optional)
sudo adduser deploy
sudo usermod -aG sudo deploy
```

### 2. Install PHP 8.4

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-gd php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-redis
```

### 3. Install MySQL

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE attendance_etc1992 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'attendance_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON attendance_etc1992.* TO 'attendance_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

### 5. Install Python 3.10+

```bash
sudo apt install -y python3-pip python3-venv
```

### 6. Install Node.js 18+

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

## Deployment Steps

### Step 1: Clone & Configure

```bash
cd /var/www
sudo git clone https://github.com/your-repo/attendance-laravel.git
cd attendance-laravel

# Copy environment file
cp .env.production.example .env

# Generate application key
php artisan key:generate
```

### Step 2: Configure Environment

Edit `.env` file:

```bash
nano .env
```

Update these values:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://attendance.etc1992.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_etc1992
DB_USERNAME=attendance_user
DB_PASSWORD=your_secure_password
```

### Step 3: Install Dependencies

```bash
# PHP dependencies
composer install --optimize-autoloader --no-dev

# Node dependencies & build
npm install
npm run build
```

### Step 4: Run Migrations

```bash
php artisan migrate --force
php artisan db:seed --force
```

### Step 5: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/attendance-etc1992
sudo chmod -R 755 /var/www/attendance-etc1992/storage
sudo chmod -R 755 /var/www/attendance-etc1992/bootstrap/cache
```

### Step 6: Configure Nginx

```bash
sudo ./scripts/setup-nginx.sh
```

## SSL Configuration (ServBot)

### Step 1: Add Domain to ServBot

1. Login to ServBot dashboard
2. Add domain: `attendance.etc1992.com`
3. Verify domain ownership

### Step 2: Download Certificate

1. Download certificate files from ServBot
2. Extract the zip file

### Step 3: Install Certificate

```bash
# Create SSL directory
sudo mkdir -p /etc/ssl/attendance.etc1992.com

# Copy certificate files
sudo cp fullchain.pem /etc/ssl/attendance.etc1992.com/
sudo cp privkey.pem /etc/ssl/attendance.etc1992.com/

# Set permissions
sudo chmod 600 /etc/ssl/attendance.etc1992.com/*

# Reload Nginx
sudo systemctl reload nginx
```

## Python Face Recognition API

### Step 1: Setup Virtual Environment

```bash
cd /var/www/attendance-etc1992/python

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Setup models
python setup_models.py
```

### Step 2: Create Systemd Service

```bash
sudo tee /etc/systemd/system/face-api.service > /dev/null <<EOF
[Unit]
Description=Face Recognition API
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/attendance-etc1992/python
ExecStart=/var/www/attendance-etc1992/python/venv/bin/python face_api.py
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable face-api
sudo systemctl start face-api
```

### Step 3: Verify Service

```bash
sudo systemctl status face-api
curl http://127.0.0.1:8000/face/health
```

## Database Migration from Supabase

### Run Seeder

```bash
php artisan db:seed --class=SupabaseMigrationSeeder
```

### What Gets Migrated

- Companies
- Employees (with pagination)
- Attendance logs
- Leave types

## Frontend Build

```bash
# Install dependencies
npm install

# Development build
npm run dev

# Production build
npm run build
```

## Queue Worker (Optional)

```bash
# Create queue worker service
sudo tee /etc/systemd/system/attendance-worker.service > /dev/null <<EOF
[Unit]
Description=Attendance Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/attendance-etc1992
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable attendance-worker
sudo systemctl start attendance-worker
```

## Cron Jobs

```bash
# Edit www-data crontab
sudo crontab -u www-data -e

# Add Laravel scheduler
* * * * * cd /var/www/attendance-etc1992 && php artisan schedule:run >> /dev/null 2>&1
```

## Database Backup

### Manual Backup

```bash
chmod +x scripts/backup-db.sh
sudo ./scripts/backup-db.sh
```

### Automated Backup (Daily)

```bash
# Add to root crontab
sudo crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * /var/www/attendance-etc1992/scripts/backup-db.sh
```

## Troubleshooting

### Issue: 500 Internal Server Error

```bash
# Check Laravel logs
tail -f /var/www/attendance-etc1992/storage/logs/laravel.log

# Check PHP error log
tail -f /var/log/php8.4-fpm.log

# Fix permissions
sudo chown -R www-data:www-data /var/www/attendance-etc1992
sudo chmod -R 755 /var/www/attendance-etc1992/storage
```

### Issue: Nginx 502 Bad Gateway

```bash
# Check PHP-FPM status
sudo systemctl status php8.4-fpm

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Check socket exists
ls -la /run/php/php8.4-fpm.sock
```

### Issue: Face API Not Working

```bash
# Check service status
sudo systemctl status face-api

# Check logs
sudo journalctl -u face-api -f

# Test API directly
curl http://127.0.0.1:8000/face/health
```

### Issue: SSL Certificate Error

```bash
# Verify certificate files
sudo openssl x509 -in /etc/ssl/attendance.etc1992.com/fullchain.pem -text -noout

# Check Nginx config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### Issue: Database Connection Failed

```bash
# Test MySQL connection
mysql -u attendance_user -p attendance_etc1992

# Check MySQL status
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql
```

## Performance Optimization

### Enable OPcache

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

### Enable Gzip in Nginx

Already configured in `setup-nginx.sh`.

### Laravel Optimization

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Monitoring

### Check Services

```bash
# All services status
sudo systemctl status nginx php8.4-fpm mysql face-api

# Check disk usage
df -h

# Check memory usage
free -m

# Check active connections
sudo netstat -tlnp
```

## Support

For issues, contact the development team or create an issue on GitHub.
