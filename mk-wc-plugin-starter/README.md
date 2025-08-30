# WP-Migrate: Production → Staging Migration Plugin

A WordPress plugin for secure, resumable migrations from production to staging environments. Built with DRY & YAGNI principles, focusing on MySQL/MariaDB migrations with file synchronization.

## 🎯 Purpose

This plugin enables automated WordPress site migrations between production and staging environments with:
- **Security**: HMAC-signed requests with shared keys
- **Reliability**: Resumable chunked uploads and state management
- **Safety**: Preflight checks and staging environment protection
- **Simplicity**: Minimal configuration, maximum compatibility

## 🏗️ Architecture

### Core Components

- **Security**: `HmacAuth` - HMAC verification with proxy-aware TLS detection
- **REST API**: `Api` - Handles migration endpoints with authentication
- **File Management**: `ChunkStore` - Resumable chunked uploads with validation
- **State Management**: `StateStore` & `JobManager` - Job lifecycle and persistence
- **Logging**: `JsonLogger` - Structured logging with redaction
- **Preflight**: `Checker` - System capability and requirement validation

### Service Structure

```
src/
├── Admin/          # Settings UI for shared keys and peer URLs
├── Security/       # HMAC authentication and TLS validation
├── Rest/           # REST API endpoints (/handshake, /command, /chunk, etc.)
├── Files/          # Chunked file storage and resume functionality
├── State/          # Job state persistence and management
├── Logging/        # JSON-structured logging system
├── Preflight/      # System requirement validation
├── Migration/      # Job lifecycle management
├── Contracts/      # Interface definitions
└── Assets/         # Frontend resources
```

## 🚀 Quick Start

### 1. Installation

```bash
# Clone the repository
git clone https://github.com/vidarbrekke/wp-migrate.git
cd wp-migrate/mk-wc-plugin-starter

# Install dependencies
composer install

# Activate in WordPress admin
```

### 2. Configuration

1. Go to **Settings → MK WC Starter**
2. Set **Shared Key** (used for HMAC signing between peers)
3. Set **Peer Base URL** (destination site URL)
4. Enable **Email/Webhook Blackhole** for staging safety

### 3. Usage

The plugin exposes REST endpoints at `/wp-json/migrate/v1/`:

- `POST /handshake` - Verify connectivity and capabilities
- `POST /command` - Execute migration actions (prepare, health)
- `POST /chunk` - Upload file chunks with resume support
- `GET /progress` - Check job status and progress
- `GET /logs/tail` - Retrieve recent log entries

## 🔒 Security Features

- **HMAC Authentication**: All requests signed with shared keys
- **TLS Enforcement**: HTTPS required (with proxy header support)
- **Nonce Protection**: Replay attack prevention (1-hour TTL)
- **Input Validation**: REST args validation and sanitization
- **Path Traversal Protection**: Secure file path handling

## 📊 Migration Workflow

1. **Handshake** → Verify connectivity and run preflight checks
2. **Prepare** → Set job state and configuration
3. **File Sync** → Chunked uploads with resume capability
4. **Database** → Export/import with URL rewriting
5. **Finalize** → Cleanup and activation

## 🛠️ Technical Details

### Requirements
- PHP 7.4+
- WordPress 6.2+
- MySQL/MariaDB (PostgreSQL not supported)
- Composer for dependency management

### Capability Detection
- **rsync**: Available system binary
- **zstd**: Compression support
- **wp-cli**: Command-line interface availability

### File Handling
- Chunk size limit: 64MB
- Storage location: `wp-uploads/mk-migrate-jobs/`
- Log location: `wp-uploads/mk-migrate-logs/`
- Resume support: Automatic chunk detection

## 🔧 Development

### Code Quality
- **PSR-4**: Autoloading standards
- **WordPress Standards**: Follow WordPress coding standards and best practices
- **Note**: Static analysis (PHPStan) and code standards (PHPCS) are planned for future phases

### Adding Features
1. Create class implementing `Registrable`
2. Add to `Plugin::register_services()`
3. Follow WordPress security best practices

### Testing
```bash
# Note: Automated testing suite is planned for future phases
# Current testing: Manual validation via REST endpoints
```

## 🚧 Current Status

### ✅ Implemented
- Core authentication and security
- REST API endpoints
- Chunked file uploads with resume
- Job state management
- Preflight system checks
- Settings administration

### 🚧 In Progress
- Database export/import engine
- URL rewriting for staging
- Rollback functionality
- WP-CLI integration

### 📋 Planned
- Multi-site support
- Advanced rollback strategies
- Performance optimizations
- Comprehensive testing suite

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Follow WordPress coding standards
4. Add tests for new functionality
5. Submit pull request

## 📄 License

This project is licensed under the GPL v2 or later.

## 🔗 Related

- [API Contract](api-contract-dry-yagni.md) - REST API specification
- [Development Plan](dev-plan-dry-yagni.md) - Implementation roadmap
- [Environment Setup](environment-setup.md) - Server configuration guide
