# WP-Migrate Enterprise User Guide

## 📋 Overview

WP-Migrate is a **production-ready, enterprise-grade** WordPress migration plugin designed for secure, resumable production-to-staging deployments. It provides military-level security, automatic error recovery, real-time monitoring, and comprehensive emergency procedures.

**🚀 Current Version: 1.0.14** - Enterprise Ready with Configuration-Driven Architecture

**Key Features:**
- 🔒 **HMAC-SHA256 Authentication** with TLS enforcement and timestamp validation
- 📦 **Chunked File Uploads** (64MB chunks) with SHA256 validation and resume capability
- 🗄️ **Complete Database Migration** with intelligent URL rewriting and search/replace
- 🔄 **Automatic Error Recovery** with configurable retry logic and exponential backoff
- 📊 **Real-time Progress Monitoring** with live activity logs and retry statistics
- 🛡️ **Enterprise Security** with email/webhook blackholing for staging safety
- 🚨 **Emergency Procedures** with admin UI for immediate stop/rollback operations
- ⚙️ **Configuration-Driven Design** for flexible retry behavior customization

---

## 🚀 Quick Setup (5 Minutes)

### Step 1: Install & Activate Plugin
```bash
# Copy plugin to WordPress
cp -r wp-migrate /path/to/wordpress/wp-content/plugins/

# Activate via WordPress admin or WP-CLI
wp plugin activate wp-migrate
```
*Server Impact: Registers 8 REST endpoints, creates settings page, initializes security services*

### Step 2: Configure Settings
Navigate to **WordPress Admin → Settings → WP-Migrate**

**Required Settings:**
- **Shared Key**: Generate a strong 32+ character secret key
- **Peer URL**: Base URL of the migration target (e.g., `https://staging.example.com`)
- **Enable Plugin**: Check to activate functionality
- **Email Blackhole**: Enable for staging safety (recommended)

**Advanced Configuration:**
- **Retry Settings**: Customize max retries, backoff times, and delays
- **Chunk Size**: Adjust file upload chunk size (default: 64MB)
- **Logging Level**: Set verbosity for debugging and monitoring

*Server Impact: Stores encrypted settings in WordPress options table, validates peer URL format*

### Step 3: Verify Installation
```bash
# Test plugin status
wp plugin status wp-migrate

# Verify REST endpoints
curl -s https://your-site.com/wp-json/migrate/v1/ | jq

# Check version
wp eval "echo 'WP-Migrate Version: ' . WP_MIGRATE_VERSION;"
```
*Server Impact: Confirms plugin registration, validates REST API availability*

---

## 🔧 Migration Workflow

### Phase 1: Handshake (Connection Test)
```bash
curl -X POST https://production.com/wp-json/migrate/v1/handshake \
  -H "X-MIG-Timestamp: $(date +%s)000" \
  -H "X-MIG-Nonce: $(openssl rand -base64 16)" \
  -H "X-MIG-Peer: https://staging.com" \
  -H "X-MIG-Signature: $(calculate_hmac_signature)" \
  -d '{"job_id":"migration-001","capabilities":{"rsync":true}}'
```
*Server Impact: Validates HMAC authentication, runs preflight checks (PHP/MySQL versions, file permissions, disk space), creates job record*

