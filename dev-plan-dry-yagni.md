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

*Scope intentionally limited. YAGNI applied. Focus: safe, resumable prod→staging migrations.*
