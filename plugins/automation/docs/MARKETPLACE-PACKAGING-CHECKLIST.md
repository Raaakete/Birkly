# Automation marketplace packaging checklist

Source of truth: this repository.

## Build

```bash
php scripts/build-plugin-zip.php
```

Expected output:

- `build/birkly-automation-<version>.zip`
- `build/birkly-automation-<version>.zip.sha256`
- JSON metadata printed with `status`, `artifact`, `sha256`, `marketplace_slug`, `version`, and `files`.

## Artifact contents

The zip must unpack to a single top-level `automation/` directory.

Included:

- `plugin.json`
- `plugin.php`
- `init.php`
- runtime PHP files
- `admin/`
- `api/`
- `blocks/`
- `cli/`
- `docs/` user/help docs
- `includes/`
- `README.md`

Excluded:

- `.git/`
- `build/`
- `tests/`
- runtime data: `settings/`, `cache/`, `logs/`, `users/`
- internal release-readiness planning files

## Pre-publish checks

- [ ] `php tests/run.php` passes from this repo.
- [ ] `php scripts/build-plugin-zip.php` succeeds.
- [ ] SHA256 file matches the zip.
- [ ] Zip contains no runtime data or secrets.
- [ ] Fresh CMS install can upload/install the zip from Marketplace.
- [ ] Activation registers admin page, docs shelf, blocks, queue/scheduler API, and dashboard widget.
- [ ] Deactivation removes admin/API/hooks without deleting stored workflows.
- [ ] README and Help docs match the built artifact behavior.
- [ ] Marketplace listing uses the same `plugin.json` version and `marketplace_slug`.