### Phase 2: Database Export
```bash
curl -X POST https://production.com/wp-json/migrate/v1/db/export \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"job_id":"migration-001","artifact":"db_dump.sql.zst"}'
```
*Server Impact: Exports MySQL database with compression, chunks into 64MB files, stores in wp-uploads/wp-migrate-jobs/*

### Phase 3: File Synchronization
```bash
# Upload file chunks
curl -X POST https://staging.com/wp-json/migrate/v1/chunk \
  -H "Authorization: HMAC-SHA256 headers..." \
  -F "job_id=migration-001" \
  -F "artifact=files.tar.zst" \
  -F "index=0" \
  -F "chunk=@chunk_0.dat"
```
*Server Impact: Receives and validates file chunks, reconstructs original files, maintains upload resume capability*

### Phase 4: Database Import
```bash
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"db_import","job_id":"migration-001","params":{"artifact":"db_dump.sql.zst"}}'
```
*Server Impact: Decompresses and imports database, performs URL rewriting, updates site options*

### Phase 5: Finalization
```bash
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"search_replace","job_id":"migration-001","params":{"mode":"hybrid","siteurl":"https://staging.com","from_abs":"https://production.com","to_rel":"/"}}'
```
*Server Impact: Updates all URLs in database, cleans temporary files, completes migration job*

---

## 🆕 Enterprise Features (v1.0.14+)

### Real-Time Migration Monitoring
Access **WordPress Admin → WP-Migrate → Monitor** for live migration dashboard:

**Features:**
- 📊 **Live Progress Bars**: Real-time completion percentages
- 📝 **Activity Logs**: Live streaming of migration events
- 🔄 **Retry Statistics**: Success rates and failure analysis
- ⏱️ **Performance Metrics**: Timing and throughput data
- 🚨 **Error Alerts**: Immediate notification of issues

**Usage:**
```javascript
// AJAX polling for live updates
setInterval(() => {
    jQuery.post(ajaxurl, {
        action: 'wp_migrate_monitor_job',
        job_id: 'migration-001',
        nonce: wp_migrate_monitor.nonce
    }, function(response) {
        updateProgressBars(response.progress);
        updateActivityLogs(response.logs);
        updateRetryStats(response.retry_stats);
    });
}, 5000);
```

### Emergency Procedures UI
**Location**: **WordPress Admin → Settings → WP-Migrate → Emergency Procedures**

**Capabilities:**
- 🛑 **Emergency Stop**: Immediately halt all migration activity
- ↩️ **Rollback Migration**: Revert to pre-migration state
- 📊 **Job Status Overview**: View all active migration jobs
- 🔍 **Error Analysis**: Detailed error information and recovery suggestions

**Emergency Stop Command:**
```bash
# Via AJAX
jQuery.post(ajaxurl, {
    action: 'wp_migrate_emergency_stop',
    job_id: 'migration-001',
    nonce: wp_migrate_emergency.nonce
});

# Via REST API
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"emergency_stop","job_id":"migration-001"}'
```

### Automatic Error Recovery
**Configuration-Driven Retry System** with intelligent error classification:

**Default Configuration:**
```php
// Default retry behavior (can be customized)
$config = new DefaultRetryConfig();
// Max retries: 3
// Base backoff: 30 seconds
// Max backoff: 900 seconds (15 minutes)
```

**Custom Configuration:**
```php
// Custom retry behavior
class CustomRetryConfig implements RetryConfigInterface {
    public function getMaxRetries(): int { return 5; }
    public function getBaseBackoffSeconds(): int { return 60; }
    public function getMaxBackoffSeconds(): int { return 1800; }
}

$errorRecovery = new ErrorRecovery(new CustomRetryConfig());
```

**Recoverable Error Types:**
- ✅ **Network Timeouts**: Connection issues, temporary failures
- ✅ **Database Locks**: Temporary table locks, deadlocks
- ✅ **File System Issues**: Permission problems, disk space
- ✅ **Memory Limits**: PHP memory exhaustion (temporary)
- ❌ **Authentication Failures**: Invalid credentials, expired tokens
- ❌ **Permission Denied**: Insufficient privileges
- ❌ **Invalid Data**: Corrupted files, malformed requests

---

## 🔍 Monitoring & Debugging

### Check Migration Progress
```bash
curl -X GET "https://your-site.com/wp-json/migrate/v1/progress?job_id=migration-001" \
  -H "Authorization: HMAC-SHA256 headers..."
```
*Server Impact: Returns current job state and completion percentage*

### View Migration Logs
```bash
curl -X GET "https://your-site.com/wp-json/migrate/v1/logs/tail?job_id=migration-001&n=100" \
  -H "Authorization: HMAC-SHA256 headers..."
```
*Server Impact: Returns last N log entries in JSON format*

### Monitor Active Jobs
```bash
curl -X GET "https://your-site.com/wp-json/migrate/v1/jobs/active" \
  -H "Authorization: HMAC-SHA256 headers..."
```
*Server Impact: Returns list of all non-terminal migration jobs*

### Real-Time Job Monitoring
```bash
curl -X GET "https://your-site.com/wp-json/migrate/v1/monitor?job_id=migration-001" \
  -H "Authorization: HMAC-SHA256 headers..."
```
*Server Impact: Returns comprehensive job status including progress, logs, and retry statistics*

---

## 🔒 Security Configuration

### Generate Secure Shared Key
```bash
# Generate 64-character key
openssl rand -base64 48
```
*Best Practice: Use different keys for each environment pair*

### TLS Enforcement
- All endpoints require HTTPS
- Self-signed certificates supported
- Proxy headers respected for load balancers

### Authentication Headers
```bash
# Required for all requests
X-MIG-Timestamp: $(date +%s)000  # Current milliseconds
X-MIG-Nonce: $(openssl rand -base64 16)  # Unique per request
X-MIG-Peer: https://target-site.com  # Target base URL
X-MIG-Signature: [HMAC-SHA256 hash]  # Cryptographic signature
```

### Security Best Practices
- 🔑 **Rotate shared keys quarterly**
- 🔒 **Use HTTPS exclusively**
- 📝 **Enable email blackholing on staging**
- ⏰ **Monitor authentication failures**
- 🚨 **Implement rate limiting for production**

---

## 🛠️ Troubleshooting

### Common Issues

**❌ HMAC Authentication Failed**
```bash
# Check timestamp skew (must be within 5 minutes)
date +%s
curl -s https://your-site.com/wp-json/migrate/v1/progress | jq
```
*Server Impact: Validates timestamp against server clock, checks nonce uniqueness*

**❌ File Upload Failed**
```bash
# Verify upload directory permissions
ls -la wp-content/uploads/wp-migrate-jobs/
wp eval "echo WP_UPLOAD_DIR;"
```
*Server Impact: Confirms write permissions, validates chunk integrity*

**❌ Database Connection Error**
```bash
# Test database connectivity
wp db check
wp eval "global \$wpdb; echo \$wpdb->dbhost;"
```
*Server Impact: Validates MySQL connection, checks user permissions*

**❌ Plugin Version Not Updating**
```bash
# Check plugin header version
head -20 wp-content/plugins/wp-migrate/wp-migrate.php | grep "Version:"

# Clear WordPress cache
wp cache flush
wp rewrite flush
```
*Server Impact: Updates plugin information cache, refreshes admin display*

### Recovery Procedures

**Resume Interrupted Migration**
```bash
# Check last completed step
curl -X GET "https://your-site.com/wp-json/migrate/v1/progress?job_id=migration-001"

# Resume from next step
curl -X POST https://your-site.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"resume","job_id":"migration-001"}'
```
*Server Impact: Identifies incomplete chunks, continues from last successful step*

**Emergency Recovery from Errors**
```bash
# Check error recovery status
curl -X GET "https://your-site.com/wp-json/migrate/v1/monitor?job_id=migration-001"

# Force retry of failed operation
curl -X POST https://your-site.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"retry","job_id":"migration-001","operation":"db_import"}'
```

---

## 📊 API Reference

### Endpoints Overview

| Endpoint | Method | Purpose | Authentication |
|----------|--------|---------|----------------|
| `/handshake` | POST | Connection test & preflight | Required |
| `/db/export` | POST | Database export | Required |
| `/chunk` | POST/GET | File upload/download | Required |
| `/command` | POST | Migration actions | Required |
| `/progress` | GET | Status monitoring | Required |
| `/logs/tail` | GET | Log streaming | Required |
| `/monitor` | GET | Real-time monitoring | Required |
| `/jobs/active` | GET | Active job listing | Required |

### Command Actions

- `db_import`: Import database dump with retry logic
- `search_replace`: Update URLs in database with retry logic
- `cleanup`: Remove temporary files
- `rollback`: Revert to previous state
- `emergency_stop`: Halt all migration activity
- `retry`: Force retry of failed operation

### New Monitoring Endpoints

**`/monitor` - Real-Time Job Status**
```json
{
  "job_id": "migration-001",
  "status": "running",
  "progress": 75,
  "current_operation": "search_replace",
  "recent_logs": [...],
  "retry_stats": {
    "total_retries": 2,
    "successful_retries": 1,
    "failed_retries": 1,
    "last_retry": "2025-08-30T16:45:00Z"
  },
  "server_time": "2025-08-30T16:45:30Z"
}
```

**`/jobs/active` - Active Job Listing**
```json
[
  {
    "job_id": "migration-001",
    "status": "running",
    "progress": 75,
    "created": "2025-08-30T16:00:00Z",
    "last_activity": "2025-08-30T16:45:00Z"
  }
]
```

---

## 🎯 Best Practices

### Security
- 🔑 **Rotate shared keys quarterly**
- 🔒 **Use HTTPS exclusively**
- 📝 **Enable email blackholing on staging**
- ⏰ **Monitor authentication failures**
- 🚨 **Implement rate limiting for production**
- 🔐 **Use strong, unique keys per environment**

### Performance
- 📦 **Use compression for large databases**
- 🔄 **Enable resumable uploads for big sites**
- 📊 **Monitor server resources during migration**
- 🕐 **Schedule migrations during low-traffic periods**
- ⚙️ **Optimize retry configuration for your environment**
- 🎯 **Use appropriate chunk sizes for your network**

### Configuration Management
- 📋 **Document custom retry configurations**
- 🔧 **Test configurations in staging first**
- 📊 **Monitor retry success rates**
- ⚡ **Adjust backoff times based on error patterns**
- 🎛️ **Use environment-specific configurations**

### Maintenance
- 🗂️ **Regularly clean migration logs**
- 💾 **Monitor disk space for large sites**
- 🔍 **Test migrations on smaller sites first**
- 📋 **Document custom configurations**
- 🔄 **Update retry configurations based on performance data**
- 📈 **Track migration success rates over time**

---

## 🚨 Emergency Procedures

### Rollback Migration
```bash
# Immediate rollback to pre-migration state
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"rollback","job_id":"migration-001"}'
```
*Server Impact: Restores database backup, removes migrated files*

### Emergency Stop
```bash
# Halt all migration activity
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"emergency_stop","job_id":"migration-001"}'

# Via WordPress admin
# Settings → WP-Migrate → Emergency Procedures → Stop Migration
```
*Server Impact: Prevents further API operations for specified job*

### Emergency Recovery
```bash
# Check system status after emergency stop
curl -X GET "https://staging.com/wp-json/migrate/v1/monitor?job_id=migration-001"

# Resume from safe point
curl -X POST https://staging.com/wp-json/migrate/v1/command \
  -H "Authorization: HMAC-SHA256 headers..." \
  -d '{"action":"resume","job_id":"migration-001"}'
```

---

## 📞 Support & Diagnostics

### Diagnostic Information
```bash
# Generate comprehensive system report
wp eval "
echo 'WordPress Version: ' . get_bloginfo('version') . PHP_EOL;
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'MySQL Version: ' . \$wpdb->db_version() . PHP_EOL;
echo 'WP-Migrate Version: ' . WP_MIGRATE_VERSION . PHP_EOL;
echo 'WP-Migrate Status: ' . (is_plugin_active('wp-migrate/wp-migrate.php') ? 'Active' : 'Inactive') . PHP_EOL;
echo 'Plugin Path: ' . WP_MIGRATE_DIR . PHP_EOL;
echo 'Upload Directory: ' . WP_UPLOAD_DIR . PHP_EOL;
"
```

### Log Locations
- **Plugin Logs**: `wp-content/uploads/wp-migrate-jobs/{job_id}/logs/`
- **WordPress Logs**: `wp-content/debug.log`
- **Server Logs**: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- **Migration State**: `wp-content/uploads/wp-migrate-jobs/{job_id}/state.json`

### Performance Monitoring
```bash
# Check retry statistics
curl -X GET "https://your-site.com/wp-json/migrate/v1/monitor?job_id=migration-001" | jq '.retry_stats'

# Monitor system resources during migration
wp eval "
echo 'Memory Usage: ' . memory_get_usage(true) . ' bytes' . PHP_EOL;
echo 'Peak Memory: ' . memory_get_peak_usage(true) . ' bytes' . PHP_EOL;
echo 'Disk Free Space: ' . disk_free_space(WP_CONTENT_DIR) . ' bytes' . PHP_EOL;
"
```

---

## 🔧 Advanced Configuration

### Custom Retry Configuration
```php
// Create custom retry configuration
class ProductionRetryConfig implements RetryConfigInterface {
    public function getMaxRetries(): int { return 5; }
    public function getBaseBackoffSeconds(): int { return 60; }
    public function getMaxBackoffSeconds(): int { return 3600; }
}

// Apply to ErrorRecovery
$errorRecovery = new ErrorRecovery(new ProductionRetryConfig());
```

### WordPress Filter Integration
```php
// Customize retry configuration via WordPress filters
add_filter('wp_migrate_retry_config', function($config) {
    // Modify configuration based on environment
    if (defined('WP_ENV') && WP_ENV === 'production') {
        return new ProductionRetryConfig();
    }
    return $config;
});
```

### Environment-Specific Settings
```php
// wp-config.php or environment-specific configuration
define('WP_MIGRATE_MAX_RETRIES', 5);
define('WP_MIGRATE_BASE_BACKOFF', 60);
define('WP_MIGRATE_MAX_BACKOFF', 3600);

// Apply in plugin initialization
add_action('wp_migrate_init', function() {
    $config = new CustomRetryConfig(
        WP_MIGRATE_MAX_RETRIES,
        WP_MIGRATE_BASE_BACKOFF,
        WP_MIGRATE_MAX_BACKOFF
    );
    // Apply configuration
});
```

---

## 🎉 You're Ready!

**WP-Migrate v1.0.14** provides **enterprise-grade migration capabilities** with:

- ✅ **Military-Level Security**: HMAC-SHA256 authentication with TLS enforcement
- ✅ **Automatic Recovery**: Intelligent error handling with configurable retry logic
- ✅ **Real-Time Monitoring**: Live progress updates and emergency controls
- ✅ **Production Reliability**: Self-healing deployment and comprehensive validation
- ✅ **Configuration Flexibility**: Interface-based design for custom retry behavior
- ✅ **WordPress Integration**: Seamless integration with WordPress 6.2+ standards

**🚀 Ready for production deployment with confidence!**

Follow this guide for reliable, resumable WordPress migrations that scale from small sites to enterprise deployments.

---

## 📚 Additional Resources

- **Architecture Documentation**: See `ARCHITECTURE.md` for technical details
- **Testing Guide**: See `TESTING_SUMMARY.md` for test procedures
- **Implementation Status**: See `IMPLEMENTATION_STATUS.md` for feature status
- **Staging Deployment**: See `STAGING_DEPLOYMENT.md` for deployment procedures
- **Development Plan**: See `dev-plan-dry-yagni.md` for future enhancements

**🎯 For technical support or feature requests, refer to the project documentation or contact the development team.**
