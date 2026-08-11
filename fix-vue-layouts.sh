#!/bin/bash
# ============================================
# Fix Vue pages - wrap with AppLayout
# Run: sudo bash fix-vue-layouts.sh
# ============================================

set -e

echo "========================================="
echo "  Fixing Vue pages layout"
echo "========================================="
echo ""

# Fix Permission.vue
echo "[1/3] Fixing Permission.vue..."
FILE1="resources/js/pages/Permission.vue"
cp "$FILE1" "${FILE1}.bak"

# Add AppLayout import and wrap template
sed -i 's|<template>|<template>\n  <AppLayout>|' "$FILE1"
sed -i 's|</template>|  </AppLayout>\n</template>|' "$FILE1"

# Add AppLayout import in script
sed -i "s|import api from '@/services/api'|import api from '@/services/api'\nimport AppLayout from '@/layouts/AppLayout.vue'|" "$FILE1"

chown -R www:www "$FILE1"
echo "  ✓ Done"

# Fix ChangePassword.vue
echo "[2/3] Fixing ChangePassword.vue..."
FILE2="resources/js/pages/ChangePassword.vue"
cp "$FILE2" "${FILE2}.bak"

sed -i 's|<template>|<template>\n  <AppLayout>|' "$FILE2"
sed -i 's|</template>|  </AppLayout>\n</template>|' "$FILE2"
sed -i "s|import api from '@/services/api'|import api from '@/services/api'\nimport AppLayout from '@/layouts/AppLayout.vue'|" "$FILE2"

chown -R www:www "$FILE2"
echo "  ✓ Done"

# Fix OtSummary.vue
echo "[3/3] Fixing OtSummary.vue..."
FILE3="resources/js/pages/OtSummary.vue"
cp "$FILE3" "${FILE3}.bak"

sed -i 's|<template>|<template>\n  <AppLayout>|' "$FILE3"
sed -i 's|</template>|  </AppLayout>\n</template>|' "$FILE3"
sed -i "s|import api from '@/services/api'|import api from '@/services/api'\nimport AppLayout from '@/layouts/AppLayout.vue'|" "$FILE3"

chown -R www:www "$FILE3"
echo "  ✓ Done"

# Build
echo ""
echo "Building frontend..."
sudo -u www npm run build
echo "✓ Build complete"

echo ""
echo "========================================="
echo "  ✅ Done! All 3 pages wrapped with AppLayout"
echo "========================================="
