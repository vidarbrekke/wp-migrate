#!/bin/bash

# Simple Production Deployment Script for WP-Migrate Plugin

set -e

# Configuration
SERVER="45.33.31.79"
USER="motherknitter"
SSH_KEY="/Users/vidarbrekke/Dev/socialintent/motherknitter.pem"
PLUGIN_PATH="/home/motherknitter/public_html/wp-content/plugins"
PLUGIN_NAME="wp-migrate"
ZIP_FILE="wp-migrate-plugin-production-v3.zip"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 Deploying WP-Migrate to Production${NC}"
echo "============================================="
echo "Server: $SERVER"
echo "User: $USER"
echo "Path: $PLUGIN_PATH"
echo ""

# Check if deployment package exists
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}❌ Deployment package not found: $ZIP_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Found deployment package: $ZIP_FILE${NC}"

# Upload and deploy
echo -e "${BLUE}📤 Uploading to production server...${NC}"
scp -i "$SSH_KEY" "$ZIP_FILE" "$USER@$SERVER:$PLUGIN_PATH/"

echo -e "${BLUE}🔧 Deploying plugin...${NC}"
ssh -i "$SSH_KEY" "$USER@$SERVER" << 'SSH_EOF'
    cd /home/motherknitter/public_html/wp-content/plugins/
    
    # Create backup
    if [ -d "wp-migrate" ]; then
        BACKUP_NAME="wp-migrate_backup_$(date +%Y%m%d_%H%M%S)"
        cp -r wp-migrate "$BACKUP_NAME"
        echo "✅ Backup created: $BACKUP_NAME"
    fi
    
    # Remove old plugin
    rm -rf wp-migrate/
    
    # Extract new plugin
    unzip -o -q wp-migrate-plugin-production-v3.zip
    rm wp-migrate-plugin-production-v3.zip
    
    # Set permissions
    find wp-migrate/ -type f -exec chmod 644 {} \;
    find wp-migrate/ -type d -exec chmod 755 {} \;
    
    echo "✅ Plugin deployed successfully!"
    
    # Show version
    if [ -f "wp-migrate/VERSION" ]; then
        VERSION=$(cat wp-migrate/VERSION)
        echo "📋 Plugin version: $VERSION"
    fi
SSH_EOF

echo -e "${GREEN}🎉 Production deployment completed!${NC}"
echo ""
echo "Next steps:"
echo "1. Test the plugin on production"
echo "2. Run the migration dry-run test"
echo "3. Verify all endpoints are working"
