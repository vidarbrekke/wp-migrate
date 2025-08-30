# WP-Migrate: Handover Document

## 🎯 **Project Overview**

**WP-Migrate** is a production-ready WordPress plugin for secure, resumable migrations from production to staging environments. All core features are complete and tested.

### **Core Functionality**
- **Automated migrations**: Complete site duplication (files + database)
- **Security**: HMAC authentication, TLS enforcement, nonce protection
- **Resumability**: Automatic recovery from interruptions
- **Safety**: Email/webhook blackholing, rollback capability

### **Technical Stack**
- **WordPress**: 6.2+ required
- **Database**: MySQL/MariaDB only
- **PHP**: 7.4+ with standard extensions
- **Security**: HTTPS mandatory

## 📊 **Completed Work & Outcomes**

### ✅ **Major Refactoring (Latest)**
- **Plugin renamed**: `mk-wc-plugin-starter.php` → `wp-migrate.php`
- **Namespace updated**: `MK\WcPluginStarter` → `WpMigrate` (all PHP files)
- **Folder restructured**: `mk-wc-plugin-starter/` → `wp-migrate/`
- **Constants updated**: `MK_WCPS_*` → `WP_MIGRATE_*`
- **Obsolete code removed**: Frontend assets, WooCommerce starter code

### ✅ **Production Features (100% Complete)**
- **Security**: HMAC-SHA256, TLS enforcement, nonce protection
- **REST API**: 6 endpoints with authentication wrapper
- **File Management**: 64MB chunked uploads with SHA256 validation
- **State Machine**: 9 states with strict transition validation
- **Database Engine**: Export/import with URL rewriting
- **Testing**: 100+ tests, 95%+ coverage, staging deployment

## 🔧 **Resolved Issues & Lessons Learned**

### **Deployment Problems Fixed**
- **Tar extraction issues**: Incorrect package naming caused extraction failures
- **Permission errors**: PHPUnit binary lacked execute permissions
- **Test suite discovery**: Incorrect PHPUnit commands for test execution
- **Timestamp skew**: HMAC headers generated at wrong time causing validation failures

### **Testing Infrastructure Issues**
- **Mock expectations**: Incorrect call counts in API tests
- **State transitions**: Missing proper job state setup in tests
- **File permissions**: Cache directories interfering with git operations
- **Metadata files**: macOS files causing PHPUnit warnings

### **Key Lessons**
- **Time-sensitive authentication**: Generate HMAC headers at runtime, not build time
- **State validation**: Always set complete job workflow in tests
- **File operations**: Clean cache directories before git operations
- **Test isolation**: Use live timestamps for server-side validation

## 📁 **Key Files & Directories**

### **Plugin Structure**
```
wp-migrate/
├── src/                          # Core services
│   ├── Security/HmacAuth.php     # Authentication logic
│   ├── Rest/Api.php             # REST endpoints
│   ├── Migration/               # Database operations
│   ├── Files/ChunkStore.php     # File upload handling
│   └── State/JobManager.php     # State management
├── tests/                       # Test suites
│   ├── Security/               # Authentication tests
│   ├── Rest/                   # API endpoint tests
│   └── Migration/              # Workflow tests
├── wp-migrate.php              # Main plugin file
└── composer.json               # Dependencies
```

### **Critical Files for Development**
- **`src/Plugin.php`**: Service registration and bootstrap
- **`src/Security/HmacAuth.php`**: Core authentication (MAX_SKEW_MS = 300000ms)
- **`src/Rest/Api.php`**: All endpoint definitions
- **`tests/TestHelper.php`**: Test utilities (use `generateLiveHmacHeaders`)
- **`phpunit.xml`**: Test configuration and coverage settings

## ⚠️ **Gotchas & Critical Insights**

### **Authentication**
- **Runtime timestamps**: Always use current time for HMAC headers
- **Clock skew tolerance**: 5 minutes maximum (300000ms)
- **Header format**: `X-Migrate-Timestamp`, `X-Migrate-Signature`, `X-Migrate-Nonce`

### **State Management**
- **Strict transitions**: 9 states with validation (see JobManager.php)
- **Persistence**: WordPress options with `autoload=false`
- **Job naming**: `wp_migrate_job_{job_id}` format

### **File Operations**
- **Chunk size**: 64MB maximum per upload
- **Storage path**: `wp-uploads/wp-migrate-jobs/`
- **Hash verification**: SHA256 for all chunks
- **Cleanup**: Automatic temp file removal

