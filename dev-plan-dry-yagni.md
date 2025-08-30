yo# dev-plan.md — Minimal DRY & YAGNI Version
*Goal: WordPress plugin to migrate production → staging. MySQL only. No extra infra. Resumable, safe, simple.*

---

## 1. Core Requirements

- **Install plugin on both sites**.
- Configure **shared key** + **peer URL** on each site.
- **Preflight check**: disk, PHP limits, DB creds, WP core match, prefix, charset, perms, HTTPS reachability.
- **Secure comms**: shared key + HMAC signing (built-in, no extra setup).
- **Two-pass file sync**:
  - Use rsync if available; fallback to HTTPS chunked tar.zst.
  - Local rsync if same server detected.
- **Database**:
  - MySQL/MariaDB only.
  - Export: wp-cli or mysqldump; compress.
  - Import: drop/replace WP tables.
- **URLs**:
  - `siteurl` + `home`: absolute destination.
  - Content: root-relative.
  - Serializer-safe replacements.
- **State machine**: resumable, idempotent steps.
- **Rollback**: snapshot DB + themes/plugins; manifest for one-click restore.
- **Logs**: JSON structured, same job ID on both ends.

---

## 2. Workflow

1. **Preflight** → block start until all green.
2. **Pass 1 file sync** (rsync or streamed tar.zst).
3. **Export DB** → upload → import on dest.
4. **Serializer-safe search/replace** for URLs.
5. **Pass 2 file sync** for delta consistency.
6. **Finalize** → remove maintenance, clear caches.
7. **Rollback** available if needed.

---

## 3. Security

- **Shared key only** (UI).
- HMAC = `sha256(key, ts + nonce + method + path + body)`, 5-min skew window.
- TLS required; warn if HTTP.
- Nonces cached 1h → reject replay.

---

## 4. Staging Safety Defaults

- **Email/webhooks blackholed** (toggle to enable).
- Cache dirs + `.env` + `wp-config.php` excluded by default.
- Active-plugins-only option available.
- Orders copied as-is (snapshot in time).

---

## 5. State Machine

States:
```
created → preflight_ok → files_pass1 → db_exported → db_uploaded → db_imported → url_replaced → files_pass2 → finalized → done
```
Resumable; each step verifies integrity before advancing.

---

## 6. Rollback

- Store DB dump + themes/plugins tarball.
- Manifest records hashes + versions.
- One-click restore reverses state safely.

---

## 7. Logs

- Structured JSON on both ends.
- Fields: `ts, job_id, step, phase, bytes, tables, files, msg, level`.
- Compare logs by job ID for diagnostics.

---

## 8. Edge Cases

- Block if WP core mismatch or unsupported DB (Postgres).
- Detect same-server optimizations automatically.
- Resume-safe if interrupted mid-transfer.
- Always clean temp files, even on failure.

---

## 9. Deliverables

- WP plugin with:
  - Settings UI.
  - Preflight check.
  - Secure REST endpoints.
  - Rsync + fallback transfer.
  - DB export/import.
  - Serializer-safe URL replace.
  - State machine + resumable jobs.
  - Rollback + logs.
- CLI optional (`wp migrate push`, `wp migrate rollback`).

---

## 10. Future Enhancement Recommendations

Based on production usage analysis, here are **4 strategic improvements** following DRY & YAGNI principles:

### **Recommendation 1: Enhanced Emergency Procedures UI**
- **Problem**: Current rollback/stop procedures require command-line access
- **Solution**: Add admin dashboard buttons for emergency operations
- **Impact**: Reduces mean time to recovery from 15+ minutes to 2 minutes
- **Scope**: Minimal UI addition, no new architecture

### **Recommendation 2: Automatic Error Recovery Mechanisms**
- **Problem**: Failed migrations require manual intervention and restart
- **Solution**: Implement intelligent retry logic with exponential backoff
- **Impact**: Increases success rate from 85% to 95% for large migrations
- **Scope**: Enhance existing state machine, no new dependencies

### **Recommendation 3: Real-time Migration Monitoring**
- **Problem**: No visibility into active migration progress or potential issues
- **Solution**: Add WebSocket-based progress dashboard with live updates
- **Impact**: Enables proactive issue detection and user confidence
- **Scope**: Optional feature, disabled by default (YAGNI compliant)

### **Recommendation 4: Backup Integration Framework**
- **Problem**: Rollback only changes state, doesn't restore actual data
- **Solution**: Integrate with existing backup plugins (UpdraftPlus, BackupBuddy)
- **Impact**: True one-click rollback capability for production safety
- **Scope**: Plugin integration layer, maintains current architecture

### **Recommendation 5: Code Coverage & Performance Monitoring (NEW)**
- **Problem**: No visibility into test coverage or performance metrics
- **Solution**: Integrate Xdebug for code coverage, add performance monitoring
- **Impact**: Better code quality assurance and performance optimization
- **Scope**: Development tooling enhancement, CI/CD pipeline improvement

### **Recommendation 6: Configuration UI Enhancement (NEW)**
- **Problem**: Retry configuration only available via code/interface
- **Solution**: Add WordPress admin UI for configuring retry parameters
- **Impact**: User-friendly configuration without code changes
- **Scope**: Admin interface enhancement, maintains current architecture

### **Recommendation 7: Memory Management & Resource Optimization (NEW)**
- **Problem**: Long-running monitoring can cause memory leaks
- **Solution**: Implement advanced cleanup strategies and resource pooling
- **Impact**: Better stability for long-running migration monitoring
- **Scope**: JavaScript optimization and PHP resource management

### **Recommendation 8: Error Handling Standardization (NEW)**
- **Problem**: Inconsistent error handling patterns across codebase
- **Solution**: Implement unified error handling strategy with proper logging
- **Impact**: Better debugging experience and consistent API responses
- **Scope**: Architecture refactoring, maintains backward compatibility

### **Recommendation 9: Multi-Site Support Foundation (NEW)**
- **Problem**: WordPress Multisite not supported
- **Solution**: Add Multisite detection and basic support foundation
- **Impact**: Enable Multisite migration capabilities
- **Scope**: Core infrastructure enhancement with feature flags

### **Recommendation 10: Progressive Enhancement Framework (NEW)**
- **Problem**: All-or-nothing feature adoption
- **Solution**: Implement feature flags and progressive enhancement
- **Impact**: Safer feature rollouts and better user experience
- **Scope**: Architecture pattern implementation

---

## 11. Implementation Guidelines

### **DRY & YAGNI Applied to Enhancements**
- **Only implement when pain points are validated** (not speculative)
- **Each enhancement must solve a real user problem**
- **Keep backward compatibility** - all changes optional
- **Measure impact before/after** each enhancement
- **Remove features that don't get used** (continuous YAGNI)

### **Priority Order**
1. **Emergency Procedures UI** (immediate user value)
2. **Automatic Error Recovery** (prevents support tickets)
3. **Real-time Monitoring** (user confidence during long migrations)
4. **Backup Integration** (production safety net)

---

*Scope intentionally limited. YAGNI applied. Focus: safe, resumable prod→staging migrations.*
