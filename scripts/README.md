# Birkly scripts

## Official plugin builds

| Script | Purpose |
|--------|---------|
| `build-plugin-zip.sh` | Build one marketplace-ready zip from `plugins/{id}/` |
| `build-all-official-plugin-zips.sh` | Build all IDs in `cms-release-manifest.json` → `official_plugin_ids` |
| `publish-official-plugins.sh` | Build + upload to Marketplace registry on release |

### Build a single plugin

```bash
./scripts/build-plugin-zip.sh commerce
./scripts/build-plugin-zip.sh commerce 1.0.1
```

Output: `dist/plugins/{marketplace_slug}-{version}.zip` (sha256 printed on stderr).

### Build all official plugins

```bash
./scripts/build-all-official-plugin-zips.sh
```

### Publish on tag (CI)

GitHub Actions workflow `.github/workflows/publish-official-plugins.yml` runs on `v*` tags:

1. Builds each official plugin via `build-plugin-zip.sh`
2. POSTs artifacts to `https://marketplace.birkly.io/api/v1/registry/official/releases`

Required repository secret:

- `MARKETPLACE_REGISTRY_PUBLISH_TOKEN` — Bearer token for the official provider registry publish API

Local dry-run (no upload):

```bash
./scripts/publish-official-plugins.sh --dry-run 1.0.0
```

Environment overrides:

- `MARKETPLACE_URL` — registry host (default `https://marketplace.birkly.io`)
- `CHANGELOG` — release notes sent with each artifact

Official plugin IDs and marketplace slugs are defined in `scripts/cms-release-manifest.json`.

## Other scripts

- `ci-check.sh` — lint + full test harness before merge
- `install-dev-plugins.sh` — install official plugins for local dev when `plugins/` is empty
- `agent-smoke-test.sh` — quick session smoke checks
