# kekaba production deploy checklist

**Milestone:** P12-M5 — production deploy closure  
**Site repo:** `Raaakete/kekaba`  
**CMS embed:** `Birkly-0.1/` (synced from `Raaakete/Birkly`, not forked)

Use this checklist when promoting kekaba to production or recovering a broken host. Agents prepare docs and smoke scripts; **humans execute FTP/hosting steps** and own DNS, TLS, and live credentials.

---

## Staging (pre-production)

Use staging to validate embed sync, marketplace plugin installs, and commerce/events routes before promoting to production.

**Default staging URL:** `https://service.kekaba.com` (override with `BIRKLY_STAGING_BASE_URL`).

### Staging workflow

- [ ] Sync embed and run `prepare-kekaba-deploy.sh` (same as production § Before upload).
- [ ] Upload to **staging** document root (human).
- [ ] Admin → Plugins → Marketplace — install in order:
  1. **commerce** (shop + cart API routes)
  2. **events** (calendar / iCal routes)
  3. **automation** (optional workflows)
  4. **user_management** (optional community features)
- [ ] Register staging host under Settings → Domains.

### Staging smoke (agent / CI)

From Birkly repo (requires sibling `kekaba` checkout or `KEKABA_REPO`):

```bash
cd /path/to/Birkly
chmod +x ./scripts/staging-smoke-kekaba.sh
BIRKLY_STAGING_BASE_URL=https://service.kekaba.com ./scripts/staging-smoke-kekaba.sh
```

Runs:

1. `kekaba/scripts/check-production.sh` — static site, loaders, snapshots, commerce page routes
2. `scripts/production-smoke.sh` — `Birkly-0.1` health, build_info, marketplace reachability

Skip marketplace egress in CI:

```bash
BIRKLY_SMOKE_SKIP_MARKETPLACE=1 BIRKLY_STAGING_BASE_URL=https://service.kekaba.com ./scripts/staging-smoke-kekaba.sh
```

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

## Live production (human)

Promote from staging after `staging-smoke-kekaba.sh` passes on `https://service.kekaba.com` (or your staging host).

### Live cutover checklist

- [ ] DNS A/AAAA for production domain points to host (human)
- [ ] TLS certificate valid (human)
- [ ] Upload bundle per § Upload bundle — **preserve** server `content/`, `users/`, `settings/`, `media/`
- [ ] Root `.htaccess` allows `/Birkly-0.1/` PHP (no `RewriteRule ^Birkly-0\.1/ - [F,L]`)
- [ ] Marketplace plugins installed on **production** admin (order below)
- [ ] Settings → Domains: production host registered
- [ ] Settings → Marketplace: `marketplace_url` → `https://marketplace.birkly.io`
- [ ] Commerce live Stripe keys in CMS Settings (human) if shop enabled
- [ ] Run post-upload smoke (both scripts below) — expect **PASS**

### Production plugin install order

Install via Admin → Plugins → Marketplace on the **live** host (not local demo zip):

1. **commerce** — shop, cart, checkout API routes
2. **events** — calendar / iCal routes
3. **automation** — optional workflows
4. **user_management** — optional community features

Re-run smoke after each required plugin; shop/events routes fail until commerce + events are active.

### Live smoke (required)

```bash
# From kekaba repo — static site + loaders + commerce routes
cd /path/to/kekaba
./scripts/check-production.sh https://YOUR-PRODUCTION-DOMAIN

# From Birkly repo — embed health + marketplace
cd /path/to/Birkly
./scripts/production-smoke.sh https://YOUR-PRODUCTION-DOMAIN/Birkly-0.1
# Cron on ops host:
./scripts/scheduled-production-smoke.sh https://YOUR-PRODUCTION-DOMAIN/Birkly-0.1
```

### Live verification gates

| Check | URL | Expected |
|-------|-----|----------|
| Embed reachable | `/Birkly-0.1/ping.txt` | HTTP 200 |
| Admin login | `/Birkly-0.1/admin/login.php` | HTTP 200 |
| Health JSON | `/Birkly-0.1/api/health.php` | `status` ok or degraded |
| Shop route | `/shop.html` or commerce slug | Page loads with commerce plugin |
| Marketplace registry | CMS health `marketplace.status` | reachable |

### Embed sync verify (agent, before human upload)

```bash
cd /path/to/Birkly
./scripts/sync-client-embed.sh /path/to/kekaba scripts/fixtures/kekaba-embed-excludes.txt
./scripts/patch-kekaba-plugins.php.sh /path/to/kekaba/Birkly-0.1/core/plugins.php
cd /path/to/kekaba && ./scripts/prepare-kekaba-deploy.sh
```

Confirm `Birkly-0.1/VERSION` or `api/build_info.php` version matches Birkly `main` you intend to ship.

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

- `docs/reviews/P16-production-deploy-playbook.md` — ecosystem deploy index
- `docs/client-sites/cms-vendoring.md` — embed policy
- `scripts/production-smoke.sh` — CMS + marketplace smoke
- `docs/reviews/P11-P12-phase-closure-checklist.md` — phase closure
