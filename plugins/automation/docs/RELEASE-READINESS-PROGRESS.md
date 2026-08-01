# Automation release-readiness progress

Last updated: 2026-07-31, Wave 3 residual gates.

## Wave 3 residual gates

- Added `scripts/http-smoke.php` for source-repo HTTP smoke against a mounted CMS install (`BIRKLY_ROOT=/agent/repos/Birkly` by default).
- HTTP smoke covers CMS automation bootstrap, installed plugin path, cron token enforcement for `process_queue`, and authenticated mutating API rejection without CSRF.
- Added `docs/WAVE3-RELEASE-EVIDENCE.md` with install/activate/deactivate checklist, automated gate commands, and rebuilt artifact checksum.
- Package builder now excludes the Wave 3 evidence doc from the marketplace zip; source tests assert the exclusion.

## P0 closed in this session

- P0-03 Trigger evaluation.
- P0-04 Starter workflow block IDs and built-in canvas palette sections.
- P0-05 Dry-run/test side-effect safety.
- P0-06 Queue retry pending state, queue lock, stale processing recovery.
- P0-07 Outbound HTTP target hardening.
- P0-08 Execution log secret redaction.
- P0-09 Source-repo plugin-local tests.
- P0-11 Unsafe docs repaired for cron/test basics.
- P0-12 Truthful action failure status.
- P0-13 Visual walkthrough scripts defined.
- Source-repo package gate coverage: package builder now validates required runtime files, skips symlinks, and is covered by `php tests/run.php`.
- Admin health hint polish: cron URLs are masked by default with reveal-on-demand, matching the secure cron UI checklist.
- P41 quick win: Automation admin CSS now defines Automation accent token aliases and removes orphan primary-blue fallbacks.

## P0 partially closed

- P0-01 Cron authentication and scheduled processing.
  - Done: API requires cron token for `process_queue` and `process_scheduled`; CLI still works; admin UI displays secure cron commands through `cron_info` with token URLs masked until the admin reveals them.
  - Remaining: browser-level verification in a full CMS install.
- P0-02 API permissions and CSRF.
  - Done: API centralizes `manage_content`; CSRF required for create/update/delete/dry_run/test/run_live.
  - Remaining: HTTP integration matrix in a full CMS install.
- P0-10 Marketplace package/install proof.
  - Done: package build script and checklist added; package builder validates required runtime files, skips symlinks, writes checksum metadata, and has source-repo test coverage for excluded runtime/internal files.
  - Remaining: install the built artifact through Marketplace/fresh CMS and record result.
- P0-14 Release decision gate.
  - Done: DoD/checklists exist in plan and packaging docs.
  - Remaining: execute gate after full automated + visual QA.

## P0 still requiring full-environment verification

- Fresh CMS install with no bundled plugin.
- Marketplace install/update/deactivate using built zip.
- HTTP auth/CSRF matrix against real CMS session roles.
- Browser walkthrough recordings from `docs/VISUAL-WALKTHROUGH-SCRIPTS.md`.
- Core entry/sub-entry/webhook route integration with current CMS hooks.

## Current test commands

```bash
php -l scripts/http-smoke.php
php tests/run.php
php scripts/build-plugin-zip.php
BIRKLY_ROOT=/agent/repos/Birkly BIRKLY_SMOKE_BASE_URL=http://localhost:8080 php scripts/http-smoke.php
```
