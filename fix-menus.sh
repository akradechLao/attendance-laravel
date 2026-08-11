#!/bin/bash
# ============================================
# Fix file ownership + Add sidebar menu items
# Run: sudo bash fix-menus.sh
# ============================================

set -e

echo "========================================="
echo "  Fixing file ownership & sidebar menus"
echo "========================================="
echo ""

# Fix ownership
echo "[1/2] Fixing file ownership..."
chown -R www:www app/Http/Controllers/Api/PermissionController.php
chown -R www:www resources/js/pages/Permission.vue
chown -R www:www resources/js/pages/ChangePassword.vue
chown -R www:www routes/api.php
echo "  ✓ Done"
echo ""

# Fix storage permissions
echo "[2/2] Fixing storage permissions..."
chown -R www:www storage/
chown -R www:www bootstrap/cache/
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
echo "  ✓ Done"
echo ""

echo "========================================="
echo "  ✅ Done! Now add menu items to sidebar"
echo "========================================="
echo ""
echo "Add these to your AppLayout.vue sidebar:"
echo ""
echo "For HR/Admin menu:"
echo '  <router-link to="/permission" class="...">'
echo '    จัดการสิทธิ์'
echo '  </router-link>'
echo ""
echo "For all users menu:"
echo '  <router-link to="/change-password" class="...">'
echo '    เปลี่ยนรหัสผ่าน'
echo '  </router-link>'
echo ""
echo "And add routes to router:"
echo '  { path: "/permission", component: Permission.vue }'
echo '  { path: "/change-password", component: ChangePassword.vue }'
echo "========================================="
