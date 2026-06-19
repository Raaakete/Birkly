# P2-M7 — Platform sync & kekaba proof notes

**Date:** 2026-06-19  
**Platform PR:** (pending) — adds `scripts/sync-client-embed.sh`  
**Site PRs:** kekaba + Birkly-Website embed sync from platform `main` (post P2-M6)

## T1 — CMS vendoring sync

Synced platform `main` into:

| Site | Embed path | Excludes |
|------|------------|----------|
| kekaba | `Birkly-0.1/` | `scripts/fixtures/kekaba-embed-excludes.txt` (site hooks, publish perf) |
| Birkly-Website | `Birkly-0.1/` | standard runtime excludes only |

Post-sync: `scripts/patch-kekaba-plugins.php.sh` re-applies `site/kekaba_publish.php` hook in `core/plugins.php`.

Verified present after sync:

- `birkly-client.js`, `birkly-hydrate.js`
- `birkly-client-modules/birkly-bundle.js`
- `api/page_bundle.php`, `api/page_snapshot.php`
- `admin/template_safe_renderer.php`, `core/collection_meta.php`

## T2 — Dev smoke (bundle / hydrate)

Local CMS servers (`php -S` on embed docroot):

| Check | kekaba :8099 | Birkly-Website :8090 |
|-------|--------------|----------------------|
| `public.php?action=list_collections` | 200 | 200 |
| `page_bundle.php` POST | 200 (`status: ok`) | 200 (`status: ok`) |
| `page_snapshot.php?page=index` | 200 (`status: ok`) after `generate-page-snapshots.php` | 200 (`status: ok`) after `generate-page-snapshots.php` |
| Static `index.html` (:3001 / :3000) | 200 | 200 |

Note: empty `blog` collection in fresh embeds is expected until demo content is created; bundle still returns `status: ok`.

## T3 — Kekaba production smoke

`./scripts/check-production.sh https://kekaba.com`:

| Check | Result |
|-------|--------|
| Home, loader JS, `birkly-client.js` | 200 |
| `api/page_bundle.php` | 200 |
| `api/page_snapshot.php?page=index` | OK (`status: ok`) |
| Admin login / dashboard redirect | 200 / 302 |
| Baked HTML crawl (index, service, blog, post, case-study) | 200 |
| Further-reading section on one post | FAIL (pre-existing content; not introduced by this sync) |

Production CMS + hydrate/bundle path is reachable. Full deploy of synced `Birkly-0.1/` to production host is a human deploy step after PR merge.

## Video (T3)

Production HTTP validation recorded above. Screen recording steps: run `check-production.sh`, open `https://kekaba.com/` in browser, confirm Network tab shows snapshot or bundle load without console errors.
