#!/bin/bash

# 🚀 WP-Migrate Plugin - Universal Deployment Script
# Single script for both production and staging deployment
# Follows DRY principles and 10x engineering best practices

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration - Environment-specific settings
PRODUCTION_SERVER="45.33.31.79"
PRODUCTION_USER="motherknitter"
PRODUCTION_SSH_KEY="/Users/vidarbrekke/Dev/socialintent/motherknitter.pem"
PRODUCTION_PATH="/home/motherknitter/public_html/wp-content/plugins"
PRODUCTION_WP_PATH="/home/motherknitter/public_html"
PRODUCTION_STRICT="true"

STAGING_SERVER="45.33.31.79"
STAGING_USER="staging"
STAGING_SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
STAGING_PATH="/home/staging/public_html/wp-content/plugins"
STAGING_WP_PATH="/home/staging/public_html"
STAGING_STRICT="false"

# Get environment variable
get_env_var() {
    local env=$1
    local var=$2

    case "$env" in
        "production")
            case "$var" in
                "SERVER") echo "$PRODUCTION_SERVER" ;;
                "USER") echo "$PRODUCTION_USER" ;;
                "SSH_KEY") echo "$PRODUCTION_SSH_KEY" ;;
                "PATH") echo "$PRODUCTION_PATH" ;;
                "WP_PATH") echo "$PRODUCTION_WP_PATH" ;;
                "STRICT") echo "$PRODUCTION_STRICT" ;;
                *) echo "" ;;
            esac
            ;;
        "staging")
            case "$var" in
                "SERVER") echo "$STAGING_SERVER" ;;
                "USER") echo "$STAGING_USER" ;;
                "SSH_KEY") echo "$STAGING_SSH_KEY" ;;
                "PATH") echo "$STAGING_PATH" ;;
                "WP_PATH") echo "$STAGING_WP_PATH" ;;
                "STRICT") echo "$STAGING_STRICT" ;;
                *) echo "" ;;
            esac
            ;;
        *)
            echo ""
            ;;
    esac
}

# Helper functions
log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }
log_step() { echo -e "\n${PURPLE}🔧 $1${NC}"; echo "=========================================="; }

# Validate environment
validate_environment() {
    local env=$1
    if [[ ! " production staging " =~ " $env " ]]; then
        log_error "Invalid environment: $env. Must be 'production' or 'staging'"
        exit 1
    fi

    # Check SSH key exists
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    if [[ ! -f "$ssh_key" ]]; then
        log_error "SSH key not found: $ssh_key"
        exit 1
    fi

    # Check we're in the right directory
    if [[ ! -f "wp-migrate.php" ]]; then
        log_error "Not in plugin root directory. Please run from wp-migrate plugin folder."
        exit 1
    fi

    log_success "Environment validation passed for $env"
}

# Create backup
create_backup() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local wp_path=$(get_env_var "$env" "WP_PATH")

    log_step "Creating backup on $env server..."

    ssh -i "$ssh_key" "$user@$server" << EOF
        cd $wp_path/wp-content/plugins/

        if [ -d "wp-migrate" ]; then
            TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
            BACKUP_NAME="wp-migrate_backup_\${TIMESTAMP}"

            echo "📦 Creating backup: \$BACKUP_NAME"
            cp -r wp-migrate "\$BACKUP_NAME"

            # Keep only last 3 backups
            ls -t wp-migrate_backup_* 2>/dev/null | tail -n +4 | xargs -r rm -rf 2>/dev/null || true

            echo "✅ Backup created: \$BACKUP_NAME"
        else
            echo "ℹ️  No existing plugin to backup"
        fi
EOF
}

# Deactivate plugin
deactivate_plugin() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local wp_path=$(get_env_var "$env" "WP_PATH")

    log_step "Deactivating plugin on $env server..."

    ssh -i "$ssh_key" "$user@$server" << EOF
        cd $wp_path

        if wp plugin is-active wp-migrate 2>/dev/null; then
            echo "🔌 Deactivating wp-migrate plugin..."
            wp plugin deactivate wp-migrate
        else
            echo "ℹ️  Plugin not active"
        fi
EOF
}

# Increment version
increment_version() {
    log_step "Incrementing plugin version..."

    if [[ -f "version.sh" ]]; then
        chmod +x version.sh
        ./version.sh patch
        local new_version=$(./version.sh current)
        log_success "Version incremented to: $new_version"
    else
        log_warning "Version manager not found, using current version"
    fi
}

