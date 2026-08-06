#!/bin/bash

echo "========================================="
echo "  Attendance ETC1992 - Deployment Script"
echo "========================================="

APP_DIR="/var/www/attendance-etc1992"
PYTHON_DIR="/var/www/attendance-etc1992/python"
REPO_URL="https://github.com/your-repo/attendance-laravel.git"

# 1. Update system
echo "[1/10] Updating system..."
sudo apt update && sudo apt upgrade -y

# 2. Install PHP 8.4 + extensions
echo "[2/10] Installing PHP 8.4..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-gd php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-redis

# 3. Install Composer
echo "[3/10] Installing Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 4. Install Python 3.10 + dependencies
echo "[4/10] Installing Python dependencies..."
sudo apt install -y python3-pip python3-venv
cd $PYTHON_DIR
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python setup_models.py

# 5. Clone repository (if not exists)
echo "[5/10] Setting up application..."
if [ ! -d "$APP_DIR" ]; then
    sudo git clone $REPO_URL $APP_DIR
fi
cd $APP_DIR

# 6. Install Laravel dependencies
echo "[6/10] Installing Laravel dependencies..."
composer install --optimize-autoloader --no-dev

# 7. Configure Laravel
echo "[7/10] Configuring Laravel..."
cp .env.example .env
php artisan key:generate

# Update .env with production settings
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sed -i 's/APP_URL=http:\/\/localhost/APP_URL=https:\/\/attendance.etc1992.com/' .env

# Database settings
read -p "Enter DB name: " DB_NAME
read -p "Enter DB user: " DB_USER
read -sp "Enter DB password: " DB_PASS
echo ""

sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -p "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env

# 8. Run migrations + seed
echo "[8/10] Running migrations..."
php artisan migrate --force
php artisan db:seed --force

# 9. Build frontend
echo "[9/10] Building frontend..."
npm install
npm run build

# 10. Configure permissions
echo "[10/10] Setting permissions..."
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR/storage
sudo chmod -R 755 $APP_DIR/bootstrap/cache

# Setup systemd service for Python Face API
echo "Setting up Python Face API service..."
sudo tee /etc/systemd/system/face-api.service > /dev/null <<EOF
[Unit]
Description=Face Recognition API
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=$PYTHON_DIR
ExecStart=$PYTHON_DIR/venv/bin/python face_api.py
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable face-api
sudo systemctl start face-api

# Setup cron for queue worker
echo "Setting up queue worker..."
sudo crontab -l -u www-data | { cat; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"; } | sudo crontab -u www-data -

echo ""
echo "========================================="
echo "  Deployment Complete!"
echo "  URL: https://attendance.etc1992.com"
echo "========================================="
