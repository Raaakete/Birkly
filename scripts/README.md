# Birkly scripts

## Official plugin builds (P71 — SoT repos only)

| Script | Purpose |
|--------|---------|
| `build-plugin-zip.sh` | Build one marketplace-ready zip from a SoT repo or `plugins/{id}/` install |
| `build-all-official-plugin-zips.sh` | Build all IDs in `cms-release-manifest.json` → `official_plugin_ids` |
| `install-dev-plugins.sh` | Install official plugins from registry or Marketplace catalog seeds |

**Source of truth:** `Birkly-Other-Things/Birkly-Plugin-*` repos. The Ops mirror at `internal/cms/packages/official-plugins/` is deprecated (P71).

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

## Other scripts

- `ci-check.sh` — lint + full test harness before merge (in Birkly-Marketplace `internal/cms/scripts/`)
- `agent-smoke-test.sh` — quick session smoke checks (internal workspace)
