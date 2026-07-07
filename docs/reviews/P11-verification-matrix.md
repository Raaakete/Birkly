# P11 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P11 commerce production wave  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 11  
**Handoff:** `docs/reviews/P11-P12-lead-engineer-handoff.md`  
**Closure:** 2026-07-07 (P12-M8 combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P11-M0** | Gap register, matrix shell | pass | `P11-commerce-gap-register.md` |
| **P11-M1** | Subscription upgrades | pass | `tests/commerce_subscription_upgrade_test.php` |
| **P11-M2** | Dunning | pass | `tests/commerce_dunning_test.php` |
| **P11-M3** | Gift cards | pass | `tests/commerce_gift_card_test.php` |
| **P11-M4** | B2B quotes | pass | `tests/commerce_quote_test.php` |
| **P11-M5** | Analytics | pass | `tests/commerce_analytics_test.php` |
| **P11-M6** | Migration wizard | pass | `tests/commerce_migration_wizard_test.php` |
| **P11-M6b** | Official CI publish | pass | `.github/workflows/publish-official-plugins.yml` |
| **P11-M7** | Docs, closure | pass | Birkly-Docs `06-extensibility/commerce-*.md` |

## Subscription upgrades

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| S-01 | Upgrade monthly → annual on active subscription | pass | `commerce_subscription_upgrade_test.php` |
| S-02 | Provider shelf receives plan change | pass | Stripe mock `updateSubscription` |
| S-03 | Admin UI shows new billing date | pass | `plugins/commerce/admin/commerce.js` upgrade panel |
| S-04 | `commerce_subscription_upgrade_test.php` | pass | `php tests/run.php` suite PASS |

## Dunning

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| D-01 | `invoice.payment_failed` → `past_due` | pass | `commerce_dunning_test.php` |
| D-02 | Automation trigger `commerce_subscription_payment_failed` | pass | automation event fired in test |
| D-03 | Recovery clears dunning | pass | `commerce_subscription_recovered` trigger |
| D-04 | `commerce_dunning_test.php` | pass | suite PASS |

## Gift cards

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| G-01 | Issue gift card with balance | pass | `GiftCardStore::issue` |
| G-02 | Partial redeem at checkout | pass | `applyGiftCardTender` |
| G-03 | Remaining balance persisted | pass | `GiftCardStore::redeem` balance update |
| G-04 | Void/expired card rejected | pass | void + validate rejection |
| G-05 | `commerce_gift_card_test.php` | pass | suite PASS |

## B2B quotes

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| Q-01 | Admin creates quote from cart | pass | `CommerceQuoteStore::create` |
| Q-02 | Customer accepts via token URL | pass | `accept(token)` |
| Q-03 | Quote converts to paid order | pass | `CommerceQuoteStore::markPaid` |
| Q-04 | `commerce_quote_test.php` | pass | suite PASS |

## Analytics

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| A-01 | Revenue widget shows period total | pass | `CommerceAnalyticsService::getSummary` |
| A-02 | CSV export valid | pass | `exportOrdersCsv` |
| A-03 | Role-gated dashboard widget | pass | `commerce_require_stats_capability()` |
| A-04 | `commerce_analytics_test.php` | pass | suite PASS |

## Migration wizard

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| M-01 | Detect legacy shopping_cart data | pass | `LegacyMigrationService::scan` |
| M-02 | Preview import mapping | pass | `migration_preview` |
| M-03 | Import creates Commerce entries | pass | `migration_import` |
| M-04 | Idempotent re-run skips duplicates | pass | second import skips |
| M-05 | `commerce_migration_wizard_test.php` | pass | suite PASS |

## Official CI publish

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| C-01 | GitHub Actions workflow on tag | pass | `publish-official-plugins.yml` |
| C-02 | Zip uploaded to marketplace registry | pass | `scripts/publish-official-plugins.sh` |
| C-03 | CMS check_updates sees new version | pass | `marketplace_client_test.php` |

## Suite

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| X-01 | `php tests/run.php` Birkly | pass | 110 suites PASS |
| X-02 | Birkly-Docs commerce P11 pages | pass | `commerce-subscriptions.md`, `commerce-gift-cards-quotes.md`, etc. |
