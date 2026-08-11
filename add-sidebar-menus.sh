#!/bin/bash
# ============================================
# Add menu items to AppLayout.vue sidebar
# Run: sudo bash add-sidebar-menus.sh
# ============================================

set -e

FILE="resources/js/layouts/AppLayout.vue"

echo "========================================="
echo "  Adding sidebar menu items"
echo "========================================="
echo ""

# Backup original
cp "$FILE" "${FILE}.bak"
echo "✓ Backup created: ${FILE}.bak"

# Add "จัดการสิทธิ์" before Admin section
sed -i "/{ section: 'Admin' }/i\\
  { section: 'Settings' },\\
  { path: '/permission', label: 'จัดการสิทธิ์', icon: '🔑' },\\
  { path: '/change-password', label: 'เปลี่ยนรหัสผ่าน', icon: '🔐' }," "$FILE"

echo "✓ Menu items added"
echo ""

# Fix ownership
chown -R www:www "$FILE"
echo "✓ File ownership fixed"
echo ""

echo "========================================="
echo "  ✅ Done! Rebuild frontend"
echo "========================================="
echo ""
echo "Run: npm run build"
echo "========================================="
