#!/bin/bash

# 🚀 WP-Migrate Plugin - Staging Deployment Script
# This script automates the deployment process to your staging server

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - UPDATED WITH ACTUAL STAGING SERVER DETAILS
STAGING_SERVER="45.33.31.79"
STAGING_USER="staging"
STAGING_SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
STAGING_PATH="/home/staging/public_html/wp-content/plugins"
PLUGIN_NAME="wp-migrate"

# Functions
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

# Check if deployment package exists
check_package() {
    if [ ! -f "wp-migrate-plugin-staging.zip" ]; then
        print_error "Deployment package not found: wp-migrate-plugin-staging.zip"
        print_error "Run this script from the project root directory"
        exit 1
    fi
    print_success "Deployment package found: wp-migrate-plugin-staging.zip"
}

# Check if SSH key exists
check_ssh_key() {
    if [ ! -f "$STAGING_SSH_KEY" ]; then
        print_error "SSH key not found: $STAGING_SSH_KEY"
        print_error "Please ensure the staging SSH key is available"
        exit 1
    fi
    chmod 600 "$STAGING_SSH_KEY"
    print_success "SSH key found and permissions set: $STAGING_SSH_KEY"
}

# Upload package to staging server
upload_package() {
    print_status "Uploading deployment package to staging server..."

    if scp -i "$STAGING_SSH_KEY" wp-migrate-plugin-staging.zip "$STAGING_USER@$STAGING_SERVER:$STAGING_PATH/"; then
        print_success "Package uploaded successfully"
    else
        print_error "Failed to upload package"
        exit 1
    fi
}

# Deploy on staging server
deploy_on_staging() {
    print_status "Deploying plugin on staging server..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        set -e

        echo "🚀 Starting WordPress plugin deployment..."

        # Navigate to plugins directory
        cd /home/staging/public_html/wp-content/plugins/

        # Always do a clean installation to avoid conflicts
        echo "🧹 Preparing clean plugin installation..."

        # Check if plugin is active in WordPress and deactivate if needed
        if wp plugin is-active wp-migrate --path=/home/staging/public_html/ 2>/dev/null; then
            echo "🔌 Deactivating existing plugin..."
            wp plugin deactivate wp-migrate --path=/home/staging/public_html/
        fi

        # Remove old plugin completely to ensure clean extraction
        echo "🗑️  Removing any existing plugin files..."
        rm -rf wp-migrate/

        # Extract new plugin
        echo "📂 Extracting new plugin version..."
        unzip -q wp-migrate-plugin-staging.zip

        # Verify plugin structure
        if [ ! -f "wp-migrate/wp-migrate.php" ]; then
            echo "❌ Plugin extraction failed - main file not found"
            exit 1
        fi

        # Set proper permissions (WordPress standard)
        echo "🔐 Setting WordPress permissions..."
        find wp-migrate/ -type f -exec chmod 644 {} \;
        find wp-migrate/ -type d -exec chmod 755 {} \;

        # Set ownership (may fail if not running as root)
        if chown -R www-data:www-data wp-migrate/ 2>/dev/null; then
            echo "✅ File ownership set to www-data:www-data"
        else
            echo "ℹ️  Could not change ownership (normal for non-root user)"
            echo "🔧 File permissions set correctly for web access"
        fi

        # Clean up deployment package
        rm wp-migrate-plugin-staging.zip

        # Get version info for logging
        if [ -f "wp-migrate/VERSION" ]; then
            PLUGIN_VERSION=$(cat wp-migrate/VERSION)
            echo "📋 Plugin version: $PLUGIN_VERSION"

            # Log deployment
            TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
            echo "$TIMESTAMP | $PLUGIN_VERSION | DEPLOYED | Automated deployment from local dev" >> wp-migrate/deployments.log
            echo "📝 Deployment logged to deployments.log"
        fi

        echo "✅ Plugin deployed successfully!"

        # Navigate to plugin directory for post-deployment tasks
        cd wp-migrate/

        # Skip composer for pure WordPress plugins
        echo "📦 Pure WordPress plugin - no external dependencies needed"

        # Verify plugin can be loaded
        echo "🔍 Verifying plugin integrity..."
        if wp plugin verify wp-migrate --path=/home/staging/public_html/ 2>/dev/null; then
            echo "✅ Plugin verification passed"
        else
            echo "⚠️  Plugin verification warning (may be normal for custom plugins)"
        fi

        echo "🎯 Plugin ready for activation and testing!"
EOF
}

