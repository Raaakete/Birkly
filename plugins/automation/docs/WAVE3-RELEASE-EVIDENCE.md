# Automation Wave 3 release evidence

Date: 2026-07-31  
Branch: `cursor/plugin-release-readiness-plan-4216`  
Source of truth: `/agent/repos/Birkly-Plugin-Automation`  
CMS smoke target: `/agent/repos/Birkly` with plugin mounted at `plugins/automation`

## Automated gates

```bash
php -l scripts/http-smoke.php
php tests/run.php
php scripts/build-plugin-zip.php
BIRKLY_ROOT=/agent/repos/Birkly BIRKLY_SMOKE_BASE_URL=http://localhost:8080 php scripts/http-smoke.php
```

Observed result:

- Source test suite: `All automation plugin tests passed (11).`
- HTTP smoke: `Automation HTTP smoke: PASS`.
- CMS bootstrap proof: `automation_bootstrap()` loaded the active plugin and exposed runtime functions.
- CSRF proof: authenticated `create` request without `_csrf` returned `403 Invalid CSRF token`.
- Cron proof: `process_queue` without token returned `403`; the same action with `automation_get_cron_token()` returned `200` / `status=ok`.

## Package artifact

Rebuilt with:

```bash
php scripts/build-plugin-zip.php
```

Artifact metadata:

- Artifact: `build/birkly-automation-1.0.0.zip`
- SHA-256: `0d013413979fbbbb4424c64f937786d1f576452e870170cc9813fae3d0f8aec4`
- Files: `38`

Package guard notes:

- Required runtime files are asserted before zip creation.
- Runtime/internal directories (`build`, `cache`, `logs`, `settings`, `tests`, `users`) are excluded.
- Internal readiness docs (`docs/RELEASE-READINESS-PLAN.md`, `docs/RELEASE-READINESS-PROGRESS.md`, `docs/WAVE3-RELEASE-EVIDENCE.md`) are excluded from the zip.
- Symlinks are skipped.

## Install / activate / deactivate HTTP smoke checklist

Use this checklist against a fresh CMS or Marketplace-installed artifact:

1. Install
   - Unzip artifact so the plugin root is `plugins/automation`.
   - Confirm `plugins/automation/plugin.json`, `plugin.php`, `init.php`, `api/automation.php`, and `cli/process_queue.php` exist.
   - Run `php -l plugins/automation/plugin.php` and `php -l plugins/automation/api/automation.php`.
2. Activate
   - Activate through `/api/plugins.php?action=activate` with an authenticated admin session and valid CSRF token.
   - Confirm `settings/plugins.json` marks `automation.active=true`.
   - Run `BIRKLY_ROOT=/path/to/cms php plugins/automation/scripts/http-smoke.php` from the source repo, or copy `scripts/http-smoke.php` into the plugin source checkout and point it at the CMS.
   - Confirm `/api/automation.php?action=cron_info` is admin-only and returns masked cron URLs in the admin UI.
3. Deactivate
   - Deactivate through `/api/plugins.php?action=deactivate` with an authenticated admin session and valid CSRF token.
   - Confirm `settings/plugins.json` marks `automation.active=false`.
   - Confirm `/api/automation.php?action=list` returns `404` plugin inactive or an equivalent inactive response.
   - Confirm no queued/logged automation data is deleted during deactivate.

## Remaining external / host items

- Fresh Marketplace install/update/deactivate proof still needs a clean CMS browser walkthrough.
- Admin visual walkthroughs from `docs/VISUAL-WALKTHROUGH-SCRIPTS.md` still need recorded UI evidence.
