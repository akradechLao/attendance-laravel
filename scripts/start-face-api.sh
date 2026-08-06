#!/bin/bash

PYTHON_DIR="/var/www/attendance-etc1992/python"

echo "Starting Face Recognition API..."
cd $PYTHON_DIR
source venv/bin/activate
python face_api.py
