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

# Configuration - UPDATE THESE VALUES
STAGING_SERVER="your-staging-server.com"
STAGING_USER="your-username"
STAGING_PATH="/path/to/wordpress/wp-content/plugins"
PLUGIN_NAME="mk-wc-plugin-starter"

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
    if [ ! -f "wp-migrate-plugin-staging.tar.gz" ]; then
        print_error "Deployment package not found: wp-migrate-plugin-staging.tar.gz"
        print_error "Run this script from the project root directory"
        exit 1
    fi
    print_success "Deployment package found: wp-migrate-plugin-staging.tar.gz"
}

# Upload package to staging server
upload_package() {
    print_status "Uploading deployment package to staging server..."
    
    if scp wp-migrate-plugin-staging.tar.gz "$STAGING_USER@$STAGING_SERVER:$STAGING_PATH/"; then
        print_success "Package uploaded successfully"
    else
        print_error "Failed to upload package"
        exit 1
    fi
}

# Deploy on staging server
deploy_on_staging() {
    print_status "Deploying plugin on staging server..."
    
    ssh "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        set -e
        
        echo "🚀 Starting deployment on staging server..."
        
        # Navigate to plugins directory
        cd /path/to/wordpress/wp-content/plugins/
        
        # Backup existing plugin if it exists
        if [ -d "mk-wc-plugin-starter" ]; then
            echo "📦 Backing up existing plugin..."
            mv mk-wc-plugin-starter mk-wc-plugin-starter.backup.$(date +%Y%m%d_%H%M%S)
        fi
        
        # Extract new plugin
        echo "📂 Extracting new plugin..."
        tar -xzf wp-migrate-plugin-staging.tar.gz
        
        # Set proper permissions
        echo "🔐 Setting permissions..."
        find mk-wc-plugin-starter/ -type f -exec chmod 644 {} \;
        find mk-wc-plugin-starter/ -type d -exec chmod 755 {} \;
        chmod +x mk-wc-plugin-starter/run-tests.sh
        
        # Clean up package
        rm wp-migrate-plugin-staging.tar.gz
        
        echo "✅ Plugin deployed successfully!"
        
        # Navigate to plugin directory
        cd mk-wc-plugin-starter/
        
        # Install dependencies
        echo "📦 Installing dependencies..."
        if command -v composer &> /dev/null; then
            composer install --no-dev --optimize-autoloader
        else
            echo "⚠️  Composer not found. Please install dependencies manually."
        fi
        
        echo "🎯 Plugin ready for testing!"
EOF
}

# Run tests on staging server
run_staging_tests() {
    print_status "Running tests on staging server..."
    
    ssh "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        set -e
        
        echo "🧪 Running tests on staging server..."
        
        cd /path/to/wordpress/wp-content/plugins/mk-wc-plugin-starter/
        
        # Check if tests directory exists
        if [ ! -d "tests" ]; then
            echo "❌ Tests directory not found"
            exit 1
        fi
        
        # Run critical tests first
        echo "🔐 Running critical security tests..."
        if [ -f "run-tests.sh" ]; then
            ./run-tests.sh security
        else
            echo "⚠️  Test runner not found. Please run tests manually."
        fi
        
        echo "✅ Tests completed!"
EOF
}

# Main deployment process
main() {
    echo "🚀 WP-Migrate Plugin - Staging Deployment"
    echo "=========================================="
    echo ""
    
    # Check configuration
    if [ "$STAGING_SERVER" = "your-staging-server.com" ]; then
        print_error "Please update the configuration variables in this script:"
        print_error "  - STAGING_SERVER"
        print_error "  - STAGING_USER"
        print_error "  - STAGING_PATH"
        exit 1
    fi
    
    # Check package
    check_package
    
    # Upload package
    upload_package
    
    # Deploy on staging
    deploy_on_staging
    
    # Run tests
    run_staging_tests
    
    print_success "🎉 Deployment completed successfully!"
    echo ""
    echo "📋 Next steps:"
    echo "  1. Activate the plugin in WordPress admin"
    echo "  2. Run full test suite: ./run-tests.sh all"
    echo "  3. Check coverage reports in tests/coverage/html/"
    echo "  4. Verify all 66 tests pass"
    echo ""
    echo "🔗 Test commands:"
    echo "  ./run-tests.sh all          # All tests with coverage"
    echo "  ./run-tests.sh critical     # Critical path only"
    echo "  ./run-tests.sh security     # Security tests only"
}

# Run main function
main "$@"
