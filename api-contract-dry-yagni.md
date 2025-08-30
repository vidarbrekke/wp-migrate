# api-contract.md — DRY & YAGNI Migration API
*Purpose: minimal REST contract between two WP sites for Prod→Staging migrations. Auth = shared key + HMAC. Resumable by design.*

---

## 0) Base
- **Base URL**: `https://<site>/wp-json/migrate/v1`
- **Content-Type**: `application/json` (unless noted)
- **Auth headers (all endpoints)**:
  - `X-MIG-Timestamp`: unix ms
  - `X-MIG-Nonce`: random 16 bytes (base64)
  - `X-MIG-Peer`: expected peer base URL
  - `X-MIG-Signature`: `base64( HMAC-SHA256( key, ts + "\n" + nonce + "\n" + METHOD + "\n" + PATH + "\n" + sha256(body) ) )`
- **HTTP**: TLS required. Reject if ts skew > 5m or nonce replay.

---

## 1) Endpoints (Minimal Set)

### 1.1 `POST /handshake`
Verify connectivity + roles.
**Req**
```json
{ "job_id": "optional", "from": "https://prod.example.com", "to": "https://stg.example.com", "capabilities": { "rsync": true, "wp_cli": true } }
```
**Res 200**
```json
{ "ok": true, "site": { "url": "https://stg.example.com", "wp": "6.6.2", "db": "mysql", "prefix": "wp_", "charset": "utf8mb4" }, "capabilities": { "rsync": true, "zstd": true, "wp_cli": true } }
```

### 1.2 `POST /command`
Generic RPC to run idempotent step.
**Req**
```json
{ "job_id":"2025-08-29-7f3e","action":"prepare","params":{"mode":"reset","email_blackhole":true} }
```
**Actions (enum)**: `prepare`, `db_import`, `search_replace`, `finalize`, `rollback`, `cleanup`, `health`  
- `prepare` must create temp dirs, maintenance flag (optional), snapshot for rollback, and persist job record.
- `db_import` imports a previously uploaded dump (see /chunk).
- `search_replace` runs serializer-safe URL rewrites.
- `finalize` removes maintenance, purges temps, marks done.
- `rollback` restores from manifest.
- `health` returns quick status.

**Res 200**
```json
{ "ok":true, "state":"db_imported", "notes":["dropped 36 tables","imported 36 tables"] }
```
**Res 409 (conflict/in-progress)**
```json
{ "ok":false, "retry_in_ms":2000, "state":"files_pass1" }
```

### 1.3 `POST /chunk`
Upload an artifact in chunks (resumable).
**Headers**: `Content-Type: application/octet-stream`
**Query**: `?job_id=...&artifact=db_dump.sql.zst&index=0&total=24&sha256=<chunk_b64>`  
**Body**: raw bytes
**Res 200**
```json
{ "ok":true, "received": {"index":0} }
```
**`GET /chunk`** (probe resume)
`?job_id=...&artifact=...` →
```json
{ "present":[0,1,2,5], "next":3 }
```

### 1.4 `GET /progress?job_id=...`
Returns current step + coarse metrics.
**Res 200**
```json
{ "job_id":"2025-08-29-7f3e","state":"files_pass2","steps":[
  {"name":"files_pass1","ok":true,"bytes":123456789},
  {"name":"db_import","ok":true,"tables":36},
  {"name":"search_replace","ok":true,"replacements":5421},
  {"name":"files_pass2","ok":false,"bytes":345678}
]}
```

### 1.5 `GET /logs/tail?job_id=...&n=200`
Returns last N JSONL lines (redacted).  
**Res 200**
```json
{ "lines":[ "{"ts":"...","step":"db_import","level":"info","msg":"table wp_posts imported"}" ] }
```

---

## 2) Job Lifecycle (States)
`created → preflight_ok → files_pass1 → db_exported → db_uploaded → db_imported → url_replaced → files_pass2 → finalized → done`  
Rules:
- All actions **idempotent**; replays re-check completed work.
- Server persists job record under `mig_job_<job_id>`.
- `/command finalize` must be **safe to re-run**.

---

## 3) Errors (Uniform)
**HTTP**: 4xx client, 5xx server.  
**Body**
```json
{ "ok": false, "code":"EPREFIX_MISMATCH", "message":"Table prefix differs", "hint":"Enable prefix rewrite", "state":"preflight_ok" }
```

Common codes: `EAUTH`, `ETS_SKEW`, `ENONCE_REPLAY`, `EPREFIX_MISMATCH`, `ECORE_MISMATCH`, `ERSYNC_UNAVAILABLE`, `EDISK_LOW`, `EDB_PRIVS`, `EIMPORT_FAIL`.

---

## 4) Minimal Payloads

### 4.1 `search_replace` params
```json
{ "mode":"hybrid", "siteurl":"https://stg.example.com", "from_abs":"https://prod.example.com", "to_rel":"/" }
```

### 4.2 `prepare` params
```json
{ "mode":"reset", "email_blackhole":true, "select_plugins":"active_only" }
```

---

## 5) Security Notes
- Shared key only in settings; never in URLs or logs.  
- Nonces kept for 1h LRU; reject replay.  
- Require TLS; if HTTP, return 426 Upgrade Required.  
- Signature covers body hash; for `GET`/no-body use empty hash.

---

## 6) YAGNI Omissions
- No multi-endpoint per step; one `/command`.  
- No per-file API (rsync or tar stream handles files).  
- No complex diff/merge; table replace only.

---

## 7) Acceptance Checklist
- All endpoints implemented with auth + idempotency.  
- Chunk upload resumes and completes large artifacts.  
- `/command finalize` always leaves site consistent.  
- Errors return uniform objects with actionable hints.
