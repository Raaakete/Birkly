# P11 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P11 commerce production wave  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 11  
**Handoff:** `docs/reviews/P11-lead-engineer-handoff.md`

Execute and mark at P11-M7 closure.

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P11-M0** | Gap register, matrix shell | | |
| **P11-M1** | Subscription upgrades | | |
| **P11-M2** | Dunning | | |
| **P11-M3** | Gift cards | | |
| **P11-M4** | B2B quotes | | |
| **P11-M5** | Analytics | | |
| **P11-M6** | Migration wizard | | |
| **P11-M6b** | Official CI publish | | |
| **P11-M7** | Docs, closure | | |

## Subscription upgrades

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| S-01 | Upgrade monthly → annual on active subscription | pass | | |
| S-02 | Provider shelf receives plan change | pass | | |
| S-03 | Admin UI shows new billing date | pass | | |
| S-04 | `commerce_subscription_upgrade_test.php` | pass | | |

## Dunning

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| D-01 | `invoice.payment_failed` → `past_due` | pass | | |
| D-02 | Automation trigger `commerce_subscription_payment_failed` | pass | | |
| D-03 | Recovery clears dunning | pass | | |
| D-04 | `commerce_dunning_test.php` | pass | | |

## Gift cards

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| G-01 | Issue gift card with balance | pass | | |
| G-02 | Partial redeem at checkout | pass | | |
| G-03 | Remaining balance persisted | pass | | |
| G-04 | Void/expired card rejected | pass | | |
| G-05 | `commerce_gift_card_test.php` | pass | | |

## B2B quotes

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| Q-01 | Admin creates quote from cart | pass | | |
| Q-02 | Customer accepts via token URL | pass | | |
| Q-03 | Quote converts to paid order | pass | | |
| Q-04 | `commerce_quote_test.php` | pass | | |

## Analytics

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| A-01 | Revenue widget shows period total | pass | | |
| A-02 | CSV export valid | pass | | |
| A-03 | Role-gated dashboard widget | pass | | |
| A-04 | `commerce_analytics_test.php` | pass | | |

## Migration wizard

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| M-01 | Detect legacy shopping_cart data | pass | | |
| M-02 | Preview import mapping | pass | | |
| M-03 | Import creates Commerce entries | pass | | |
| M-04 | Idempotent re-run skips duplicates | pass | | |
| M-05 | `commerce_migration_wizard_test.php` | pass | | |

## Official CI publish

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| C-01 | GitHub Actions workflow on tag | pass | | |
| C-02 | Zip uploaded to marketplace registry | pass | | |
| C-03 | CMS check_updates sees new version | pass | | |

## Suite

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| X-01 | `php tests/run.php` Birkly | pass | | |
| X-02 | Birkly-Docs commerce P11 pages | pass | | |
