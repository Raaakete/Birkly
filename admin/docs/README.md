# Admin docs (legacy)

**Do not edit authoritative human product documentation here.**

Human-facing docs are maintained in **[Birkly-Docs](https://github.com/Raaakete/Birkly-Docs)** and published at [docs.birkly.com](https://docs.birkly.com). The CMS admin Help panel loads topics from that manifest (live fetch with offline mirror).

## What lives here

| Path | Role |
|------|------|
| `snapshots/*.md` | Offline markdown mirrors synced by `./scripts/sync-birkly-docs.sh` |
| `help-offline-manifest.json` | Offline Help topic index (includes `ai_topic`) |
| `*.php` | **Legacy** standalone HTML pages — deprecated, not wired to Help v2 |

## Sync workflow

After Birkly-Docs manifest or topic changes:

```bash
BIRKLY_DOCS_PATH=/path/to/Birkly-Docs ./scripts/sync-birkly-docs.sh
```

This updates `admin/docs/snapshots/`, `admin/docs/help-offline-manifest.json`, and `docs/help-manifest.json`.

## Agent / AI docs

Operational agent playbooks remain in `docs/ai/playbooks/` in the CMS repo. They link to Birkly-Docs via `ai_topic` — do not duplicate full user guides into playbooks.
