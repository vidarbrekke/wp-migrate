#!/bin/bash

# 🚀 WP-Migrate Version Manager
# Simple version management for plugin deployment
# Follows semantic versioning: major.minor.patch

set -e

VERSION_FILE=".version"
DEFAULT_VERSION="1.0.0"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get current version
current() {
    if [[ -f "$VERSION_FILE" ]]; then
        cat "$VERSION_FILE"
    else
        echo "$DEFAULT_VERSION"
    fi
}

# Increment patch version
patch() {
    local current_version=$(current)
    local major=$(echo "$current_version" | cut -d. -f1)
    local minor=$(echo "$current_version" | cut -d. -f2)
    local patch=$(echo "$current_version" | cut -d. -f3)

    local new_patch=$((patch + 1))
    local new_version="$major.$minor.$new_patch"

    echo "$new_version" > "$VERSION_FILE"
    echo -e "${GREEN}Version bumped to: $new_version${NC}"
}

# Show help
help() {
    echo "WP-Migrate Version Manager"
    echo ""
    echo "Usage: $0 <command>"
    echo ""
    echo "Commands:"
    echo "  current    Show current version"
    echo "  patch      Increment patch version (e.g., 1.0.0 → 1.0.1)"
    echo "  help       Show this help"
    echo ""
    echo "Version file: $VERSION_FILE"
}

# Main execution
main() {
    case "${1:-current}" in
        "current")
            current
            ;;
        "patch")
            patch
            ;;
        "help"|"-h"|"--help")
            help
            ;;
        *)
            echo -e "${YELLOW}Unknown command: $1${NC}"
            echo ""
            help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
