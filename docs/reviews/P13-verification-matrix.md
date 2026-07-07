# P13 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P13 platform monetization & production hardening  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 13  
**Handoff:** `docs/reviews/P13-P15-lead-engineer-handoff.md`  
**Closure:** Target P15-M8 (combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P13-M0** | Gap register, SSO threat model | pending | `P13-platform-gap-register.md` |
| **P13-M1** | Cloud SSO optional link | pending | `tests/cloud_account_link_test.php` |
| **P13-M2** | Paid official tiers | pending | `tests/marketplace_paid_official_test.php` |
| **P13-M3** | Subscription licenses | pending | `tests/marketplace_subscription_license_test.php` |
| **P13-M4** | Health alerts + smoke v2 | pending | `tests/health_alert_test.php` |
| **P13-M5** | Code hygiene | pending | `P13-deferred-todos.md` |
| **P13-M6** | kekaba staging | pending | `scripts/staging-smoke-kekaba.sh` |
| **P13-M7** | Docs | pending | Birkly-Docs P13 guides |

## Cloud SSO link

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SSO-01 | Link cloud account from settings | pending | |
| SSO-02 | Unlink restores local-only | pending | |
| SSO-03 | Local login works without link | pending | |
| SSO-04 | `cloud_account_link_test.php` | pending | |

## Paid official tiers

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PO-01 | Paid official requires license | pending | |
| PO-02 | Free official one-click unchanged | pending | |
| PO-03 | Checkout issues license key | pending | |
| PO-04 | `marketplace_paid_official_test.php` | pending | |

## Subscription licenses

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SL-01 | Subscription checkout creates active license | pending | |
| SL-02 | Cancellation revokes license | pending | |
| SL-03 | CMS blocks update when lapsed | pending | |
| SL-04 | `marketplace_subscription_license_test.php` | pending | |

## Production ops

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| OPS-01 | Health error triggers alert hook | pending | |
| OPS-02 | `production-smoke.sh` validates extended health | pending | |
| OPS-03 | Marketplace health parity | pending | |
| OPS-04 | `health_alert_test.php` | pending | |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pending | |
| T-02 | Marketplace `php tests/run.php` | pending | |
| T-03 | `./scripts/ci-check.sh` | pending | |
