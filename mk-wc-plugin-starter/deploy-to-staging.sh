#!/bin/bash

# 🚀 WP-Migrate Plugin - Staging Deployment Script
# This script syncs local plugin code to staging server using rsync
#
# Updated: 2025-08-30
# - Simplified to use rsync instead of ZIP files
# - Local version management only
# - Direct file sync to server
# - Clean and efficient deployment

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

print_step() {
    echo -e "${PURPLE}[STEP]${NC} $1"
}

# Increment version before deployment
increment_version() {
    print_status "Incrementing plugin version..."
    
    if [ -f "version-manager.sh" ]; then
        chmod +x version-manager.sh
        ./version-manager.sh patch
        NEW_VERSION=$(./version-manager.sh current)
        print_success "Version incremented to: $NEW_VERSION"
    else
        print_warning "Version manager not found, using current version"
    fi
}

# Create backup on staging server
create_backup() {
    print_step "Creating backup of current plugin on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/wp-content/plugins/
        
        if [ -d "wp-migrate" ]; then
            TIMESTAMP=$(date +%Y%m%d_%H%M%S)
            BACKUP_NAME="wp-migrate_backup_${TIMESTAMP}"
            
            echo "📦 Creating backup: $BACKUP_NAME"
            cp -r wp-migrate "$BACKUP_NAME"
            
            # Keep only last 3 backups
            ls -t wp-migrate_backup_* 2>/dev/null | tail -n +4 | xargs -r rm -rf
            
            echo "✅ Backup created: $BACKUP_NAME"
        else
            echo "ℹ️  No existing plugin to backup"
        fi
EOF
}

# Deactivate plugin on staging server
deactivate_plugin() {
    print_step "Deactivating plugin on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/
        
        if wp plugin is-active wp-migrate 2>/dev/null; then
            echo "🔌 Deactivating wp-migrate plugin..."
            wp plugin deactivate wp-migrate
        else
            echo "ℹ️  Plugin not active"
        fi
EOF
}

# Sync plugin files to staging server
sync_plugin() {
    print_step "Syncing plugin files to staging server..."
    
    # Exclude unnecessary files from sync
    rsync -avz --delete \
        --exclude='.git/' \
        --exclude='tests/' \
        --exclude='vendor/' \
        --exclude='*.log' \
        --exclude='coverage/' \
        --exclude='node_modules/' \
        --exclude='.DS_Store' \
        -e "ssh -i $STAGING_SSH_KEY" \
        ./ "$STAGING_USER@$STAGING_SERVER:$STAGING_PATH/$PLUGIN_NAME/"
    
    print_success "Plugin files synced to staging server"
}

# Install dependencies on staging server
install_dependencies() {
    print_step "Installing dependencies on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/wp-content/plugins/wp-migrate/
        
        if [ -f "composer.json" ]; then
            echo "📦 Installing Composer dependencies..."
            composer install --no-dev --optimize-autoloader
        else
            echo "ℹ️  No Composer dependencies to install"
        fi
EOF
}

# Set permissions on staging server
set_permissions() {
    print_step "Setting file permissions on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/wp-content/plugins/wp-migrate/
        
        echo "🔐 Setting WordPress permissions..."
        find . -type f -exec chmod 644 {} \;
        find . -type d -exec chmod 755 {} \;
        
        # Make scripts executable
        if [ -f "run-tests.sh" ]; then
            chmod +x run-tests.sh
        fi
        if [ -f "version-manager.sh" ]; then
            chmod +x version-manager.sh
        fi
        
        echo "✅ Permissions set correctly"
EOF
}

# Activate plugin on staging server
activate_plugin() {
    print_step "Activating plugin on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/
        
        echo "🔌 Activating wp-migrate plugin..."
        wp plugin activate wp-migrate
        
        if wp plugin is-active wp-migrate; then
            echo "✅ Plugin activated successfully"
        else
            echo "❌ Plugin activation failed"
            exit 1
        fi
EOF
}

# Run tests on staging server
run_tests() {
    print_step "Running tests on staging server..."
    
    ssh -i "$STAGING_SSH_KEY" "$STAGING_USER@$STAGING_SERVER" << 'EOF'
        cd /home/staging/public_html/wp-content/plugins/wp-migrate/
        
        if [ -f "run-tests.sh" ]; then
            echo "🧪 Running test suite..."
            ./run-tests.sh all
        else
            echo "⚠️  Test runner not found"
        fi
EOF
}

# Main deployment function
main() {
    echo "🚀 WP-Migrate Plugin - STAGING DEPLOYMENT"
    echo "============================================"
    echo ""
    echo "🎯 Target Server: $STAGING_SERVER (Staging)"
    echo "👤 User: $STAGING_USER"
    echo "🔑 SSH Key: $STAGING_SSH_KEY"
    echo "📁 Path: $STAGING_PATH"
    echo ""
    
    # Pre-deployment checks
    if [ ! -f "$STAGING_SSH_KEY" ]; then
        print_error "SSH key not found: $STAGING_SSH_KEY"
        exit 1
    fi
    
    if [ ! -f "wp-migrate.php" ]; then
        print_error "Not in plugin root directory. Please run from wp-migrate plugin folder."
        exit 1
    fi
    
    # Deployment steps
    increment_version
    create_backup
    deactivate_plugin
    sync_plugin
    install_dependencies
    set_permissions
    activate_plugin
    
    print_success "🎉 Deployment completed successfully!"
    echo ""
    echo "📋 Next steps:"
    echo "  1. Verify plugin is working in WordPress admin"
    echo "  2. Run tests: ssh -i $STAGING_SSH_KEY $STAGING_USER@$STAGING_SERVER"
    echo "  3. Check logs and functionality"
    echo ""
}

# Run main function
main "$@"
