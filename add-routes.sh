#!/bin/bash
# ============================================
# Add missing routes to router.js
# Run: sudo bash add-routes.sh
# ============================================

set -e

FILE="resources/js/router.js"

echo "========================================="
echo "  Adding missing routes"
echo "========================================="
echo ""

# Backup
cp "$FILE" "${FILE}.bak"
echo "✓ Backup created"

# Add routes before the last closing bracket of routes array
# Find the line with admin/location-settings and add after it
sed -i "/path: '\/admin\/location-settings'/,/\]/{
/\]/a\\
  },\\
  {\\
    path: '/permission',\\
    name: 'Permission',\\
    component: () => import('./pages/Permission.vue'),\\
    meta: { requiresAuth: true, layout: 'app' }\\
  },\\
  {\\
    path: '/change-password',\\
    name: 'ChangePassword',\\
    component: () => import('./pages/ChangePassword.vue'),\\
    meta: { requiresAuth: true, layout: 'app' }\\
  },\\
  {\\
    path: '/ot-summary',\\
    name: 'OtSummary',\\
    component: () => import('./pages/OtSummary.vue'),\\
    meta: { requiresAuth: true, layout: 'app' }
}" "$FILE"

echo "✓ Routes added"

# Fix ownership
chown -R www:www "$FILE"
echo "✓ File ownership fixed"

# Build
echo "Building frontend..."
sudo -u www npm run build
echo "✓ Build complete"

echo ""
echo "========================================="
echo "  ✅ Done! Routes added:"
echo "  - /permission"
echo "  - /change-password"
echo "  - /ot-summary"
echo "========================================="
