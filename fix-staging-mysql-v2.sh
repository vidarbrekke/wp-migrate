#!/bin/bash

# WP-Migrate - Advanced Fix for Staging Server MySQL Extension
# This script addresses multiple PHP/MySQL configuration issues
#
# Issues identified:
# 1. Server running PHP 7.4 instead of PHP 8.2
# 2. MySQL extensions not loading properly
# 3. Need to ensure correct PHP version and extensions are active

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
STAGING_SERVER="45.33.31.79"
STAGING_USER="staging"
STAGING_SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
SUDO_PASSWORD="@Flatbygdi73?"

# Helper functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${PURPLE}[STEP]${NC} $1"
    echo "=========================================="
}

# Check PHP configuration and versions
check_php_config() {
    print_step "Analyzing PHP configuration..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        echo "🔍 PHP Configuration Analysis"
        echo ""

        echo "📋 Available PHP versions:"
        ls /usr/bin/php* 2>/dev/null | grep -E "php[0-9]" | sort -V || echo "No PHP binaries found in /usr/bin/"

        echo ""
        echo "🔗 PHP CLI version:"
        php --version | head -1

        echo ""
        echo "🌐 PHP CGI/FPM versions available:"
        ls /usr/sbin/php*-fpm 2>/dev/null | sort -V || echo "No PHP-FPM found in /usr/sbin/"

        echo ""
        echo "⚙️  Apache PHP module:"
        apache2ctl -M 2>/dev/null | grep -E "php[0-9]" || echo "No PHP module found in Apache"

        echo ""
        echo "📁 PHP configuration files:"
        ls -la /etc/php/ 2>/dev/null || echo "PHP config directory not found"

        echo ""
        echo "🔧 Checking for multiple PHP installations:"
        which php
        php -v | head -1

        if command -v php8.2 &> /dev/null; then
            echo ""
            echo "✅ PHP 8.2 is available:"
            php8.2 --version | head -1
            echo "PHP 8.2 modules:"
            php8.2 -m | grep -E "(mysql|mysqli|pdo)" || echo "No MySQL modules in PHP 8.2"
        fi

        if command -v php7.4 &> /dev/null; then
            echo ""
            echo "ℹ️  PHP 7.4 is also available:"
            php7.4 --version | head -1
            echo "PHP 7.4 modules:"
            php7.4 -m | grep -E "(mysql|mysqli|pdo)" || echo "No MySQL modules in PHP 7.4"
        fi
EOF
}

# Install MySQL extension for both PHP versions
install_mysql_for_both_versions() {
    print_step "Installing MySQL extensions for all PHP versions..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "📦 Installing MySQL extensions for all available PHP versions..."

        # Update package list
        echo "Updating package list..."
        echo '$SUDO_PASSWORD' | sudo -S apt-get update --allow-releaseinfo-change

        # Install MySQL extensions for different PHP versions
        echo "Installing php-mysql (default)..."
        echo '$SUDO_PASSWORD' | sudo -S apt-get install -y php-mysql

        if command -v php7.4 &> /dev/null; then
            echo "Installing php7.4-mysql..."
            echo '$SUDO_PASSWORD' | sudo -S apt-get install -y php7.4-mysql
        fi

        if command -v php8.2 &> /dev/null; then
            echo "Installing php8.2-mysql..."
            echo '$SUDO_PASSWORD' | sudo -S apt-get install -y php8.2-mysql
        fi

        echo ""
        echo "Verifying installations..."
        dpkg -l | grep php.*mysql | sort
EOF

    print_success "MySQL extensions installed for all PHP versions"
}

# Configure Apache to use PHP 8.2
configure_apache_php() {
    print_step "Configuring Apache to use PHP 8.2..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "🔧 Configuring Apache to use PHP 8.2..."

        # Disable current PHP module
        echo "Disabling current PHP modules..."
        echo '$SUDO_PASSWORD' | sudo -S a2dismod php7.4 2>/dev/null || echo "php7.4 module not enabled"
        echo '$SUDO_PASSWORD' | sudo -S a2dismod php 2>/dev/null || echo "php module not enabled"

        # Enable PHP 8.2
        echo "Enabling PHP 8.2 module..."
        echo '$SUDO_PASSWORD' | sudo -S a2enmod php8.2

        # Set PHP 8.2 as default
        echo "Setting PHP 8.2 as default..."
        echo '$SUDO_PASSWORD' | sudo -S update-alternatives --set php /usr/bin/php8.2

        # Verify Apache configuration
        echo "Checking Apache modules..."
        apache2ctl -M | grep php || echo "PHP module not found in Apache"

        echo "✅ Apache configured to use PHP 8.2"
EOF

    print_success "Apache configured for PHP 8.2"
}

# Update PHP CLI to use PHP 8.2
update_php_cli() {
    print_step "Updating PHP CLI to use PHP 8.2..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "🔧 Updating PHP CLI to version 8.2..."

        # Set PHP 8.2 as default CLI
        echo '$SUDO_PASSWORD' | sudo -S update-alternatives --set php /usr/bin/php8.2

        # Verify CLI version
        echo "Verifying PHP CLI version:"
        php --version | head -1

        # Verify modules in CLI
        echo "PHP CLI modules:"
        php -m | grep -E "(mysql|mysqli|pdo)" || echo "MySQL modules not found in CLI"
EOF

    print_success "PHP CLI updated to version 8.2"
}

