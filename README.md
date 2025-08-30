# WP-Migrate: WordPress Production → Staging Migration Tool

A secure, resumable WordPress migration plugin designed for production-to-staging deployments. Built with DRY & YAGNI principles, focusing on MySQL/MariaDB migrations with robust file synchronization.

## 🎯 What This Is

WP-Migrate is a WordPress plugin that enables automated, secure migrations between production and staging environments. It's designed for:

- **Agencies**: Migrate client sites to staging for testing
- **Developers**: Deploy updates safely with rollback capability  
- **DevOps**: Automate WordPress deployments in CI/CD pipelines
- **Site Owners**: Maintain staging environments synchronized with production

## 🚀 Current Status

**Phase**: Core Infrastructure Complete (65% done)  
**Next Milestone**: Database export/import engine  
**Ready For**: Development testing and early adoption  

### ✅ What's Working
- **Security**: HMAC authentication with shared keys
- **API**: Complete REST endpoint infrastructure
- **File Management**: Chunked uploads with resume support
- **State Management**: Job lifecycle and persistence
- **Preflight**: System requirement validation
- **Logging**: Structured logging with security

### 🚧 What's Next
- **Database Engine**: MySQL export/import with URL rewriting
- **Migration Workflow**: Complete end-to-end process
- **Rollback System**: Automated restoration from snapshots
- **WP-CLI**: Command-line interface

## 🏗️ Architecture

The plugin follows WordPress best practices with a service-oriented architecture:

```
src/
├── Security/       # HMAC authentication & TLS validation
├── Rest/           # REST API endpoints (/handshake, /command, /chunk, etc.)
├── Files/          # Chunked file storage with resume
├── State/          # Job state management & persistence
├── Logging/        # Structured JSON logging
├── Preflight/      # System capability validation
├── Migration/      # Job lifecycle management
└── Admin/          # Settings UI for configuration
```

## 🚀 Quick Start

### 1. Installation
```bash
git clone https://github.com/vidarbrekke/wp-migrate.git
cd wp-migrate/mk-wc-plugin-starter
composer install
```

### 2. WordPress Setup
1. Copy `mk-wc-plugin-starter` to `wp-content/plugins/`
2. Activate **WP-Migrate: Production → Staging Migration**
3. Go to **Settings → MK WC Starter**
4. Configure shared key and peer URL

### 3. Basic Usage
```bash
# Test connectivity
curl -X POST https://your-site.com/wp-json/migrate/v1/handshake \
  -H "X-MIG-Timestamp: $(date +%s)000" \
  -H "X-MIG-Nonce: $(openssl rand -base64 16)" \
  -H "X-MIG-Peer: https://staging-site.com" \
  -H "X-MIG-Signature: [calculated-hmac]"
```

## 🔒 Security Features

- **HMAC Authentication**: All requests cryptographically signed
- **TLS Enforcement**: HTTPS required (with proxy header support)
- **Nonce Protection**: Replay attack prevention
- **Input Validation**: Comprehensive parameter sanitization
- **Path Security**: Directory traversal protection

## 📊 Migration Workflow

1. **Handshake** → Verify connectivity & run preflight checks
2. **Prepare** → Set job state & configuration
3. **File Sync** → Chunked uploads with resume capability
4. **Database** → Export/import with URL rewriting
5. **Finalize** → Cleanup & activation

## 🛠️ Development

### Requirements
- PHP 7.4+
- WordPress 6.2+
- MySQL/MariaDB
- Composer

### Code Quality
```bash
# Static analysis
composer run analyze

# Code standards
composer run lint

# Autoloader
composer run autoload
```

### Adding Features
1. Create class implementing `Registrable`
2. Add to `Plugin::register_services()`
3. Follow WordPress security best practices

## 📚 Documentation

- **[Plugin README](mk-wc-plugin-starter/README.md)** - Plugin-specific documentation
- **[Architecture](mk-wc-plugin-starter/ARCHITECTURE.md)** - Technical design decisions
- **[Implementation Status](mk-wc-plugin-starter/IMPLEMENTATION_STATUS.md)** - Current progress
- **[API Contract](api-contract-dry-yagni.md)** - REST API specification
- **[Development Plan](dev-plan-dry-yagni.md)** - Implementation roadmap

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress coding standards
4. Add tests for new functionality
5. Submit pull request

### Development Guidelines
- **DRY**: Don't repeat yourself - centralize common functionality
- **YAGNI**: You aren't gonna need it - avoid premature optimization
- **Security First**: Validate inputs, escape outputs, use WordPress functions
- **WordPress Standards**: Follow WordPress coding standards and best practices

## 🔮 Roadmap

### Phase 1: Core Infrastructure ✅
- Security & authentication
- REST API framework
- File management
- State persistence

### Phase 2: Migration Engine 🚧
- Database export/import
- URL rewriting
- Complete workflow
- Rollback system

### Phase 3: Production Ready 📋
- WP-CLI integration
- Comprehensive testing
- Performance optimization
- User documentation

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built on WordPress plugin development best practices
- Inspired by the need for reliable staging deployments
- Designed for real-world agency and development workflows

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/vidarbrekke/wp-migrate/issues)
- **Discussions**: [GitHub Discussions](https://github.com/vidarbrekke/wp-migrate/discussions)
- **Documentation**: Check the docs folder for detailed guides

---

**Status**: 🚧 Development in Progress  
**Last Updated**: January 2025  
**Version**: 0.1.0-alpha
