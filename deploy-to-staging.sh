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
    if [ ! -f "wp-migrate-plugin-staging.tar.gz" ]; then
        print_error "Deployment package not found: wp-migrate-plugin-staging.tar.gz"
        print_error "Run this script from the project root directory"
        exit 1
    fi
    print_success "Deployment package found: wp-migrate-plugin-staging.tar.gz"
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
    
    if scp -i "$STAGING_SSH_KEY" wp-migrate-plugin-staging.tar.gz "$STAGING_USER@$STAGING_SERVER:$STAGING_PATH/"; then
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
        
        echo "🚀 Starting deployment on staging server..."
        
        # Navigate to plugins directory
        cd /home/staging/public_html/wp-content/plugins/
        
        # Backup existing plugin if it exists
        if [ -d "wp-migrate" ]; then
            echo "📦 Backing up existing plugin..."
            mv wp-migrate wp-migrate.backup.$(date +%Y%m%d_%H%M%S)
        fi
        
        # Extract new plugin
        echo "📂 Extracting new plugin..."
        tar -xzf wp-migrate-plugin-staging.tar.gz
        
        # Set proper permissions
        echo "🔐 Setting permissions..."
        find wp-migrate/ -type f -exec chmod 644 {} \;
        find wp-migrate/ -type d -exec chmod 755 {} \;
        chmod +x wp-migrate/run-tests.sh
        
        # Clean up package
        rm wp-migrate-plugin-staging.tar.gz
        
        echo "✅ Plugin deployed successfully!"
        
        # Navigate to plugin directory
        cd wp-migrate/
        
        # Install dependencies (including dev dependencies for testing)
        echo "📦 Installing dependencies..."
        if command -v composer &> /dev/null; then
            composer install --optimize-autoloader
        else
            echo "⚠️  Composer not found. Please install dependencies manually."
        fi
        
        echo "🎯 Plugin ready for testing!"
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
        
        # Run full test suite
        echo "🧪 Running full test suite..."
        if [ -f "run-tests.sh" ]; then
            echo "🔐 Running security tests first..."
            ./run-tests.sh security
            
            echo "🚀 Running all tests with coverage..."
            ./run-tests.sh all
            
            echo "📊 Test results available in tests/coverage/html/"
        else
            echo "⚠️  Test runner not found. Please run tests manually."
        fi
        
        echo "✅ All tests completed!"
EOF
}

# Main deployment process
main() {
    echo "🚀 WP-Migrate Plugin - Staging Deployment"
    echo "=========================================="
    echo ""
    echo "🎯 Target Server: $STAGING_SERVER"
    echo "👤 User: $STAGING_USER"
    echo "🔑 SSH Key: $STAGING_SSH_KEY"
    echo "📁 Path: $STAGING_PATH"
    echo ""
    
    # Check package
    check_package
    
    # Check SSH key
    check_ssh_key
    
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
    echo ""
    echo "🌐 WordPress Admin: http://45.33.31.79/wp-admin/"
    echo "🔑 Login with your WordPress admin credentials"
}

# Run main function
main "$@"
