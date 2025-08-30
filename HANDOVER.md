# WP-Migrate: Handover Document

## 🎯 **Project Purpose**

**WP-Migrate** is a WordPress plugin designed to solve a specific, real-world problem: **secure, resumable migrations from production WordPress sites to staging environments**.

### **What It Does**
- **Automates** the complex process of copying a live WordPress site to staging
- **Secures** all communications with HMAC authentication and TLS enforcement
- **Resumes** interrupted migrations automatically (no lost progress)
- **Protects** staging environments with email/webhook blackholing
- **Rolls back** safely if something goes wrong

### **Why It Exists**
WordPress agencies and developers need staging environments that are:
- **Identical** to production (files + database)
- **Safe** to test on (no accidental emails to customers)
- **Fast** to set up (automated vs. manual)
- **Reliable** (resumable, with rollback)

## 👥 **Target Audience**

### **Primary Users**
1. **WordPress Agencies** - Migrate client sites to staging for testing
2. **Developers** - Deploy updates safely with rollback capability
3. **DevOps Engineers** - Automate WordPress deployments in CI/CD pipelines
4. **Site Owners** - Maintain synchronized staging environments

### **Technical Requirements**
- **WordPress 6.2+** on both source and destination
- **MySQL/MariaDB** (PostgreSQL not supported)
- **PHP 7.4+** with standard WordPress extensions
- **HTTPS** required (TLS enforcement)

## 🏗️ **Current Architecture**

### **Core Services**
```
src/
├── Security/       # HmacAuth - HMAC verification, TLS validation
├── Rest/           # Api - REST endpoints (/handshake, /command, /chunk, etc.)
├── Files/          # ChunkStore - Resumable file uploads (64MB chunks)
├── State/          # StateStore & JobManager - Job lifecycle management
├── Logging/        # JsonLogger - Structured logging with redaction
├── Preflight/      # Checker - System capability validation
├── Admin/          # SettingsPage - Configuration UI
└── Contracts/      # Registrable interface
```

### **Key Design Principles**
- **DRY**: Single `HmacAuth` class handles all authentication
- **YAGNI**: No external dependencies, minimal WordPress functions
- **Security First**: HMAC signing, TLS enforcement, nonce protection
- **Service-Oriented**: Each feature in its own class implementing `Registrable`

## 📊 **Implementation Status**

### ✅ **Complete (65%)**
- **Security Infrastructure**: HMAC auth, TLS validation, nonce protection
- **REST API**: All endpoints implemented with authentication wrapper
- **File Management**: Chunked uploads with SHA256 validation and resume
- **State Management**: Job lifecycle with WordPress options storage
- **Preflight System**: System capability detection (rsync, zstd, wp-cli)
- **Settings UI**: Shared key, peer URL, safety toggles

### 🚧 **In Progress**
- **Command Actions**: `health` and `prepare` implemented, others pending
- **State Machine**: Basic workflow, needs full implementation

### 📋 **Next Priority**
1. **Database Engine**: MySQL export/import with URL rewriting
2. **Migration Workflow**: Complete end-to-end process
3. **Rollback System**: Automated restoration from snapshots
4. **WP-CLI Integration**: Command-line interface

## 🔐 **Security Features**

### **Authentication**
- **Shared Key**: Configured in WordPress admin, never logged
- **HMAC-SHA256**: All requests cryptographically signed
- **Nonce Protection**: 1-hour TTL prevents replay attacks
- **TLS Enforcement**: HTTPS required (with proxy header support)

### **File Security**
- **Path Validation**: Directory traversal protection
- **Size Limits**: 64MB chunk size prevents memory exhaustion
- **Hash Verification**: SHA256 validation for all uploaded chunks
- **Secure Storage**: Files in `wp-uploads/mk-migrate-jobs/`

## 🚀 **Migration Workflow**

### **Current Flow**
1. **Handshake** → Verify connectivity & run preflight checks
2. **Prepare** → Set job state & configuration
3. **File Sync** → Chunked uploads with resume capability
4. **Database** → Export/import with URL rewriting *(pending)*
5. **Finalize** → Cleanup & activation *(pending)*

### **State Machine**
```
created → preflight_ok → files_pass1 → db_exported → 
db_uploaded → db_imported → url_replaced → files_pass2 → 
finalized → done
```

## 🛠️ **Development Environment**

### **Setup**
```bash
git clone https://github.com/vidarbrekke/wp-migrate.git
cd wp-migrate/mk-wc-plugin-starter
composer install
```