# Sync files
sync_files() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local path=$(get_env_var "$env" "PATH")

    log_step "Syncing plugin files to $env server..."

    # Exclude unnecessary files
    rsync -avz --delete \
        --exclude='.git/' \
        --exclude='tests/' \
        --exclude='vendor/' \
        --exclude='*.log' \
        --exclude='coverage/' \
        --exclude='node_modules/' \
        --exclude='.DS_Store' \
        --exclude='deploy*.sh' \
        --exclude='*.zip' \
        --exclude='.version' \
        -e "ssh -i $ssh_key" \
        ./ "$user@$server:$path/wp-migrate/"

    log_success "Plugin files synced to $env server"
}

# Install dependencies
install_dependencies() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local path=$(get_env_var "$env" "PATH")

    log_step "Installing dependencies on $env server..."

    ssh -i "$ssh_key" "$user@$server" << EOF
        cd $path/wp-migrate/

        if [ -f "composer.json" ]; then
            echo "📦 Installing Composer dependencies..."
            composer install --no-dev --optimize-autoloader 2>/dev/null || echo "⚠️  Composer install failed, continuing..."
        else
            echo "ℹ️  No Composer dependencies to install"
        fi
EOF
}

# Set permissions
set_permissions() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local path=$(get_env_var "$env" "PATH")

    log_step "Setting file permissions on $env server..."

    ssh -i "$ssh_key" "$user@$server" << EOF
        cd $path/wp-migrate/

        echo "🔐 Setting WordPress permissions..."
        find . -type f -exec chmod 644 {} \; 2>/dev/null || true
        find . -type d -exec chmod 755 {} \; 2>/dev/null || true

        # Make scripts executable if they exist
        for script in "run-tests.sh" "version-manager.sh"; do
            if [ -f "\$script" ]; then
                chmod +x "\$script"
            fi
        done

        echo "✅ Permissions set correctly"
EOF
}

# Activate plugin
activate_plugin() {
    local env=$1
    local server=$(get_env_var "$env" "SERVER")
    local user=$(get_env_var "$env" "USER")
    local ssh_key=$(get_env_var "$env" "SSH_KEY")
    local wp_path=$(get_env_var "$env" "WP_PATH")
    local strict=$(get_env_var "$env" "STRICT")

    log_step "Activating plugin on $env server..."

    ssh -i "$ssh_key" "$user@$server" << EOF
        cd $wp_path

        echo "🔌 Activating wp-migrate plugin..."

        # Check MySQL extension
        if php -m | grep -q mysqli 2>/dev/null; then
            echo "✅ MySQL extension available"

            if wp plugin activate wp-migrate 2>/dev/null; then
                echo "✅ Plugin activated successfully"
                exit 0
            else
                echo "❌ Plugin activation failed"
                if [ "$strict" = "true" ]; then
                    exit 1
                fi
            fi
        else
            echo "❌ CRITICAL: MySQL extension not available on $env server"
            echo "🔧 Server configuration issue: PHP mysqli extension missing"
            echo "💡 Plugin files deployed but NOT activated"
            if [ "$strict" = "true" ]; then
                exit 1
            fi
        fi
EOF
}

# Deploy to environment
deploy() {
    local env=$1

    echo "🚀 WP-Migrate Plugin - $env DEPLOYMENT"
    echo "=================================="
    echo ""
    echo "🎯 Target: $(get_env_var "$env" "SERVER") ($env)"
    echo "👤 User: $(get_env_var "$env" "USER")"
    echo "📁 Path: $(get_env_var "$env" "PATH")"
    echo ""

    # Validate
    validate_environment "$env"

    # Execute deployment steps
    increment_version
    create_backup "$env"
    deactivate_plugin "$env"
    sync_files "$env"
    install_dependencies "$env"
    set_permissions "$env"
    activate_plugin "$env"

    log_success "🎉 $env deployment completed successfully!"

    echo ""
    echo "📋 Next steps:"
    echo "  1. Verify plugin is working in WordPress admin"
    echo "  2. Monitor for any errors in $env logs"
    echo "  3. Test critical functionality"
    echo ""
}

# Main execution
main() {
    if [[ $# -ne 1 ]]; then
        log_error "Usage: $0 <environment>"
        log_error "Environments: production, staging"
        exit 1
    fi

    local env=$1
    deploy "$env"
}

# Run main function
main "$@"
