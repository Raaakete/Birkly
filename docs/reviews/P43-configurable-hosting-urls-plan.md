# P43 — Configurable hosting URLs (CMS vs public site)

**Status:** Approved (2026-07-25, Keven) — **next active phase**  
**Repos:** Birkly (primary) · Birkly-Docs (hosting guides)  
**Handoff:** `docs/reviews/P43-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P43-verification-matrix.md`  
**Gap register:** `docs/reviews/P43-gap-register.md`

---

## Problem

Birkly’s integration model is **headless-friendly**: the public website can live on **any domain** and connect via `birkly-client.js`. The optional **`project/`** folder is a convenience, not the architecture.

Routing today does **not** match that model:

1. **`cms.` hostname magic** — only hosts starting with `cms.` get `/` → admin.
2. **`project/` folder hijacks `/`** on other hosts when the directory exists.
3. **No settings** for “CMS URL” vs “public site URL” — Dokploy Internal Path is misused to patch gaps.
4. **Public URLs** always expose `/project/` in the path when using bundled HTML.

Production proof (`birkly.cloud` + `cms.birkly.cloud`, 2026-07-25): same Dokploy app, same files — behavior differed only because of hostname prefix and Dokploy path rewrite.

---

## Vision

**One Birkly install, explicit URLs in settings, predictable routing.**

| Concept | User-facing meaning |
|---------|---------------------|
| **CMS URL** | Where admin, API, and `birkly-client.js` live |
| **Public site URL** | Where visitors see the marketing site (optional) |
| **Allowed origins** | Which browser origins may call the Public API (existing Domains) |

**Dokploy / nginx / Traefik** only forward hostnames to the container (Path `/`, Internal Path `/`). **Birkly** decides what each hostname means.

---

## Locked decisions (P43-D1–D10)

| ID | Decision |
|----|----------|
| **P43-D1** | Replace `str_starts_with($host, 'cms.')` with settings-driven host roles |
| **P43-D2** | `cms_base_url` required after onboarding (scheme + host, no trailing slash) |
| **P43-D3** | `public_site_url` optional; modes: `none` · `external` · `project` |
| **P43-D4** | `external` = site hosted elsewhere; Birkly does not serve HTML at public URL (redirect or informational landing optional) |
| **P43-D5** | `project` = files in `project/`; public host serves clean root URLs (no `/project/` in browser bar) |
| **P43-D6** | Saving public site URL auto-registers host in Domains allowlist (idempotent) |
| **P43-D7** | `/admin` on non-CMS host → redirect to CMS URL `/admin` (canonical admin entry) |
| **P43-D8** | Single host (CMS URL = public site URL): user picks default landing — **admin** or **public site** |
| **P43-D9** | Migration: if settings empty, infer `cms.*` request host as CMS URL; if `project/index.html` exists and host ≠ CMS, infer public URL |
| **P43-D10** | Notify human **once** at P43-M9 closure; no per-milestone approval |

---

## Settings schema (target)

Stored in `settings/hosting.json` (new) with mirrors in admin API:

```json
{
  "cms_base_url": "https://cms.birkly.cloud",
  "public_site_mode": "project",
  "public_site_url": "https://birkly.cloud",
  "same_host_landing": "public",
  "strict_admin_host": true
}
```

| Field | Values | Notes |
|-------|--------|-------|
| `cms_base_url` | HTTPS URL | Canonical admin + API base |
| `public_site_mode` | `none` \| `external` \| `project` | |
| `public_site_url` | HTTPS URL or empty | Required when mode ≠ `none` |
| `same_host_landing` | `admin` \| `public` | Only when CMS and public hosts equal |
| `strict_admin_host` | boolean | Default `true` — enforce P43-D7 |

Reuse / align existing `admin.cms_url` — migrate to hosting.json or keep as display alias.

---

## Routing rules (after P43)

### Host role resolution

`core/hosting_urls.php`:

- `birkly_hosting_cms_host()` — host from `cms_base_url`
- `birkly_hosting_public_host()` — host from `public_site_url` when mode ≠ `none`
- `birkly_request_host_role()` — `cms` \| `public` \| `unknown` \| `local`

### Request handling

