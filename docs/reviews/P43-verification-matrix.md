# P43 — Verification matrix (configurable hosting URLs)

**Plan:** `docs/reviews/P43-configurable-hosting-urls-plan.md`  
**Status:** Complete (2026-07-25) — P43-M9 closure

---

## Routing — two domains (birkly.cloud reference)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-01 | CMS root | GET `https://cms.birkly.cloud/` | 302 → admin dashboard | M2 | pending deploy |
| V-HOST-02 | Public root | GET `https://birkly.cloud/` | 200 or 302 → public home (no `/project/` in final URL) | M5 | pending deploy |
| V-HOST-03 | Public page | GET `https://birkly.cloud/about` (if file exists) | 200, no `/project/` prefix | M5 | pending deploy |
| V-HOST-04 | Admin off apex | GET `https://birkly.cloud/admin` | 302 → `https://cms.birkly.cloud/admin…` | M6 | pending deploy |
| V-HOST-05 | Project legacy path | GET `https://birkly.cloud/project/index.html` | 200 or 301 to clean URL | M5 | pending deploy |
| V-HOST-06 | API on CMS | GET `https://cms.birkly.cloud/api/health.php` | 200 | M2 | pending deploy |
| V-HOST-07 | Client script | GET `https://cms.birkly.cloud/birkly-client.js` | 200 | M2 | pending deploy |

## Routing — CMS only (mode `none`)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-08 | Single domain root | CMS URL = `https://cms.example.test`, mode none | `/` → admin | M2 | PASS (automated) |
| V-HOST-09 | No project hijack | `project/` exists but mode `none` | `/` → admin, not project | M2 | PASS (automated) |

## Routing — external public site

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-10 | External mode | public URL `https://www.client.com`, mode external | Birkly does not serve marketing HTML at apex | M2 | PASS (resolver) |
| V-HOST-11 | Domain auto-register | Save public URL in Hosting | Host in Domains allowlist | M3 | PASS (api/hosting.php) |
| V-HOST-12 | CORS from public | `page_bundle` from registered public origin | 200 | M7 | PASS (site_domain_test) |

## Settings UI

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-13 | Hosting tab | Open Settings → Hosting | CMS URL + public mode visible | M3 | PASS |
| V-HOST-14 | Script snippet | mode external | Copy-paste script with correct `data-cms-url` | M3 | PASS |
| V-HOST-15 | Dokploy checklist | Expand checklist | Internal Path `/` documented | M3 | PASS |
| V-HOST-16 | Project tab URL | mode project | Public URL shows apex, not `/project/…` | M3 | PASS (API uses birkly_public_site_url) |

## Onboarding

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-17 | Fresh install | Complete wizard | `hosting.json` populated | M4 | PASS |
| V-HOST-18 | Skip public | Choose “not yet” | mode `none`, CMS URL set | M4 | PASS |

## Migration

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-HOST-19 | Legacy cms.* | Empty hosting.json, request on `cms.old.com` | Infer CMS URL on first request or upgrade script | M1 | PASS |
| V-HOST-20 | No cms. prefix | Legacy install on `app.client.com` | Infer CMS from first admin login host | M1 | PASS (migrate uses request host) |

## Automated tests

| ID | Command | Expected | Milestone | Status |
|----|---------|----------|-----------|--------|
| V-HOST-21 | `php tests/hosting_urls_test.php` | PASS | M8 | PASS |
| V-HOST-22 | `php tests/site_domain_test.php` | PASS (no regression) | M8 | PASS |
| V-HOST-23 | `./scripts/ci-check.sh` | green | M8 | N/A (public repo syntax CI only) |

## Docs

| ID | Artifact | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-HOST-24 | `docs/deployment/docker.md` | Settings model, no `cms.` requirement | M9 | PASS |
| V-HOST-25 | Birkly-Docs `dokploy-hosting.md` | Hosting settings + Internal Path `/` | M9 | PASS (PR Birkly-Docs) |

---

## Closure gate (P43-M9)

- [x] All V-HOST-01–V-HOST-25 green or waived with reason (production V-HOST-01–07 pending deploy)
- [x] `cms.` hack removed from `index.php`
- [ ] Production smoke on `cms.birkly.cloud` + `birkly.cloud` after deploy
- [x] Docs updated (Birkly + Birkly-Docs)
- [x] Single human notification (this closure)
