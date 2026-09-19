# Birkly scripts

## Official plugin builds (P71 — SoT repos only)

| Script | Purpose |
|--------|---------|
| `build-plugin-zip.sh` | Build one marketplace-ready zip from a SoT repo or `plugins/{id}/` install |
| `build-all-official-plugin-zips.sh` | Build all IDs in `cms-release-manifest.json` → `official_plugin_ids` |
| `install-dev-plugins.sh` | Install official plugins from registry or Marketplace catalog seeds |

**Source of truth:** `Birkly-Other-Things/Birkly-Plugin-*` repos. The Ops mirror at `internal/cms/packages/official-plugins/` was **deleted** in P71 (2026-09-02).

`publish-official-plugins.sh` is **deprecated** — publish from each SoT repo's GitHub Release workflow instead.

### Build a single plugin

```bash
# From sibling SoT clone (auto-detected)
./scripts/build-plugin-zip.sh commerce

# Explicit SoT root
BIRKLY_PLUGIN_SOT_ROOT=../Birkly-Plugin-Commerce ./scripts/build-plugin-zip.sh commerce
./scripts/build-plugin-zip.sh commerce 1.0.1
```

Output: `dist/plugins/{marketplace_slug}-{version}.zip` (sha256 printed on stderr).

### Build all official plugins

Requires sibling SoT clones or installed `plugins/` copies:

```bash
./scripts/build-all-official-plugin-zips.sh
```

### Dev install

```bash
./scripts/install-dev-plugins.sh
```

Pulls from marketplace registry API, with fallback to `../Birkly-Marketplace/catalog/packages/` seed zips.

Official plugin IDs and marketplace slugs are defined in `scripts/cms-release-manifest.json`.

### CMS release zip

Customer zips use `start-server.bat` / `start-server.sh` and `0_START-HERE.html` only (no `Birkly Launch` binaries in the zip). Optional dev build: `./scripts/build-birkly-launch.sh` (not part of `build-cms-release.sh`).

```bash
OUT=/tmp/birkly-release.zip ./scripts/build-cms-release.sh
```

## Other scripts

- `sync-marketplace-fallback-catalog.php` — refresh `catalog/marketplace-registry-fallback.json` (bundled Hub offline catalog)
- `ci-check.sh` — lint + full test harness before merge (in Birkly-Marketplace `internal/cms/scripts/`)
- `agent-smoke-test.sh` — quick session smoke checks (internal workspace)