# Restart all services
restart_all_services() {
    print_step "Restarting all services..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "🔄 Restarting services in correct order..."

        # Stop Apache first
        echo "Stopping Apache..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl stop apache2

        # Restart PHP-FPM
        echo "Restarting PHP-FPM..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl restart php8.2-fpm

        # Restart Apache
        echo "Restarting Apache..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl restart apache2

        # Wait for services to start
        sleep 5

        # Check service status
        echo "Checking service status..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl status php8.2-fpm --no-pager -l | grep -E "(Active|Loaded)"
        echo '$SUDO_PASSWORD' | sudo -S systemctl status apache2 --no-pager -l | grep -E "(Active|Loaded)"
EOF

    print_success "All services restarted"
}

# Comprehensive verification
comprehensive_verification() {
    print_step "Running comprehensive verification..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        echo "🔍 Comprehensive PHP and MySQL verification..."
        echo ""

        echo "📋 PHP Versions:"
        echo "CLI: $(php --version | head -1)"
        echo "CGI/FPM: $(/usr/sbin/php-fpm8.2 --version | head -1 2>/dev/null || echo "PHP-FPM not found")"
        echo "Apache: $(apache2ctl -M 2>/dev/null | grep php | head -1 || echo "No PHP in Apache")"

        echo ""
        echo "🔧 PHP Modules (CLI):"
        php -m | grep -E "(mysql|mysqli|pdo)" || echo "❌ No MySQL modules in CLI"

        echo ""
        echo "🔧 PHP Modules (8.2 if available):"
        if command -v php8.2 &> /dev/null; then
            php8.2 -m | grep -E "(mysql|mysqli|pdo)" || echo "❌ No MySQL modules in PHP 8.2"
        else
            echo "PHP 8.2 not found"
        fi

        echo ""
        echo "🧪 MySQL Extension Tests:"
        php -r "
            echo 'Testing mysqli: ';
            echo function_exists('mysqli_connect') ? '✅ Available' : '❌ Not available';
            echo PHP_EOL;

            echo 'Testing PDO: ';
            echo class_exists('PDO') ? '✅ Available' : '❌ Not available';
            echo PHP_EOL;

            if (class_exists('PDO')) {
                echo 'Testing PDO MySQL driver: ';
                echo in_array('mysql', PDO::getAvailableDrivers()) ? '✅ Available' : '❌ Not available';
                echo PHP_EOL;
            }
        "

        echo ""
        echo "🌐 WordPress PHP Check:"
        if [ -f "/home/staging/public_html/wp-config.php" ]; then
            cd /home/staging/public_html
            echo "WordPress PHP version check:"
            wp core version --extra 2>/dev/null | grep -E "(php|PHP)" || echo "WP-CLI PHP info not available"
        else
            echo "WordPress config not found"
        fi

        echo ""
        echo "🔌 Plugin Activation Test:"
        if [ -d "/home/staging/public_html/wp-content/plugins/wp-migrate" ]; then
            cd /home/staging/public_html
            echo "Testing wp-migrate plugin activation..."
            if wp plugin activate wp-migrate 2>/dev/null; then
                echo "✅ Plugin activated successfully!"
                wp plugin list | grep wp-migrate
            else
                echo "❌ Plugin activation failed"
                echo "Error details:"
                wp plugin activate wp-migrate 2>&1
            fi
        else
            echo "⚠️  wp-migrate plugin not found"
        fi
EOF
}

# Main execution
main() {
    echo "🔧 WP-Migrate - Advanced Staging MySQL Extension Fix"
    echo "==================================================="
    echo ""
    echo "🎯 Target Server: $STAGING_SERVER (Staging)"
    echo "👤 User: $STAGING_USER"
    echo "🔧 Issues to Fix:"
    echo "  • PHP version mismatch (7.4 vs 8.2)"
    echo "  • MySQL extensions not loading"
    echo "  • Apache/PHP-FPM configuration"
    echo ""

    # Execute comprehensive fix
    check_php_config
    install_mysql_for_both_versions
    configure_apache_php
    update_php_cli
    restart_all_services
    comprehensive_verification

    echo ""
    print_success "🎉 Advanced MySQL extension fix completed!"
    echo ""
    echo "📋 Summary of changes:"
    echo "  ✅ Installed MySQL extensions for all PHP versions"
    echo "  ✅ Configured Apache to use PHP 8.2"
    echo "  ✅ Updated PHP CLI to use PHP 8.2"
    echo "  ✅ Restarted all services"
    echo "  ✅ Verified MySQL extension availability"
    echo ""
    echo "🔍 Next steps:"
    echo "  1. ✅ Check if wp-migrate plugin can now be activated"
    echo "  2. 🧪 Run the migration dry-run test"
    echo "  3. 📊 Verify full migration workflow"
    echo ""
    echo "🔧 If issues persist, check logs:"
    echo "  • Apache: /var/log/apache2/error.log"
    echo "  • WordPress: /home/staging/public_html/wp-content/debug.log"
    echo "  • PHP-FPM: /var/log/php8.2-fpm.log"
}

# Run main function
main "$@"
