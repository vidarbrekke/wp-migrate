#!/bin/bash

# WP-Migrate - Fix Staging Server MySQL Extension
# This script installs the missing PHP MySQL extension on the staging server
#
# Server: 45.33.31.79 (staging)
# Issue: PHP mysqli extension missing, preventing plugin activation
# Solution: Install php8.2-mysql and restart services

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

# Check if SSH key exists
check_prerequisites() {
    print_step "Checking prerequisites..."

    if [ ! -f "$STAGING_SSH_KEY" ]; then
        print_error "SSH key not found: $STAGING_SSH_KEY"
        exit 1
    fi

    print_success "Prerequisites check passed"
}

# Test SSH connection
test_ssh_connection() {
    print_step "Testing SSH connection to staging server..."

    if ssh -i "$STAGING_SSH_KEY" -o ConnectTimeout=10 -o BatchMode=yes "$STAGING_USER@$STAGING_SERVER" "echo 'SSH connection successful'" 2>/dev/null; then
        print_success "SSH connection established"
    else
        print_error "Failed to connect to staging server via SSH"
        echo "Please check:"
        echo "  1. SSH key is correct: $STAGING_SSH_KEY"
        echo "  2. Server is accessible: $STAGING_SERVER"
        echo "  3. User has access: $STAGING_USER"
        exit 1
    fi
}

# Check current PHP MySQL extension status
check_mysql_extension() {
    print_step "Checking current PHP MySQL extension status..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        echo "🔍 Checking PHP MySQL extension status..."
        echo ""

        echo "📋 PHP version and modules:"
        php --version | head -1
        echo ""

        echo "🔧 Loaded PHP modules (checking for mysqli/mysql):"
        php -m | grep -E "(mysql|mysqli|pdo_mysql)" || echo "❌ No MySQL extensions found"

        echo ""
        echo "📦 Checking if php8.2-mysql package is installed:"
        dpkg -l | grep php8.2-mysql || echo "❌ php8.2-mysql package not installed"

        echo ""
        echo "🌐 Testing WordPress plugin activation (will likely fail without MySQL):"
        if [ -d "/home/staging/public_html/wp-content/plugins/wp-migrate" ]; then
            echo "✅ WP-Migrate plugin directory exists"
            cd /home/staging/public_html
            wp plugin list | grep wp-migrate || echo "ℹ️  Plugin not in plugin list (may be due to MySQL extension)"
        else
            echo "⚠️  WP-Migrate plugin directory not found"
        fi
EOF
}

# Install PHP MySQL extension
install_mysql_extension() {
    print_step "Installing PHP MySQL extension..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "📦 Installing php8.2-mysql extension..."
        echo "🔑 Using sudo password for installation"

        # Update package list
        echo "Updating package list..."
        echo '$SUDO_PASSWORD' | sudo -S apt-get update --allow-releaseinfo-change

        # Install PHP MySQL extension
        echo "Installing php8.2-mysql..."
        echo '$SUDO_PASSWORD' | sudo -S apt-get install -y php8.2-mysql

        # Verify installation
        echo "Verifying installation..."
        if dpkg -l | grep -q php8.2-mysql; then
            echo "✅ php8.2-mysql package installed successfully"
        else
            echo "❌ Failed to install php8.2-mysql package"
            exit 1
        fi

        # Check loaded modules
        echo "Checking loaded PHP modules after installation..."
        php -m | grep -E "(mysql|mysqli|pdo_mysql)" || echo "⚠️  MySQL extensions not loaded yet (may need service restart)"
EOF

    print_success "PHP MySQL extension installation completed"
}

# Restart PHP and Apache services
restart_services() {
    print_step "Restarting PHP and Apache services..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << EOF
        echo "🔄 Restarting PHP-FPM service..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl restart php8.2-fpm

        echo "🔄 Restarting Apache service..."
        echo '$SUDO_PASSWORD' | sudo -S systemctl restart apache2

        echo "⏳ Waiting for services to fully restart..."
        sleep 3

        # Verify services are running
        echo "Checking service status..."
        if echo '$SUDO_PASSWORD' | sudo -S systemctl is-active --quiet php8.2-fpm; then
            echo "✅ PHP-FPM service is running"
        else
            echo "❌ PHP-FPM service failed to start"
        fi

        if echo '$SUDO_PASSWORD' | sudo -S systemctl is-active --quiet apache2; then
            echo "✅ Apache service is running"
        else
            echo "❌ Apache service failed to start"
        fi
EOF

    print_success "Services restarted successfully"
}

# Verify the fix
verify_fix() {
    print_step "Verifying MySQL extension fix..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        echo "🔍 Verifying PHP MySQL extension is now available..."
        echo ""

        echo "📋 Loaded PHP modules (should now include mysqli/mysql):"
        php -m | grep -E "(mysql|mysqli|pdo_mysql)" || echo "❌ MySQL extensions still not loaded"

        echo ""
        echo "🧪 Testing MySQL connection capability:"
        php -r "
            if (function_exists('mysqli_connect')) {
                echo \"✅ mysqli_connect function available\n\";
            } else {
                echo \"❌ mysqli_connect function not available\n\";
            }

            if (class_exists('PDO')) {
                echo \"✅ PDO class available\n\";
                try {
                    \$pdo = new PDO('mysql:host=localhost', 'test', 'test');
                } catch (Exception \$e) {
                    echo \"ℹ️  PDO available but connection test shows: \" . \$e->getMessage() . \"\n\";
                }
            } else {
                echo \"❌ PDO class not available\n\";
            }
        "

        echo ""
        echo "🔌 Testing WordPress plugin activation:"
        if [ -d "/home/staging/public_html/wp-content/plugins/wp-migrate" ]; then
            cd /home/staging/public_html
            echo "Attempting to activate wp-migrate plugin..."
            if wp plugin activate wp-migrate 2>/dev/null; then
                echo "✅ Plugin activated successfully!"
                wp plugin list | grep wp-migrate
            else
                echo "⚠️  Plugin activation failed - check WordPress error logs"
                wp plugin list | grep wp-migrate || echo "Plugin not found in plugin list"
            fi
        else
            echo "⚠️  WP-Migrate plugin directory not found - deploy plugin first"
        fi
EOF
}

# Main execution
main() {
    echo "🔧 WP-Migrate - Fix Staging Server MySQL Extension"
    echo "=================================================="
    echo ""
    echo "🎯 Target Server: $STAGING_SERVER (Staging)"
    echo "👤 User: $STAGING_USER"
    echo "🔑 SSH Key: $STAGING_SSH_KEY"
    echo "📦 Package: php8.2-mysql"
    echo ""

    # Execute steps
    check_prerequisites
    test_ssh_connection
    check_mysql_extension
    install_mysql_extension
    restart_services
    verify_fix

    echo ""
    print_success "🎉 MySQL extension fix process completed!"
    echo ""
    echo "📋 Next steps:"
    echo "  1. ✅ MySQL extension should now be available"
    echo "  2. 🔌 Try activating the wp-migrate plugin in WordPress admin"
    echo "  3. 🧪 Test plugin functionality"
    echo "  4. 📊 Run the dry-run migration test to verify everything works"
    echo ""
    echo "🔍 If issues persist, check:"
    echo "  • WordPress error logs: /home/staging/public_html/wp-content/debug.log"
    echo "  • Apache error logs: /var/log/apache2/error.log"
    echo "  • PHP error logs for any extension loading issues"
}

# Run main function
main "$@"
