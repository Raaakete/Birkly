# P43 — Gap register (configurable hosting URLs)

**Status:** Open — baseline for P43-M0  
**Plan:** `docs/reviews/P43-configurable-hosting-urls-plan.md`

---

## G1 — Root URL routing is hostname magic, not settings

| | |
|---|---|
| **Today** | `index.php` uses `str_starts_with($host, 'cms.')` for `/` → admin; other hosts → `/project/` |
| **Problem** | Requires `cms.*` subdomain; breaks `admin.example.com` + `www.example.com`; single-domain CMS cannot land on admin at `/` when `project/` exists |
| **Target** | `cms_base_url` + `public_site_url` in settings drive `/` behavior |
| **Milestone** | P43-M2 |

## G2 — Three concepts conflated

| | |
|---|---|
| **Today** | `project/` folder, Domains allowlist, and Dokploy Internal Path used interchangeably for “marketing host” |
| **Problem** | Site owners confuse upload path with DNS; Dokploy Internal Path `/project` double-prefixes URLs |
| **Target** | Clear UI: CMS URL · Public site URL (optional) · Allowed API origins |
| **Milestone** | P43-M3, P43-M7 |

## G3 — Public site URL always shows `/project/` path

| | |
|---|---|
| **Today** | `birkly_public_site_url()` always prefixes `/project/`; apex shows `birkly.cloud/project/index.html` |
| **Problem** | Unprofessional marketing URLs; contradicts “host anywhere” story when using bundled `project/` |
| **Target** | On public site host, `/` and `/about.html` map to `project/` files without `/project/` in the browser bar |
| **Milestone** | P43-M5 |

## G4 — `cms_url` setting exists but does not drive routing

| | |
|---|---|
| **Today** | `admin.json` has `cms_url` field; not used for host role or redirects |
| **Problem** | Dead setting; onboarding does not set canonical CMS URL |
| **Target** | `cms_url` becomes canonical CMS base; used for redirects, script tag helper, smoke tests |
| **Milestone** | P43-M1, P43-M2 |

## G5 — Admin reachable on every hostname

| | |
|---|---|
| **Today** | `/admin` works on marketing host (`birkly.cloud/admin`) |
| **Problem** | No canonical CMS entry; phishing/UX confusion; optional hardening gap |
| **Target** | Non-CMS hosts redirect `/admin` → CMS URL (configurable strict mode) |
| **Milestone** | P43-M6 |

## G6 — Onboarding does not ask “where is your website?”

| | |
|---|---|
| **Today** | Wizard creates admin; optional starters; no CMS vs public URL capture |
| **Problem** | Two-domain Dokploy setup is tribal knowledge |
| **Target** | Steps: CMS URL · public site (external / project / none) · copy-paste client script |
| **Milestone** | P43-M4 |

## G7 — Docker docs describe `cms.` convention, not product settings

| | |
|---|---|
| **Today** | `docs/deployment/docker.md` documents two-domain + `cms.` pattern |
| **Problem** | Docs reinforce hack instead of settings model |
| **Target** | Docs + Birkly-Docs describe Hosting settings; Dokploy Internal Path always `/` |
| **Milestone** | P43-M7, P43-M9 |

## G8 — No automated tests for host roles

| | |
|---|---|
| **Today** | `tests/site_domain_test.php` covers origin helpers only |
| **Problem** | Regressions on `cms.` hack undetected |
| **Target** | `tests/hosting_urls_test.php` + HTTP routing fixtures |
| **Milestone** | P43-M8 |

---

## Evidence (production, 2026-07-25)

- `cms.birkly.cloud/project/index.html` → 200
- `birkly.cloud` failed until Dokploy Internal Path changed from `/project` to `/`
- Root routing still depends on `cms.` prefix, not settings
