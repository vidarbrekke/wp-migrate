#!/bin/bash

# WP-Migrate Version Manager
# Follows semantic versioning: MAJOR.MINOR.PATCH

set -e

VERSION_FILE="VERSION"
PLUGIN_FILE="wp-migrate.php"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_usage() {
    echo "Usage: $0 [major|minor|patch|current]"
    echo "  major  - Increment major version (1.0.0 -> 2.0.0)"
    echo "  minor  - Increment minor version (1.0.0 -> 1.1.0)"
    echo "  patch  - Increment patch version (1.0.0 -> 1.0.1)"
    echo "  current- Show current version"
    echo "  update - Update plugin files with current VERSION"
}

get_current_version() {
    if [ ! -f "$VERSION_FILE" ]; then
        echo "1.0.0"
        return
    fi
    cat "$VERSION_FILE"
}

set_version() {
    local new_version=$1
    echo "$new_version" > "$VERSION_FILE"

    # Update plugin header
    sed -i.bak "s/Version: .*/Version: $new_version/" "$PLUGIN_FILE"
    sed -i.bak "s/WP_MIGRATE_VERSION', '[^']*'/WP_MIGRATE_VERSION', '$new_version'/" "$PLUGIN_FILE"

    # Clean up backup files
    rm -f "${PLUGIN_FILE}.bak"

    echo -e "${GREEN}Version updated to $new_version${NC}"
}

increment_version() {
    local current_version=$(get_current_version)
    local version_type=$1

    # Parse version components
    IFS='.' read -ra VERSION_PARTS <<< "$current_version"
    local major=${VERSION_PARTS[0]}
    local minor=${VERSION_PARTS[1]}
    local patch=${VERSION_PARTS[2]}

    case $version_type in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
        *)
            echo -e "${RED}Invalid version type: $version_type${NC}"
            print_usage
            exit 1
            ;;
    esac

    local new_version="$major.$minor.$patch"
    set_version "$new_version"
}

update_plugin_files() {
    local version=$(get_current_version)
    set_version "$version"
}

case "$1" in
    major|minor|patch)
        increment_version "$1"
        ;;
    current)
        echo -e "${BLUE}Current version: $(get_current_version)${NC}"
        ;;
    update)
        update_plugin_files
        ;;
    "")
        print_usage
        ;;
    *)
        echo -e "${RED}Unknown option: $1${NC}"
        print_usage
        exit 1
        ;;
esac
