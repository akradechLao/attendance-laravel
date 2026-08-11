#!/bin/bash
# ============================================
# Add OT Summary menu to sidebar
# Run: sudo bash add-ot-summary-menu.sh
# ============================================

set -e

FILE="resources/js/layouts/AppLayout.vue"

echo "========================================="
echo "  Adding OT Summary menu"
echo "========================================="
echo ""

# Backup
cp "$FILE" "${FILE}.bak"

# Add menu item before Settings section
sed -i "/{ section: 'Settings' }/i\\
  { path: '/ot-summary', label: 'สรุปโอที', icon: '📊' }," "$FILE"

echo "✓ Menu item added"

# Fix ownership
chown -R www:www "$FILE"
echo "✓ File ownership fixed"

# Build
echo "Building frontend..."
sudo -u www npm run build
echo "✓ Build complete"

echo ""
echo "========================================="
echo "  ✅ Done!"
echo "========================================="
