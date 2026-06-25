# P3-M9 — Backup & restore smoke notes

**Milestone:** P3-M9-T2  
**Date:** 2026-06-25  
**Scope:** Birkly CMS pre-update backup and restore paths (`core/version.php`, `api/updates.php`)

## What is backed up

`BirklyVersion::installUpdate()` creates a timestamped directory under `backups/pre-update-YYYY-MM-DD-HH-ii-ss/` before applying an update package. The backup includes CMS core files listed in the update manifest (excluding runtime dirs: `users/`, `cache/`, `logs/`, `settings/`, `media/`, `.env`).

## Manual smoke — backup creation (dry)

1. Start dev server: `php -S localhost:8080 -t . server-router.php`
2. Log in as admin → **Settings → Updates** (or `?page=updates`)
3. **Check for updates** — confirms API reachability (`api/updates.php?action=check_updates`)
4. On install, verify `backups/pre-update-*` appears **only after** a real install attempt (do not run install on production without human approval)

## Manual smoke — restore path

Restore is invoked automatically if update extraction fails (`BirklyVersion::restoreBackup()`). To verify logic without installing:

```bash
php -r "
require 'core/version.php';
// Reflection not needed — confirm class methods exist
echo method_exists('BirklyVersion', 'createBackup') ? 'createBackup: OK\n' : 'FAIL\n';
echo method_exists('BirklyVersion', 'restoreBackup') ? 'restoreBackup: OK\n' : 'FAIL\n';
"
```

## Automated checks (agent smoke)

`scripts/agent-smoke-test.sh` includes:

- PHP syntax on `api/updates.php` and `core/version.php`
- `tests/automation_plugin_test.php` (queue/cron; separate from backup but part of P3-M9 harness)

## Runtime data — not in backup

Per architecture rules, these are **never** committed and may not be in pre-update backups:

| Path | Notes |
|------|--------|
| `settings/` | Site config, automations, cron tokens |
| `users/` | Admin accounts |
| `media/` | Uploads |
| `cache/`, `logs/`, `temp/` | Ephemeral |

**Human action after restore:** Re-verify `settings/` and `users/` on the target environment; restore from separate off-site backup if needed.

## Failure modes

| Symptom | Likely cause |
|---------|----------------|
| Install rolls back immediately | Zip integrity or manifest mismatch — check `logs/` |
| `backups/` not writable | Filesystem permissions on CMS root |
| Updates API 403 | Non-admin session or CSRF failure |

## Sign-off

- [ ] `api/updates.php` returns JSON (not HTML) for authenticated admin
- [ ] `backups/` directory creatable by PHP process
- [ ] Human confirms off-site backup strategy for `settings/` + `users/` before production update