### **WordPress Integration**
1. Copy `mk-wc-plugin-starter` to `wp-content/plugins/`
2. Activate **WP-Migrate: Production → Staging Migration**
3. Configure shared key and peer URL in **Settings → MK WC Starter**

### **Testing**
- **Current**: No automated tests (planned for next phase)
- **Manual**: Use REST endpoints with proper authentication
- **Validation**: Check WordPress admin for settings and logs

## 📚 **Key Documentation**

### **Specifications**
- **`dev-plan-dry-yagni.md`**: Implementation roadmap and requirements
- **`api-contract-dry-yagni.md`**: REST API endpoint specifications
- **`mk-wc-plugin-starter/ARCHITECTURE.md`**: Technical design decisions
- **`mk-wc-plugin-starter/IMPLEMENTATION_STATUS.md`**: Current progress tracking

### **Code Structure**
- **`mk-wc-plugin-starter/src/Plugin.php`**: Main plugin bootstrap and service registration
- **`mk-wc-plugin-starter/src/Security/HmacAuth.php`**: Authentication and security logic
- **`mk-wc-plugin-starter/src/Rest/Api.php`**: REST endpoint definitions
- **`mk-wc-plugin-starter/src/Admin/SettingsPage.php`**: Configuration management

## 🎯 **Immediate Next Steps**

### **Week 1: Complete Core Commands**
1. **Implement remaining `/command` actions**:
   - `db_import`: Import uploaded database dumps
   - `search_replace`: URL rewriting for staging
   - `finalize`: Complete migration and cleanup
   - `rollback`: Restore from snapshots

2. **Enhance state machine**:
   - Validate state transitions
   - Add rollback support
   - Implement job cleanup

### **Week 2: Database Engine**
1. **MySQL export/import**:
   - Integrate with wp-cli or mysqldump
   - Handle large database files
   - Implement URL rewriting logic

2. **Error handling**:
   - Add contextual error information
   - Implement retry mechanisms
   - Enhance logging for debugging

### **Week 3: Testing & Validation**
1. **Create test suite**:
   - Unit tests for individual services
   - Integration tests for API endpoints
   - Security tests for authentication

2. **End-to-end testing**:
   - Complete migration workflow
   - Rollback functionality
   - Error scenarios

## ⚠️ **Important Notes**

### **What NOT to Change**
- **Security model**: HMAC authentication is working and secure
- **File handling**: Chunked uploads with resume are solid
- **State management**: WordPress options approach is appropriate
- **Architecture**: Service-oriented design is clean and maintainable

### **What to Watch For**
- **WordPress function calls**: Some linter warnings about global functions (these are false positives)
- **Dependency injection**: Settings provider pattern in `Plugin.php`
- **Error handling**: Consistent error response format across all endpoints
- **File permissions**: Ensure upload directories are writable

### **Common Gotchas**
1. **Authentication**: All REST endpoints require proper HMAC headers
2. **TLS**: HTTPS is enforced, even in development (use local SSL or disable temporarily)
3. **File paths**: Chunks stored in `wp-uploads/mk-migrate-jobs/`
4. **State persistence**: Jobs stored as WordPress options with `autoload=false`

## 🔮 **Future Considerations**

### **Phase 2 Features**
- **Multi-site support**: Network-wide migrations
- **Advanced rollback**: Incremental restoration options
- **Performance monitoring**: Migration metrics and optimization
- **Plugin API**: Third-party integration capabilities

### **Scalability**
- **Horizontal scaling**: Stateless design supports multiple instances
- **Batch operations**: Multiple site migrations
- **Async processing**: Background job processing for large sites

## 📞 **Getting Help**

### **Code Issues**
- Check `mk-wc-plugin-starter/IMPLEMENTATION_STATUS.md` for current status
- Review `mk-wc-plugin-starter/ARCHITECTURE.md` for design decisions
- Examine existing service implementations for patterns

### **API Questions**
- Reference `api-contract-dry-yagni.md` for endpoint specifications
- Check `dev-plan-dry-yagni.md` for implementation requirements
- Use existing endpoints as examples

### **WordPress Integration**
- Follow WordPress coding standards
- Use WordPress functions when available
- Implement proper capability checks
- Sanitize inputs and escape outputs

---

**Remember**: This is a focused, production-ready migration tool. Stick to DRY & YAGNI principles. Don't add features that aren't explicitly needed. The goal is reliable, secure migrations, not a general-purpose WordPress toolkit.

**Good luck!** 🚀
