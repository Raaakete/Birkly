# P43 — Verification matrix (configurable hosting URLs)

**Plan:** `docs/reviews/P43-configurable-hosting-urls-plan.md`  
**Status:** Draft — fill at P43-M9

---

## Routing — two domains (birkly.cloud reference)

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-01 | CMS root | GET `https://cms.birkly.cloud/` | 302 → admin dashboard | M2 |
| V-HOST-02 | Public root | GET `https://birkly.cloud/` | 200 or 302 → public home (no `/project/` in final URL) | M5 |
| V-HOST-03 | Public page | GET `https://birkly.cloud/about` (if file exists) | 200, no `/project/` prefix | M5 |
| V-HOST-04 | Admin off apex | GET `https://birkly.cloud/admin` | 302 → `https://cms.birkly.cloud/admin…` | M6 |
| V-HOST-05 | Project legacy path | GET `https://birkly.cloud/project/index.html` | 200 or 301 to clean URL | M5 |
| V-HOST-06 | API on CMS | GET `https://cms.birkly.cloud/api/health.php` | 200 | M2 |
| V-HOST-07 | Client script | GET `https://cms.birkly.cloud/birkly-client.js` | 200 | M2 |

## Routing — CMS only (mode `none`)

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-08 | Single domain root | CMS URL = `https://cms.example.test`, mode none | `/` → admin | M2 |
| V-HOST-09 | No project hijack | `project/` exists but mode `none` | `/` → admin, not project | M2 |

## Routing — external public site

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-10 | External mode | public URL `https://www.client.com`, mode external | Birkly does not serve marketing HTML at apex | M2 |
| V-HOST-11 | Domain auto-register | Save public URL in Hosting | Host in Domains allowlist | M3 |
| V-HOST-12 | CORS from public | `page_bundle` from registered public origin | 200 | M7 |

## Settings UI

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-13 | Hosting tab | Open Settings → Hosting | CMS URL + public mode visible | M3 |
| V-HOST-14 | Script snippet | mode external | Copy-paste script with correct `data-cms-url` | M3 |
| V-HOST-15 | Dokploy checklist | Expand checklist | Internal Path `/` documented | M3 |
| V-HOST-16 | Project tab URL | mode project | Public URL shows apex, not `/project/…` | M3 |

## Onboarding

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-17 | Fresh install | Complete wizard | `hosting.json` populated | M4 |
| V-HOST-18 | Skip public | Choose “not yet” | mode `none`, CMS URL set | M4 |

## Migration

| ID | Scenario | Steps | Expected | Milestone |
|----|----------|-------|----------|-----------|
| V-HOST-19 | Legacy cms.* | Empty hosting.json, request on `cms.old.com` | Infer CMS URL on first request or upgrade script | M1 |
| V-HOST-20 | No cms. prefix | Legacy install on `app.client.com` | Infer CMS from first admin login host | M1 |

## Automated tests

| ID | Command | Expected | Milestone |
|----|---------|----------|-----------|
| V-HOST-21 | `php tests/hosting_urls_test.php` | PASS | M8 |
| V-HOST-22 | `php tests/site_domain_test.php` | PASS (no regression) | M8 |
| V-HOST-23 | `./scripts/ci-check.sh` | green | M8 |

## Docs

| ID | Artifact | Expected | Milestone |
|----|----------|----------|-----------|
| V-HOST-24 | `docs/deployment/docker.md` | Settings model, no `cms.` requirement | M9 |
| V-HOST-25 | Birkly-Docs `dokploy-hosting.md` | Hosting settings + Internal Path `/` | M9 |

---

## Closure gate (P43-M9)

- [ ] All V-HOST-01–V-HOST-25 green or waived with reason  
- [ ] `cms.` hack removed from `index.php`  
- [ ] Production smoke on `cms.birkly.cloud` + `birkly.cloud` after deploy  
- [ ] Single human notification (digest / email per REPORTING.md)
