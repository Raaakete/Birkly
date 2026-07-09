# Birkly-Docs offline sync (P27-M12)

At **release time**, copy help topics from the sibling **Birkly-Docs** repository into the CMS offline bundle so Help works without live docs fetch.

## Prerequisites

- **Birkly** platform repo (this repo)
- **Birkly-Docs** cloned as a sibling directory (default `../Birkly-Docs`) or set `BIRKLY_DOCS_PATH`

## Run

```bash
chmod +x ./scripts/sync-birkly-docs.sh
./scripts/sync-birkly-docs.sh
```

With a custom docs path:

```bash
BIRKLY_DOCS_PATH=/path/to/Birkly-Docs ./scripts/sync-birkly-docs.sh
```

## What it does

1. Reads `help-manifest.json` from Birkly-Docs
2. Resolves each topic’s `docs_path` (`.html` → `.md`) and copies the markdown into `admin/docs/snapshots/`
3. Regenerates `admin/docs/help-offline-manifest.json` with snapshot paths for offline Help

Legacy snapshot filenames are preserved where needed (for example `ai` → `ai-overview.md`, `closed-alpha` → `closed-alpha.md`).

## When to run

- Before tagging a CMS release
- After Birkly-Docs help topics change materially
- As part of release CI (optional gate)

## Verification

- `admin/docs/snapshots/*.md` updated
- `admin/docs/help-offline-manifest.json` lists topics with `snapshot` paths
- Admin **Help** page search finds synced topics when live docs are unavailable

## Related

- `core/help_docs.php` — runtime manifest merge (live + offline)
- `docs/reviews/P27-admin-polish-plan.md` — P27-M12 acceptance
