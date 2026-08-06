#!/bin/bash

echo "Setting up Nginx..."

# Create Nginx config
sudo tee /etc/nginx/sites-available/attendance-etc1992 > /dev/null <<'EOF'
server {
    listen 80;
    server_name attendance.etc1992.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name attendance.etc1992.com;

    root /var/www/attendance-etc1992/public;
    index index.php index.html;

    # SSL (ServBot)
    ssl_certificate /etc/ssl/attendance.etc1992.com/fullchain.pem;
    ssl_certificate_key /etc/ssl/attendance.etc1992.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Python Face API (reverse proxy)
    location /face/ {
        proxy_pass http://127.0.0.1:8000/face/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 30s;
        client_max_body_size 10M;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Static files cache
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
}
EOF

# Enable site
sudo ln -sf /etc/nginx/sites-available/attendance-etc1992 /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

echo "Nginx configured successfully!"
