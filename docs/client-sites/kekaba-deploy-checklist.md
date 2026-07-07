# kekaba production deploy checklist

**Milestone:** P12-M5 — production deploy closure  
**Site repo:** `Raaakete/kekaba`  
**CMS embed:** `Birkly-0.1/` (synced from `Raaakete/Birkly`, not forked)

Use this checklist when promoting kekaba to production or recovering a broken host. Agents prepare docs and smoke scripts; **humans execute FTP/hosting steps** and own DNS, TLS, and live credentials.

---

## Before upload (local)

- [ ] Platform changes merged on Birkly `main` (security/API fixes land in Birkly first).
- [ ] Sync embed from Birkly repo:
  ```bash
  cd /path/to/Birkly
  ./scripts/sync-client-embed.sh /path/to/kekaba scripts/fixtures/kekaba-embed-excludes.txt
  ./scripts/patch-kekaba-plugins.php.sh /path/to/kekaba/Birkly-0.1/core/plugins.php
  ```
- [ ] Optional local commerce/events demos: `./scripts/install-kekaba-demo-plugins.sh /path/to/kekaba`
- [ ] Run kekaba prepare script:
  ```bash
  cd /path/to/kekaba
  ./scripts/prepare-kekaba-deploy.sh
  ```
- [ ] Confirm site root `.htaccess` does **not** block `Birkly-0.1/` (see kekaba `DEPLOY-RECOVERY.md`).
- [ ] Bake static HTML if content changed: `php scripts/bake-static-html.php`

---

## Upload bundle (human)

Upload the **whole project folder** to the host document root (PHP required for CMS paths).

| Path | Notes |
|------|--------|
| `Birkly-0.1/` | Full tree: `admin/`, `api/`, `core/`, `birkly-client.js`, `site/kekaba_publish.php` |
| `Birkly-0.1/media/previews/` | WebP/JPEG thumbs (`chmod 755`) |
| `Birkly-0.1/cache/page-snapshots/` | `index.json`, slug snapshots + `cache/.htaccess` |
| `publish/` | Slug HTML for `post.html`, `case-study.html`, `snapshot.html` |
| `components/` | `birkly-config.js`, `birkly-loader.js`, `kekaba-cms-cache.js`, … |
| Site HTML + assets | `index.html`, `service.html`, `blog.html`, `src/`, `fonts/`, `site-summary.txt` |
| `.htaccess` | Site root rewrites; must allow `/Birkly-0.1/` PHP |

**Do not upload from git:** `content/`, `users/`, `media/` originals, `cache/`, `logs/`, `settings/`, `.env` — preserve server copies or migrate deliberately.

---

## Plugins (marketplace)

Official plugins (`commerce`, `events`, `automation`, `user_management`) are **not** vendored in the embed zip.

- [ ] Production/staging: Admin → Plugins → Marketplace → install required plugins.
- [ ] After marketplace install, re-run site smoke (shop/cart/events routes need active plugins).
- [ ] Local-only demos: `install-kekaba-demo-plugins.sh` (not a substitute for production marketplace install).

See `docs/client-sites/cms-vendoring.md` § Plugins after embed sync.

---

## Post-upload verification

### Site smoke (kekaba repo)

```bash
cd /path/to/kekaba
./scripts/check-production.sh https://service.kekaba.com
```

Covers static site, loaders, `page_bundle.php`, snapshots, admin login, baked HTML crawlability.

### CMS health (Birkly platform)

From Birkly repo against the **public CMS base** (e.g. `https://service.kekaba.com/Birkly-0.1`):

```bash
cd /path/to/Birkly
./scripts/production-smoke.sh https://service.kekaba.com/Birkly-0.1
```

Checks:

- `api/health.php` — writable dirs (`settings`, `content`, `cache`, `logs`), version, disk
- `api/build_info.php` — version stamp
- `https://marketplace.birkly.io/health` — registry reachability

### Admin configuration

- [ ] Settings → Domains: live host registered (public API CORS/origin).
- [ ] Settings → Security: default passwords rotated; GitHub token if private update source.
- [ ] Settings → Marketplace: `marketplace_url` points at production registry.
- [ ] Log in to `/Birkly-0.1/admin/` and open deploy diagnostics (`/admin/deploy-check.php`).

### Hosting sanity

- [ ] `https://your-domain/Birkly-0.1/ping.txt` → **200** (proves folder not blocked).
- [ ] `https://your-domain/Birkly-0.1/admin/login.php` → **200**.
- [ ] `https://your-domain/Birkly-0.1/api/health.php` → JSON `status` is `ok` or `degraded` (not `error`).

---

## Human-only gates (agent does not run)

- [ ] DNS / TLS for production domain
- [ ] Stripe live keys + Connect (commerce on production)
- [ ] Legal review of marketplace terms
- [ ] Production marketplace operator checklist (`Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md`)

---

## Recovery

If CMS URLs return 404 but the static site works:

1. Re-upload root `.htaccess` from repo (remove any `RewriteRule ^Birkly-0\.1/ - [F,L]`).
2. Upload `Birkly-0.1/ping.txt` and confirm **200**.
3. Re-upload full `Birkly-0.1/admin/` and `Birkly-0.1/api/`.

Details: kekaba `DEPLOY-RECOVERY.md`, `AUDIT-TODAY.md`.

---

## Related

- `docs/client-sites/cms-vendoring.md` — embed policy
- `scripts/production-smoke.sh` — CMS + marketplace smoke
- `docs/reviews/P11-P12-phase-closure-checklist.md` — phase closure