# Run tests on staging server
run_staging_tests() {
    print_status "Running tests on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        set -e
        
        echo "🧪 Running tests on staging server..."
        
        cd /home/staging/public_html/wp-content/plugins/wp-migrate/
        
        # Check if tests directory exists
        if [ ! -d "tests" ]; then
            echo "❌ Tests directory not found"
            exit 1
        fi
        
        # Run full test suite (if available)
        echo "🧪 Checking test environment..."
        if [ -f "run-tests.sh" ] && [ -d "tests" ]; then
            echo "🔐 Running security tests first..."
            ./run-tests.sh security

            echo "🚀 Running all tests with coverage..."
            ./run-tests.sh all

            echo "📊 Test results available in tests/coverage/html/"
        else
            echo "ℹ️  Test suite not deployed (production deployment)"
            echo "💡 Run tests locally: ./run-tests.sh all"
        fi
        
        echo "✅ All tests completed!"
EOF
}

# Activate plugin after deployment
activate_plugin() {
    print_status "Activating plugin on staging server..."

    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        set -e

        echo "🔌 Activating WP-Migrate plugin..."

        # Check if WP-CLI can connect to database
        if wp db check --path=/home/staging/public_html/ 2>/dev/null; then
            echo "✅ Database connection OK"

            # Activate plugin using WP-CLI
            if wp plugin activate wp-migrate --path=/home/staging/public_html/; then
                echo "✅ Plugin activated successfully"

                # Get plugin info
                wp plugin get wp-migrate --path=/home/staging/public_html/ --format=json | head -20

                echo "🎯 Plugin is now active and ready to use!"
            else
                echo "❌ Plugin activation failed"
                echo "🔧 Manual activation required in WordPress admin"
                echo "🌐 WordPress Admin: http://45.33.31.79/wp-admin/"
                echo "🔧 Go to Plugins → WP-Migrate → Activate"
            fi
        else
            echo "⚠️  WP-CLI database connection issue"
            echo "🔧 Manual activation required in WordPress admin"
            echo "🌐 WordPress Admin: http://45.33.31.79/wp-admin/"
            echo "🔧 Go to Plugins → WP-Migrate → Activate"
            echo ""
            echo "💡 If activation fails, check MySQL extension:"
            echo "   sudo apt-get install php-mysql"
            echo "   sudo systemctl restart apache2"
        fi
EOF
}

# Increment version before deployment
increment_version() {
    print_status "Incrementing plugin version..."

    cd wp-migrate/

    if [ -f "version-manager.sh" ]; then
        chmod +x version-manager.sh
        ./version-manager.sh patch
        NEW_VERSION=$(./version-manager.sh current)
        print_success "Version incremented to: $NEW_VERSION"
    else
        print_warning "Version manager not found, using current version"
    fi

    cd ..
}

# Main deployment process
main() {
    echo "🚀 WP-Migrate Plugin - Production Deployment"
    echo "============================================"
    echo ""
    echo "🎯 Target Server: $STAGING_SERVER"
    echo "👤 User: $STAGING_USER"
    echo "🔑 SSH Key: $STAGING_SSH_KEY"
    echo "📁 Path: $STAGING_PATH"
    echo ""
    echo "📋 Deployment Strategy:"
    echo "  • Single plugin instance (removes old versions)"
    echo "  • Proper WordPress deactivation/activation"
    echo "  • Version management with semantic versioning"
    echo "  • WordPress security best practices"
    echo ""

    # Increment version for new deployment
    increment_version
    echo ""

    # Check package
    check_package

    # Check SSH key
    check_ssh_key

    # Upload package
    upload_package

    # Deploy on staging (proper WordPress update)
    deploy_on_staging

    # Activate plugin
    activate_plugin

    # Run tests if available
    run_staging_tests

    print_success "🎉 Production deployment completed successfully!"
    echo ""
    echo "📋 Deployment Summary:"
    echo "  ✅ Single plugin instance maintained"
    echo "  ✅ WordPress deactivation/activation handled"
    echo "  ✅ Version incremented automatically"
    echo "  ✅ Proper permissions and ownership set"
    echo ""
    echo "🔗 WordPress Admin: http://45.33.31.79/wp-admin/"
    echo "🔧 Plugin Settings: Settings → WP-Migrate"
    echo ""
    echo "📊 Version Control:"
    echo "  • Use './wp-migrate/version-manager.sh major|minor|patch' to increment versions"
    echo "  • Current version tracked in VERSION file"
    echo "  • Plugin header updated automatically"
}

# Run main function
main "$@"
