#!/bin/bash
set -e

PYTHON_DIR="/www/wwwroot/attendance.northernthai.co.th/python"
VENV_DIR="$PYTHON_DIR/venv"
MODELS_DIR="$PYTHON_DIR/models"

echo "=== Face API Deployment ==="

# Check Python3
if ! command -v python3 &> /dev/null; then
    echo "Python3 not found. Installing..."
    sudo apt-get update
    sudo apt-get install -y python3 python3-pip python3-venv
fi

echo "Python3 version: $(python3 --version)"

# Install system dependencies for dlib
echo "Installing system dependencies..."
sudo apt-get install -y cmake build-essential libopenblas-dev liblapack-dev

# Create virtual environment
echo "Creating virtual environment..."
cd "$PYTHON_DIR"
python3 -m venv venv
source venv/bin/activate

# Install pip packages
echo "Installing pip packages..."
pip install --upgrade pip
pip install -r requirements.txt

# Download models
echo "Downloading dlib models..."
python3 setup_models.py

# Create systemd service
echo "Creating systemd service..."
sudo tee /etc/systemd/system/face-api.service > /dev/null <<EOF
[Unit]
Description=Face Recognition API
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$PYTHON_DIR
Environment=PATH=$VENV_DIR/bin:/usr/local/bin:/usr/bin:/bin
ExecStart=$VENV_DIR/bin/python3 face_api.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable face-api
sudo systemctl restart face-api

echo "=== Deployment Complete ==="
echo "Service status:"
sudo systemctl status face-api --no-pager
echo ""
echo "Test health: curl http://127.0.0.1:8000/api/face/health"
