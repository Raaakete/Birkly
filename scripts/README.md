# Birkly scripts

Maintainer and release tooling for the public CMS repo. Most scripts are **not** shipped in the self-host release zip (see `cms-release-manifest.json`).

## Self-host and release

| Script | Purpose |
|--------|---------|
| `build-cms-release.sh` | Build runtime-only `birkly-release.zip` (uses manifest below) |
| `create-default-user.php` | First admin user when CLI setup is needed |
| `reset-admin-password.php` | Emergency password reset (CLI only) |
| `secret-scan.sh` | Reject live API token patterns before release |
| `oss-history-audit.sh` | Verify sensitive paths are absent from git history |
| `scrub-oss-history.sh` | Maintainer-only history rewrite if audit fails |

```bash
OUT=/tmp/birkly-release.zip ./scripts/build-cms-release.sh
```

Customer zips include `0_START-HERE.html`, platform launchers (`start-server.sh` / `.ps1`, `check-php.sh` / `.ps1`), and a minimal `scripts/` subset defined in the manifest.

## Plugins (Maintainers)

| Script | Purpose |
|--------|---------|
| `build-plugin-zip.sh` | Zip one plugin from a SoT clone or `plugins/{id}/` |
| `build-all-official-plugin-zips.sh` | All `official_plugin_ids` in the manifest |
| `install-dev-plugins.sh` | Install official plugins from Hub or local catalog seeds |

**Source of truth:** `Birkly-Other-Things/Birkly-Plugin-*` (private). Publish via each plugin repo’s GitHub Release workflow — not from this CMS repo.

```bash
./scripts/build-plugin-zip.sh commerce
BIRKLY_PLUGIN_SOT_ROOT=../Birkly-Plugin-Commerce ./scripts/build-plugin-zip.sh commerce
./scripts/install-dev-plugins.sh
```

Slugs and IDs: `scripts/cms-release-manifest.json`.

## Hub catalog (Maintainers)

| Script | Purpose |
|--------|---------|
| `sync-marketplace-fallback-catalog.php` | Refresh `catalog/marketplace-registry-fallback.json` (bundled offline Hub catalog) |

## Localization (Maintainers)

| Script | Purpose |
|--------|---------|
| `i18n-audit.php` | Key parity across selectable admin locales |
| `i18n-sync-locale.php` | Sync a locale file from `lang/en.json` |
| `i18n-sync-plugin-locale.php` | Plugin locale parity |
| `i18n-new-locale.php` | Scaffold a new locale from English |
| `build-locale-from-en.py` | Batch locale generation helper |

One-off P85 migration batches were removed from this repo; use `i18n-audit.php` for ongoing work.

## CI and quality

| Script | Purpose |
|--------|---------|
| `ci-check.sh` | Public CI: PHP syntax, template engine, focused PHP/Node tests |
| `validate-project-templates.php` | Project template validator (also linted in GitHub Actions) |
| `sync-template-engine-inline.sh` | Guard client/server template engine drift (`--check` in CI) |

The **full** PHP regression suite runs in the private `Raaakete/Birkly-Marketplace` workspace (`internal/cms/scripts/ci-check.sh`). See [CONTRIBUTING.md](../CONTRIBUTING.md).

## Optional local / ops smoke

These are developer conveniences and are excluded from the release zip:

- `production-smoke.sh`, `local-install-smoke.sh`, `staging-smoke-kekaba.sh`
- `install-kekaba-demo-plugins.sh`, `patch-kekaba-plugins.php.sh` — see `docs/client-sites/cms-vendoring.md`
- `build-birkly-launch.sh`, `sync-release-launchers.sh` — desktop launcher artifacts

## Desktop MCP helper (shipped in zip)

- `birkly-mcp-desktop.php`, `birkly-mcp-desktop-install.php`
