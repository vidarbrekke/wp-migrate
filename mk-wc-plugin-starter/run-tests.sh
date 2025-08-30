#!/bin/bash

# WP-Migrate Test Runner
# Comprehensive testing script for the WordPress migration plugin

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to run tests with coverage
run_with_coverage() {
    print_status "Running tests with coverage report..."

    if [ ! -d "tests/coverage" ]; then
        mkdir -p tests/coverage/html
        mkdir -p tests/coverage/xml
        mkdir -p tests/results
    fi

    vendor/bin/phpunit \
        --coverage-html=tests/coverage/html \
        --coverage-text=tests/coverage/coverage.txt \
        --coverage-clover=tests/coverage/clover.xml \
        --coverage-xml=tests/coverage/xml \
        --log-junit=tests/results/junit.xml \
        --testdox-html=tests/results/testdox.html \
        --testdox-text=tests/results/testdox.txt \
        "$@"

    print_success "Coverage report generated at: tests/coverage/html/index.html"
}

# Function to run specific test suites
run_test_suite() {
    local suite_name="$1"
    print_status "Running $suite_name tests..."

    vendor/bin/phpunit --testsuite "$suite_name" "$@"
}

# Function to run security tests only
run_security_tests() {
    print_status "Running security tests..."
    run_test_suite "WP-Migrate Security Tests" "$@"
}

# Function to run critical path tests
run_critical_tests() {
    print_status "Running critical path tests..."
    run_test_suite "WP-Migrate Critical Path" "$@"
}

# Function to run integration tests
run_integration_tests() {
    print_status "Running integration tests..."
    run_test_suite "WP-Migrate Integration Tests" "$@"
}

# Function to run performance tests
run_performance_tests() {
    print_status "Running performance tests..."
    print_warning "Performance tests require additional setup"

    vendor/bin/phpunit \
        --profile \
        --testsuite "WP-Migrate Critical Path" \
        "$@"
}

# Function to check test environment
check_environment() {
    print_status "Checking test environment..."

    # Check PHP version
    if ! command_exists php; then
        print_error "PHP is not installed or not in PATH"
        exit 1
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status "PHP Version: $PHP_VERSION"

    # Check PHPUnit
    if [ ! -f "vendor/bin/phpunit" ]; then
        print_error "PHPUnit not found. Run 'composer install' first."
        exit 1
    fi

    # Check if Xdebug is available (optional)
    if php -m | grep -q "xdebug"; then
        print_success "Xdebug is available for debugging"
    else
        print_warning "Xdebug not found - debugging features limited"
    fi

    # Check test directories
    if [ ! -d "tests" ]; then
        print_error "Tests directory not found"
        exit 1
    fi

    print_success "Environment check passed"
}

# Function to clean test artifacts
clean_test_artifacts() {
    print_status "Cleaning test artifacts..."

    rm -rf tests/coverage/
    rm -rf tests/results/
    rm -f tests/.phpunit.cache

    print_success "Test artifacts cleaned"
}

# Function to show help
show_help() {
    cat << EOF
WP-Migrate Test Runner

USAGE:
    ./run-tests.sh [OPTIONS] [COMMAND]

COMMANDS:
    all                 Run all tests with coverage (default)
    critical           Run critical path tests only
    security           Run security tests only
    integration        Run integration tests only
    performance        Run performance tests
    clean              Clean test artifacts
    help               Show this help message

OPTIONS:
    --no-coverage       Run tests without coverage report
    --verbose          Verbose output
    --stop-on-failure   Stop on first failure
    --debug            Debug mode with Xdebug
    --filter PATTERN    Run tests matching pattern
    --profile          Performance profiling

EXAMPLES:
    ./run-tests.sh                      # Run all tests with coverage
    ./run-tests.sh critical             # Run critical path tests
    ./run-tests.sh security             # Run security tests only
    ./run-tests.sh --verbose all        # Run all tests with verbose output
    ./run-tests.sh --filter HmacAuthTest security  # Run specific test

TEST SUITES:
    WP-Migrate Critical Path    - Core security and functionality
    WP-Migrate Security Tests   - Authentication and security features
    WP-Migrate Integration Tests- API endpoint integration
    WP-Migrate Core Tests       - All tests

COVERAGE REPORTS:
    HTML: tests/coverage/html/index.html
    Text: tests/coverage/coverage.txt
    XML:  tests/coverage/clover.xml

For more information, see: tests/README.md
EOF
}

# Main script logic
main() {
    local command="all"
    local phpunit_args=()

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            all|critical|security|integration|performance|clean|help)
                command="$1"
                shift
                ;;
            --no-coverage)
                NO_COVERAGE=1
                shift
                ;;
            --verbose|--stop-on-failure|--debug|--profile)
                phpunit_args+=("$1")
                shift
                ;;
            --filter)
                phpunit_args+=("$1" "$2")
                shift 2
                ;;
            *)
                print_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done

    # Handle commands
    case $command in
        help)
            show_help
            exit 0
            ;;
        clean)
            clean_test_artifacts
            exit 0
            ;;
        all)
            check_environment
            if [ -z "$NO_COVERAGE" ]; then
                run_with_coverage "${phpunit_args[@]}"
            else
                vendor/bin/phpunit "${phpunit_args[@]}"
            fi
            ;;
        critical)
            check_environment
            run_critical_tests "${phpunit_args[@]}"
            ;;
        security)
            check_environment
            run_security_tests "${phpunit_args[@]}"
            ;;
        integration)
            check_environment
            run_integration_tests "${phpunit_args[@]}"
            ;;
        performance)
            check_environment
            run_performance_tests "${phpunit_args[@]}"
            ;;
        *)
            print_error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac

    print_success "Test execution completed!"
}

# Check if we're in the right directory
if [ ! -f "phpunit.xml" ] || [ ! -d "tests" ]; then
    print_error "This script must be run from the plugin root directory"
    print_error "Expected files: phpunit.xml, tests/"
    exit 1
fi

# Run main function
main "$@"
