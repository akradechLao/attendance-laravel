#!/bin/bash

BACKUP_DIR="/var/backups/attendance"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="attendance_etc1992"

mkdir -p $BACKUP_DIR

echo "Backing up database..."
mysqldump -u root -p $DB_NAME | gzip > "$BACKUP_DIR/attendance_$DATE.sql.gz"

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup complete: $BACKUP_DIR/attendance_$DATE.sql.gz"
