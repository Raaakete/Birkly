# P13 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P13 platform monetization & production hardening  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 13  
**Closure:** 2026-07-07 (P15-M8 combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P13-M0** | Gap register, SSO threat model | pass | `P13-platform-gap-register.md`, `P13-sso-threat-model.md` |
| **P13-M1** | Cloud SSO optional link | pass | `tests/cloud_account_link_test.php` |
| **P13-M2** | Paid official tiers | pass | `tests/marketplace_paid_official_test.php` |
| **P13-M3** | Subscription licenses | pass | `tests/marketplace_subscription_license_test.php` |
| **P13-M4** | Health alerts + smoke v2 | pass | `tests/health_alert_test.php` |
| **P13-M5** | Code hygiene | pass | `P13-deferred-todos.md` |
| **P13-M6** | kekaba staging | pass | `scripts/staging-smoke-kekaba.sh` |
| **P13-M7** | Docs | pass | Birkly-Docs monitoring, account, marketplace guides |

## Cloud SSO link

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SSO-01 | Link cloud account from settings | pass | `core/cloud_account.php` + Account UI |
| SSO-02 | Unlink restores local-only | pass | unlink clears marketplace owner sync |
| SSO-03 | Local login works without link | pass | `cloud_account_link_test.php` |
| SSO-04 | `cloud_account_link_test.php` | pass | suite PASS |

## Paid official tiers

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PO-01 | Paid official requires license | pass | `birkly-seo-basics` paid offer |
| PO-02 | Free official one-click unchanged | pass | commerce/events free install |
| PO-03 | Checkout issues license key | pass | Marketplace checkout + license store |
| PO-04 | `marketplace_paid_official_test.php` | pass | Birkly + Marketplace suites PASS |

## Subscription licenses

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SL-01 | Subscription checkout creates active license | pass | `birkly-analytics-pro` subscription |
| SL-02 | Cancellation revokes license | pass | Stripe webhook `customer.subscription.deleted` |
| SL-03 | CMS blocks update when lapsed | pass | `birkly_marketplace_check_plugin_update()` |
| SL-04 | `marketplace_subscription_license_test.php` | pass | suite PASS |

## Production ops

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| OPS-01 | Health error triggers alert hook | pass | `core/health_alerts.php` mock test |
| OPS-02 | `production-smoke.sh` validates extended health | pass | structured checks catalog |
| OPS-03 | Marketplace health parity | pass | Marketplace `core/health.php` |
| OPS-04 | `health_alert_test.php` | pass | suite PASS |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pass | 121 suites PASS |
| T-02 | Marketplace `php tests/run.php` | pass | 15 suites PASS |
| T-03 | `./scripts/ci-check.sh` | pass | PASS |
