#!/bin/bash

# WP-Migrate - Fix PHP CLI MySQL Extension
# The issue is that WP-CLI uses PHP CLI, not PHP-FPM
# We need to ensure PHP CLI has MySQL extensions loaded

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

STAGING_SERVER="45.33.31.79"
STAGING_USER="staging"
STAGING_SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
SUDO_PASSWORD="@Flatbygdi73?"

echo -e "${BLUE}🔧 Fixing PHP CLI MySQL Extension${NC}"
echo "=================================="

# Force PHP CLI to use PHP 8.2 and ensure MySQL extensions are loaded
ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
    echo "🔧 Fixing PHP CLI configuration..."

    # First, let's check what's happening with PHP CLI
    echo "Current PHP CLI version:"
    which php
    php --version | head -1
    echo ""

    # Check if php8.2 exists and has MySQL modules
    if command -v php8.2 &> /dev/null; then
        echo "PHP 8.2 exists and has modules:"
        php8.2 -m | grep -E "(mysql|mysqli|pdo)" || echo "No MySQL modules found"
        echo ""

        # Try to update alternatives more forcefully
        echo "Forcing PHP 8.2 as default CLI..."
        echo '$SUDO_PASSWORD' | sudo -S update-alternatives --install /usr/bin/php php /usr/bin/php8.2 100
        echo '$SUDO_PASSWORD' | sudo -S update-alternatives --set php /usr/bin/php8.2

        # Check if there's a local php binary that's overriding
        if [ -f "/home/staging/bin/php" ]; then
            echo "Found local PHP binary, removing it..."
            rm -f /home/staging/bin/php
        fi
    fi

    echo ""
    echo "After changes - PHP CLI version:"
    which php
    php --version | head -1

    echo ""
    echo "PHP CLI modules:"
    php -m | grep -E "(mysql|mysqli|pdo)" || echo "❌ No MySQL modules in CLI"

    echo ""
    echo "Testing MySQL extension in CLI:"
    php -r "
        echo 'mysqli: ' . (function_exists('mysqli_connect') ? '✅' : '❌') . PHP_EOL;
        echo 'PDO: ' . (class_exists('PDO') ? '✅' : '❌') . PHP_EOL;
        if (class_exists('PDO')) {
            echo 'PDO MySQL: ' . (in_array('mysql', PDO::getAvailableDrivers()) ? '✅' : '❌') . PHP_EOL;
        }
    "
EOF

echo ""
echo -e "${BLUE}🔄 Testing WordPress plugin activation...${NC}"

# Test if the fix worked
ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
    if [ -d "/home/staging/public_html/wp-content/plugins/wp-migrate" ]; then
        cd /home/staging/public_html
        echo "Testing wp-migrate plugin activation..."
        if wp plugin activate wp-migrate 2>/dev/null; then
            echo "✅ SUCCESS: Plugin activated!"
            wp plugin list | grep wp-migrate
        else
            echo "❌ FAILED: Plugin activation failed"
            echo "WP-CLI is still using wrong PHP version"
            wp --info | grep -E "(php|PHP)" || echo "Cannot get WP-CLI PHP info"
        fi
    else
        echo "⚠️  wp-migrate plugin not found"
    fi
EOF

echo ""
echo -e "${GREEN}✅ PHP CLI MySQL extension fix completed${NC}"
echo ""
echo "📋 If the plugin still won't activate, the issue might be:"
echo "  • WP-CLI has its own PHP path configured"
echo "  • WordPress is configured to use a specific PHP version"
echo "  • Check WordPress error logs for more details"