| Path | CMS host | Public host | Unknown host |
|------|----------|-------------|--------------|
| `/` | → admin (or `same_host_landing`) | → public site root | 404 or redirect to CMS URL |
| `/admin` | admin | redirect to CMS `/admin` | redirect or 404 |
| `/api/*` | API | API (CORS as today) | API |
| `/project/*` | serve files (legacy path) | rewrite internally; prefer clean URLs on public host |

### Remove

- `index.php` `cms.` prefix check (replace with hosting resolver)

---

## UI (Settings → Hosting)

New tab or sub-section under **Domains**:

1. **CMS URL** — text + “Use current host” button  
2. **Public website** — radio: None · Hosted elsewhere · In Birkly (`project/`)  
3. **Public site URL** — shown when not None  
4. **When both use the same domain** — landing page radio  
5. **Hosting checklist** (collapsible) — Dokploy domains, Internal Path `/`, volume `/app/data`, test URLs  
6. **Integration snippet** — generated `<script>` with correct `data-cms-url`  
7. Link to **Settings → Project** when mode = `project`

Update **Settings → Project** overview: show public URL from hosting settings (not hardcoded `/project/index.html` when clean URLs enabled).

---

## Onboarding (P43-M4)

After first admin account:

1. Confirm **CMS URL** (default: current host)  
2. **Where is your website?** — Elsewhere · In Birkly · Not yet  
3. If elsewhere: URL + auto domain register + script snippet  
4. Skip allowed — defaults from first login host

---

## Nginx / Docker (P43-M5)

`docker/nginx-site.conf`:

- Keep `location = /` → `index.php` for CMS host behavior  
- On public host (if nginx cannot split by host, PHP handles redirects): optional `map` or dual-server blocks if feasible  
- **Clean URLs:** for public host, `try_files` maps `/` → `project/index.html`, `$uri` → `project$uri` before PHP  

Document: Dokploy **Internal Path must be `/`** for all domains.

Merge or supersede open PR #289 nginx fixes as part of M5.

---

## Milestones

| ID | Focus | Owner | Status |
|----|--------|--------|--------|
| **P43-M0** | Gap register + plan approval (this PR) | product-planner | complete |
| **P43-M1** | `hosting.json` + `core/hosting_urls.php` + migration | cms-developer | complete |
| **P43-M2** | `index.php` / router: settings-based `/` and host roles | cms-developer | complete |
| **P43-M3** | Settings → Hosting UI + API | cms-developer | complete |
| **P43-M4** | Onboarding steps (CMS URL, public site mode) | cms-developer | complete |
| **P43-M5** | Clean public URLs + docker nginx | cms-developer | complete |
| **P43-M6** | Admin canonical host redirects (`/admin` off CMS host) | cms-developer | complete |
| **P43-M7** | `site_domain.php`, CORS, `birkly_public_site_url()` alignment | cms-developer | complete |
| **P43-M8** | Tests: `hosting_urls_test.php`, routing HTTP fixtures | cms-developer | complete |
| **P43-M9** | Birkly-Docs + `docker.md` + closure matrix | docs-writer | complete |

**Depends on:** Docker single-volume (`BIRKLY_DATA`) on `main` · live `*.birkly.cloud` reference install.

**Does not include:** P41 design system · P42 marketing template pack · Studio P26 resume.

---

## Example setups (acceptance narratives)

1. **CMS only, site on Netlify** — CMS URL `cms.client.com`, public external `client.com`, `/` on cms → admin, client.com not served by Birkly.  
2. **One domain** — `mycompany.com` CMS only, mode `none`, `/` → admin.  
3. **Bundled template (birkly.cloud)** — CMS `cms.birkly.cloud`, public `birkly.cloud` project mode, `/` on apex → site root without `/project/` in URL.  
4. **Single domain + project** — both URLs `mycompany.com`, landing `public`, `/admin` still works.

---

## Values check (`docs/PROJECT_VALUES.md` when present)

- **Dependency-free core** — hosting resolver in `core/`, no new npm deps  
- **UI decoupled** — routing in PHP core; admin is configuration surface  
- **Self-contained** — no Dokploy-specific code in core; checklist is docs/UI only

---

## Task ID format

`P43-M[N]-T[N]` — e.g. `P43-M3-T2` Hosting API save validation.
