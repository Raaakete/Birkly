# P8 deploy checklist — kekaba reference site

**Site:** https://birkly.kekaba.com  
**Human action:** `git pull` on server after P8 merges land.

## Pre-deploy (CMS — Birkly repo)

1. Confirm `php tests/run.php` is green on `main`.
2. Merge all P8 milestone PRs (commerce ops, subscriptions, cell merge).

## kekaba embed sync

From Birkly repo root:

```bash
./scripts/sync-client-embed.sh /path/to/kekaba
```

Verify `kekaba/Birkly-0.1/plugins/` contains: `commerce`, `events`, `automation`, `user_management`.

**Do not commit:** `content/`, `users/`, `settings/`, `media/`, `cache/`, `logs/`, `.env`, `config.php`.

## kekaba repo deploy

```bash
cd /path/to/kekaba
git pull origin main
```

## Post-deploy smoke (human)

| Check | Expected |
|-------|----------|
| Admin login | `/admin` loads; Commerce + Events in sidebar |
| Commerce → Catalog | Products indexed |
| Demo shop page | Product listing renders |
| Demo events page | Calendar/list shows events |
| Checkout | Tax line when tax rules configured |
| Order paid | Automation triggers for receipt/admin notify |
| Subscriptions | Admin can view/cancel recurring subscriptions |

## Rollback

- Revert kekaba `git pull` to previous commit.
- CMS: revert Birkly `main` to prior release tag/commit if needed.
