# OSS git history hygiene

Sensitive runtime paths must never appear in git history. CI runs `./scripts/oss-history-audit.sh` before release.

## Clone reset after history rewrite

If `origin/main` was force-pushed after a history scrub:

```bash
git fetch origin
git reset --hard origin/main
```

Do not merge old local main over rewritten history.

## Scrubbed paths (2026-09-02)

- `data/invitations`, `data/ai_conversations`, `data/ai_approvals`
- `api/update.php`
- `docs/reviews`, `docs/roadmap`

Runtime dirs are created at deploy (Docker entrypoint) or on first Storage write — no tracked files under these paths.
