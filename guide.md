# WP-Migrate User Guide

## 📋 Overview

WP-Migrate is a secure, resumable WordPress migration plugin designed for production-to-staging deployments. It provides enterprise-grade migration capabilities with robust security, automatic recovery, and comprehensive error handling.

**Key Features:**
- 🔒 HMAC-SHA256 authentication with TLS enforcement
- 📦 Chunked file uploads (64MB chunks) with resume capability
- 🗄️ Complete database migration with URL rewriting
- 🔄 Automatic retry and recovery mechanisms
- 📊 Real-time progress monitoring and logging
- 🛡️ Email/webhook blackholing for staging safety

---

## 🚀 Quick Setup (5 Minutes)

### Step 1: Install & Activate Plugin
```bash
# Copy plugin to WordPress
cp -r wp-migrate /path/to/wordpress/wp-content/plugins/

# Activate via WordPress admin or WP-CLI
wp plugin activate wp-migrate
```
*Server Impact: Registers 6 REST endpoints, creates settings page, initializes security services*

### Step 2: Configure Settings
Navigate to **WordPress Admin → Settings → WP-Migrate**

**Required Settings:**
- **Shared Key**: Generate a strong 32+ character secret key
- **Peer URL**: Base URL of the migration target (e.g., `https://staging.example.com`)
- **Enable Plugin**: Check to activate functionality
- **Email Blackhole**: Enable for staging safety (recommended)

*Server Impact: Stores encrypted settings in WordPress options table, validates peer URL format*

### Step 3: Verify Installation
```bash
# Test plugin status
wp plugin status wp-migrate

# Verify REST endpoints
curl -s https://your-site.com/wp-json/migrate/v1/ | jq
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

### Command Actions

- `db_import`: Import database dump
- `search_replace`: Update URLs in database
- `cleanup`: Remove temporary files
- `rollback`: Revert to previous state

---

## 🎯 Best Practices

### Security
- 🔑 Rotate shared keys quarterly
- 🔒 Use HTTPS exclusively
- 📝 Enable email blackholing on staging
- ⏰ Monitor authentication failures

### Performance
- 📦 Use compression for large databases
- 🔄 Enable resumable uploads for big sites
- 📊 Monitor server resources during migration
- 🕐 Schedule migrations during low-traffic periods

### Maintenance
- 🗂️ Regularly clean migration logs
- 💾 Monitor disk space for large sites
- 🔍 Test migrations on smaller sites first
- 📋 Document custom configurations

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
wp option update wp_migrate_job_migration-001 '{"status":"cancelled"}'
```
*Server Impact: Prevents further API operations for specified job*

---

## 📞 Support

### Diagnostic Information
```bash
# Generate system report
wp eval "
echo 'WordPress Version: ' . get_bloginfo('version');
echo 'PHP Version: ' . PHP_VERSION;
echo 'MySQL Version: ' . \$wpdb->db_version();
echo 'WP-Migrate Status: ' . (is_plugin_active('wp-migrate/wp-migrate.php') ? 'Active' : 'Inactive');
"
```

### Log Locations
- **Plugin Logs**: `wp-content/uploads/wp-migrate-jobs/{job_id}/logs/`
- **WordPress Logs**: `wp-content/debug.log`
- **Server Logs**: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`

---

**🎉 You're Ready!** WP-Migrate provides enterprise-grade migration capabilities with military-level security. Follow this guide for reliable, resumable WordPress migrations.