### **Testing**
- **Live headers**: Use `TestHelper::generateLiveHmacHeaders()` not `generateValidHmacHeaders()`
- **State setup**: Always transition through complete workflow in tests
- **Permissions**: Ensure PHPUnit binary is executable
- **Cache cleanup**: Remove `.phpunit.cache` before git operations

### **WordPress Integration**
- **Hooks**: `wp_migrate_booted`, `wp_migrate_services_registered`
- **Options**: `wp_migrate_settings` for configuration
- **Text domain**: `wp-migrate` for translations
- **Menu**: `wp_migrate` slug for admin interface

## 🏗️ **Architecture Overview**

### **Service Architecture**
- **DRY Principle**: Single `HmacAuth` class for all authentication
- **YAGNI**: No external dependencies, WordPress-native functions only
- **Security First**: HMAC-SHA256, TLS enforcement, nonce protection
- **Service-Oriented**: Each feature implements `WpMigrate\Contracts\Registrable`

### **Migration Workflow**
```
1. Handshake     → Verify connectivity & preflight checks
2. Prepare       → Initialize job state & configuration
3. File Sync     → Chunked uploads with resume capability
4. Database      → Export/import with URL rewriting
5. Finalize      → Cleanup & activation
```

### **State Machine**
```
created → preflight_ok → files_pass1 → db_exported →
db_uploaded → db_imported → url_replaced → files_pass2 →
finalized → done
```

## 🛠️ **Development Setup**

### **Quick Start**
```bash
git clone https://github.com/vidarbrekke/wp-migrate.git
cd wp-migrate/wp-migrate
composer install
```

### **WordPress Installation**
1. Copy `wp-migrate/` to `wp-content/plugins/`
2. Activate **"WP-Migrate: Production → Staging Migration"**
3. Configure in **Settings → WP-Migrate**

### **Testing**
- **Test Runner**: `./run-tests.sh all` for full suite
- **Coverage**: 95%+ across security, API, and migration tests
- **Staging**: Automated deployment with `./deploy-to-staging.sh`

## 📋 **Command Reference**

### **Available Commands**
- `health`: System capability validation
- `prepare`: Job initialization and state setup
- `db_import`: Database import with URL rewriting
- `search_replace`: Serializer-safe URL replacement
- `finalize`: Migration completion and cleanup
- `rollback`: Automated restoration from snapshots

## 🔐 **Security Implementation**

### **HMAC Authentication**
- **Algorithm**: HMAC-SHA256 with shared secret
- **Headers**: `X-Migrate-Timestamp`, `X-Migrate-Signature`, `X-Migrate-Nonce`
- **Clock Skew**: 5 minutes tolerance (300000ms)
- **Nonce TTL**: 1 hour to prevent replay attacks

### **File Security**
- **Chunk Size**: 64MB maximum per upload
- **Validation**: SHA256 hash verification for all chunks
- **Storage**: `wp-uploads/wp-migrate-jobs/` with secure permissions
- **Cleanup**: Automatic temporary file removal

## 📚 **Essential Documentation**

### **Technical Specs**
- **`dev-plan-dry-yagni.md`**: Implementation requirements and roadmap
- **`api-contract-dry-yagni.md`**: REST API endpoint specifications
- **`wp-migrate/ARCHITECTURE.md`**: Technical design decisions
- **`wp-migrate/IMPLEMENTATION_STATUS.md`**: Feature completion tracking
- **`wp-migrate/TESTING_SUMMARY.md`**: Test coverage and strategy

### **Development Workflow**
1. **Setup**: Clone repo, run `composer install`
2. **Testing**: Execute `./run-tests.sh all` for full validation
3. **Deployment**: Use `./deploy-to-staging.sh` for automated staging
4. **WordPress**: Copy to `wp-content/plugins/`, activate, configure

---

## ⚠️ **Critical Reminders**

### **Do Not Change**
- **Security model**: HMAC authentication is validated and secure
- **State machine**: 9-state workflow with strict validation
- **File handling**: Chunked uploads with resume capability
- **Architecture**: Service-oriented design with DRY principles

### **Always Verify**
- **HMAC headers**: Use live timestamps, not cached values
- **State transitions**: Complete workflow setup in tests
- **File permissions**: Executable PHPUnit, writable upload directories
- **Cache cleanup**: Remove `.phpunit.cache` before git operations

### **WordPress Standards**
- **Hooks**: Use `wp_migrate_booted`, `wp_migrate_services_registered`
- **Options**: Store in `wp_migrate_settings`
- **Text Domain**: `wp-migrate` for all translations
- **Capabilities**: Implement proper access controls

---

**This plugin is production-ready with comprehensive testing and security hardening. Focus on maintenance, bug fixes, and incremental improvements following DRY & YAGNI principles.**
