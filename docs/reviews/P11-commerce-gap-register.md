# P11 — Commerce gap register

**Date:** 2026-07-07 · **Phase:** P11 commerce production  
**Baseline:** P8 commerce ops complete — subscriptions v1, CSV import, discounts, no upgrades/dunning/gift cards/quotes/analytics/migration wizard  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 11

---

## Gap summary

| Area | P8 baseline | P11 target | Priority |
|------|-------------|------------|----------|
| Subscription upgrades | Create/cancel/webhook only | Same-product plan change + simple proration | M1 |
| Dunning | `past_due` status + trigger hook | Failed-payment record, recovery, email templates, admin retry | M2 |
| Gift cards | None | Issue/redeem/balance as checkout tender | M3 |
| B2B quotes | None | Quote → accept URL → checkout → paid | M4 |
| Analytics | Payment activity stats | Revenue widget + CSV export | M5 |
| Migration | Legacy payments→commerce only | Shop/Events → Commerce fieldtype wizard | M6 |
| Official CI publish | Manual zip build | GitHub Actions on tag → registry | M6b |

---

## P11-M1 — Subscription upgrades

| Gap | Resolution |
|-----|------------|
| No `upgrade()` on `CommerceSubscriptionService` | Add `SubscriptionUpgradeService` with plan/purchase_option change |
| Stripe plan change not implemented | `PaymentShelf::updateSubscription()` → Stripe subscription item update |
| No proration model | v1: provider-reported amount or simple unused-time credit |
| Admin UI read-only subscriptions | Add upgrade picker on subscription detail |
| Multi-product upgrade | **Out of scope** (P11-D1) |

**Edge cases:** canceled subs reject upgrade; same plan no-op; provider without subscription API → error message.

---

## P11-M2 — Dunning

| Gap | Resolution |
|-----|------------|
| `past_due` without dunning metadata | `DunningRecord` on subscription (attempt count, last_failed_at) |
| Single trigger only | Add `commerce_subscription_payment_failed`, `commerce_subscription_recovered` |
| No built-in emails | Optional templates in commerce settings → automation or direct send |
| No admin retry | Manual “retry payment” action on subscription detail |

---

## P11-M3 — Gift cards

| Gap | Resolution |
|-----|------------|
| No store credit | `GiftCardStore` flat-file codes with balance_cents, expiry, status |
| Checkout tender | Extend `DiscountEngine` or parallel `GiftCardTender` applied after coupons |
| Partial redeem | Decrement balance; persist on order snapshot |
| Separate fieldtype | **Deferred** — codes issued in admin only (P11-D3) |

**Rules:** void/expired rejected; currency must match; one card per checkout v1.

---

## P11-M4 — B2B quotes

| Gap | Resolution |
|-----|------------|
| No quote workflow | Order status `quote` with line snapshot + customer email |
| No customer accept | Tokenized URL `/commerce/quote/{token}` → review → pay |
| Approval chains | **Out of scope** — single-step accept (P11-D4) |

**States:** `quote` → `pending_payment` (accepted) → `paid` | `expired` | `canceled`

---

## P11-M5 — Analytics

| Gap | Resolution |
|-----|------------|
| Stats API payment-only | `CommerceAnalyticsService` — orders, revenue, top SKUs, affiliates |
| No export | CSV download endpoint + admin button |
| External BI | **Out of scope** (P11-D5) |

---

## P11-M6 — Migration wizard

| Gap | Resolution |
|-----|------------|
| No Shop plugin import | Read `shopping_cart` plugin data → Commerce entries |
| No Events import | Read legacy Events entries → Commerce `register` fulfillment |
| Removes legacy plugins | **No** — import only (P11-D6) |
| Idempotency | Track import run id; skip duplicate source keys |

---

## P11-M6b — Official CI publish

| Gap | Resolution |
|-----|------------|
| Manual `build-plugin-zip.sh` | GitHub Actions workflow on `v*` tag |
| Registry upload manual | Action posts artifact to marketplace registry API |
| Third-party GitHub App | **P12-M1** — official only in P11 |

---

## Dependencies

- P11-M2 depends on P11-M1 subscription model stability (shared webhook paths)
- P11-M4 quotes may use gift cards at checkout (after M3)
- P11-M6 independent of M1–M5 except commerce fieldtype stable

---

## Test suites to add

| Suite | Milestone |
|-------|-----------|
| `commerce_subscription_upgrade_test.php` | M1 |
| `commerce_dunning_test.php` | M2 |
| `commerce_gift_card_test.php` | M3 |
| `commerce_quote_test.php` | M4 |
| `commerce_analytics_test.php` | M5 |
| `commerce_migration_wizard_test.php` | M6 |
