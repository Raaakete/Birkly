# P34 — Verification matrix (Birkly Ops & platform production)

**Status:** Draft — populate during P34-M0→M22  
**Plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`

---

## Surface isolation

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| SURF-01 | `ops.birkly.cloud/admin` loads staff dashboard | pass | Deployed 2026-07-23 |
| SURF-02 | `ops.birkly.cloud/provider/login` returns 404 or redirects to providers host | pass | Redirects to providers host |
| SURF-03 | `ops.birkly.cloud/catalog` returns 404 or redirects | pass | Redirects to marketplace host |
| SURF-04 | `providers.birkly.cloud/provider/login` loads | pass | Deployed 2026-07-23 |
| SURF-05 | `providers.birkly.cloud/admin` blocked | pass | 404 JSON wrong_surface |
| SURF-06 | `marketplace.birkly.cloud/catalog` loads | pass | After nginx catalog fix |
| SURF-07 | `marketplace.birkly.cloud/api/v1/registry` returns JSON | pass | Deployed 2026-07-23 |
| SURF-08 | `marketplace.birkly.cloud/admin` blocked | pass | 404 JSON wrong_surface |
| SURF-09 | Session cookies isolated per surface | pending | |

---

## Public marketplace + CMS

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| MKT-01 | Official catalog seeded on production | pending | |
| MKT-02 | Stripe checkout (test or live) completes | pending | |
| MKT-03 | License activate with `installation_id` | pending | |
| MKT-04 | CMS plugins tab reaches production registry | pending | |
| MKT-05 | CMS installs official plugin ZIP to `plugins/` | pending | |
| MKT-06 | GitHub webhook delivers release to offer | pending | |

---

## Provider studio

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| PROV-01 | Provider signup on providers host | pass | providers.birkly.cloud/provider/signup |
| PROV-02 | Stripe Connect onboarding completes | pending | |
| PROV-03 | Offer create + artifact upload | pending | |
| PROV-04 | QC queue visible on Ops admin | pending | |
| PROV-05 | Approved offer on public catalog | pending | |

---

## Ops modules

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| OPS-01 | Feedback relay POST accepted (HMAC) | pass | API live on ops.birkly.cloud |
| OPS-02 | Feedback visible in Ops admin inbox | pass | /admin/feedback |
| OPS-03 | Install registry register + list | pass | M18 wire-up in CMS |
| OPS-04 | CMS updates check returns JSON | pass | Ops releases/check + version.php |
| OPS-05 | CMS owner triage hidden when relay configured | pass | M19 platform.php gate |

---

## Automated tests

| ID | Suite | Status |
|----|-------|--------|
| TEST-01 | `Birkly-Marketplace/tests/run.php` green | pending |
| TEST-02 | Surface routing unit tests added | pending |
| TEST-03 | `Birkly` marketplace + feedback tests green | pending |
| TEST-04 | `scripts/ci-check.sh` green (Birkly) | pending |

---

## Human review (M22)

| ID | Artifact | Status |
|----|----------|--------|
| HR-01 | P34 human review pack | pending |
| HR-02 | Production runbook updated | pending |
| HR-03 | DNS + TLS screenshot / checklist | pending |
