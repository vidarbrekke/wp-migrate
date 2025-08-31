# WP‑Migrate Handover

## 1) Completed Work & Outcomes

- Plugin deploys and runs on staging; production site recovered after a config error in `wp-config.php` was fixed (malformed `define('WP_DEBUG', ...)` lines).
- HMAC auth hardened: header name normalization (dash/underscore), path normalization (`/wp-json` + route), nonce replay, TLS checks (incl. proxy headers).
- Added staging‑only diagnostics:
  - `GET/POST /wp-json/migrate/v1/diag/headers` returns received headers and `HTTP_*` view.
  - `GET /wp-json/migrate/v1/diag/settings` returns masked settings (peer URL and shared‑key fingerprint).
- Test script updated to sign requests and accept env keys: `STAGING_SHARED_KEY`, `PRODUCTION_SHARED_KEY`.
- Unified deploy flow in repo (`deploy.sh`) used for staging/production; rsync‑based, idempotent.

## 2) Failures, Open Issues, Lessons Learned

- Root cause of production outage: malformed `wp-config.php` constants (not plugin). Fixed; plugin left deactivated on production.
- Cross‑site auth still blocked until keys/peers are configured identically on both sites. Staging fingerprint observed: `0f625f9ac604` (matches provided key).
- Cloudflare terminates TLS; rely on `is_ssl()` plus `X-Forwarded-Proto` for secure detection.
- Safety: avoid server‑level edits; run deploys from plugin root; quote `define()` keys in `wp-config.php`.

## 3) Files Changed, Key Insights, Gotchas

- `mk-wc-plugin-starter/src/Security/HmacAuth.php`
  - New `get_header_value()` supports both `x-mig-*` and `x_mig_*` forms.
  - `normalize_path()` signs `/wp-json` + REST route.
  - New `get_masked_settings()` exposes peer and key fingerprint for diagnostics.
- `mk-wc-plugin-starter/src/Rest/Api.php`
  - Added staging‑only `diag/headers` and `diag/settings` routes.
- `test-migration-dry-run.sh`
  - Uses env keys, fixes signing payload, corrects endpoints; prints structured results.
- `deploy.sh` (repo root)
  - Rsync‑based unified deploy; expects execution from `mk-wc-plugin-starter/`.

Gotchas:
- PHP/WordPress lowercases headers and may convert dashes to underscores; read both.
- Signed path must be exactly `/wp-json` + route (queries included when present).
- Ensure identical shared key and `peer_url` on both sites; otherwise signature/peer mismatch.
- Run deploy from plugin root; verify activation after sync.

## 4) Key Files and Directories

```
wp-migrate/
├── deploy.sh                         # Unified deploy (staging|production)
├── test-migration-dry-run.sh         # Signed handshake/endpoint tests
└── mk-wc-plugin-starter/
    ├── wp-migrate.php                # Plugin bootstrap
    └── src/
        ├── Plugin.php                # Service registration
        ├── Security/HmacAuth.php     # HMAC verification
        ├── Rest/Api.php              # REST endpoints (+ staging diag)
        ├── Migration/JobManager.php  # 9‑state job manager
        ├── Files/ChunkStore.php      # Chunked uploads
        └── Admin/SettingsPage.php    # Admin options (shared_key, peer_url)
```

### Current Status
- Staging: plugin deployed and active; diagnostics available; key fingerprint: `0f625f9ac604`.
- Production: site healthy; plugin currently deactivated (intentionally). No server‑level changes pending.

### Next Steps
1) In both WordPress admins, set identical values:
   - `shared_key`: use the provided value
   - `peer_url`: reciprocal URLs (`https://motherknitter.com` ↔ `https://staging.motherknitter.com`)
   - Verify via staging `GET /wp-json/migrate/v1/diag/settings` (fingerprints must match test key).
2) Run staging handshake: POST `/wp-json/migrate/v1/handshake` using `test-migration-dry-run.sh` with `STAGING_SHARED_KEY`.
3) When green, deploy and activate to production (`deploy.sh production`), then run E2E (production↔staging) with matching keys.
4) Future work: UI start command, real‑time progress, error recovery.

This brief is intentionally DRY; use the diagnostics endpoints and test script to validate keys and signatures before E2E.
