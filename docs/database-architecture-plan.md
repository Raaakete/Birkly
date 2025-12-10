# Database Architecture Plan (Optional DB Backend)

## Table of Contents
1. Goals & Principles
2. User Experience (Settings Flow)
3. Architecture: Pluggable Storage
4. Supported Drivers (initial + optional)
5. Data Model & Mapping
6. Migration & Rollback
7. Operations & Health
8. Performance & Indexing
9. Security & Permissions
10. Testing & Rollout

---

## 1) Goals & Principles
- Flat-file remains default; DB is optional.
- Toggle in Settings to switch to DB; must preserve behavior parity.
- Pluggable storage drivers behind a single storage interface.
- Safe migrations with rollback; no data loss.
- Keep simple for users; powerful enough for larger deployments.

---

## 2) User Experience (Settings Flow)
- **Settings → Storage**:
  - Toggle: “Use database instead of flat files”.
  - Driver select: Postgres (incl. Supabase), MySQL/MariaDB, SQLite; optional: MongoDB; custom/experimental DSN.
  - Connection form (host/port/DB/user/pass or DSN; TLS options).
  - Buttons: “Health check”, “Run migration (flat-file → DB)”, “Rollback to flat-file”.
  - Status: current backend, last health check, last migration snapshot.

---

## 3) Architecture: Pluggable Storage
- **Storage interface**: read/write/list entries, collections, relations; queries with filters/pagination; transactions (when supported); metadata.
- **Drivers**: `FlatFileStorage` (existing), `SqlStorage` (Postgres/MySQL/MariaDB/SQLite), optional `DocumentStorage` (MongoDB).
- **Driver registry**: core ships official drivers; allow community/custom drivers via manifest.
- **Injection**: all content access goes through storage; no direct file/DB calls outside drivers.

---

## 4) Supported Drivers
- **Recommended first-class**:
  - PostgreSQL (incl. Supabase) – JSONB, strong indexing.
  - MySQL/MariaDB – widely hosted.
  - SQLite – simplest local/on-prem.
- **Optional**:
  - MongoDB (document store; different query model).
  - Custom DSN (any PDO-supported), marked experimental/unsupported.
- **Deprioritized for now**: Firebase/Firestore (different consistency/query model; vendor lock-in) unless user demand; treat as community driver.

---

## 5) Data Model & Mapping
- Collections → tables; system fields as columns (`id`, `slug`, `status`, timestamps).
- Flexible fields → JSON/JSONB column to reduce schema churn; index selected paths as needed.
- Relations/sub-entries → foreign keys or link tables; mirror parent_id/collection from flat-file.
- Media/binary → remain on disk; DB stores references/metadata.
- Slug uniqueness: enforce at DB; emulate on flat-file.
- Timestamps in UTC; consistent format across backends.

---

## 6) Migration & Rollback
- Tools: export/import flat-file ↔ DB; dry-run with validation (slugs, required fields).
- Snapshots: take backup before migration; keep rollback path to flat-file.
- Incremental option: batch import with integrity checks.
- Schema migrations: SQL migrations for DB; for flexible fields, prefer JSON storage to avoid frequent DDL.
- Rollback: one-click restore using last snapshot; keep previous DB/flat-file state.

---

## 7) Operations & Health
- Health checks: connectivity, migrations applied, basic CRUD test, schema drift detection.
- Config: backend selector, DSN/credentials, pool size, timeouts, SSL/TLS.
- Backups: guidance for DB backups + media; suggest snapshot before backend switch.
- Observability: log driver errors, migration results, health check status.

---

## 8) Performance & Indexing
- Index common fields: `id`, `slug`, `status`, `collection`, timestamps; selective JSON path indexes for hot filters.
- Pagination defaults to avoid heavy scans.
- Caching: reuse existing cache; optionally cache query results with invalidation on writes.
- Connection pooling (where applicable); sane query timeouts.

---

## 9) Security & Permissions
- Least-privilege DB user; TLS.
- Secrets: do not log; store securely.
- Validation/capabilities stay above storage; drivers only execute authorized ops.
- Rate limiting for public submission paths unchanged; must behave identically on DB.

---

## 10) Testing & Rollout
- Test matrix: flat-file (default) and each official driver (start with SQLite + Postgres; then MySQL/MariaDB).
- Parity tests: drafts/status, slugs, validation, permissions, public submissions, relations/sub-entries.
- Performance sanity checks on sample large datasets.
- Rollout steps:
  1) Define storage interface; refactor callers to use it (still flat-file).
  2) Add SQLite + Postgres drivers; optional MySQL/MariaDB.
  3) Add Settings UI toggle, health check, migration/rollback buttons.
  4) Add migration/export/import with dry-run + snapshot.
  5) Optional MongoDB driver if needed; otherwise rely on JSONB in Postgres.

---

## Quick Guidance on Driver Choices
- Start simple: SQLite for local/small installs; Postgres (or Supabase) for production; MySQL/MariaDB if that’s what hosting provides.
- Keep MongoDB optional; only add if document-store semantics are needed.
- Cloud managed DBs (AWS/GCP/Azure) are fine if they speak Postgres/MySQL—just use TLS and least privilege.

