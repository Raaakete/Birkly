# P8 — Verification matrix

**Date:** 2026-07-06 · **Branch:** `main` @ `53c2070` (pre-closure)  
**Handoff:** `docs/reviews/P8-lead-engineer-handoff.md` · **Spec:** `docs/reviews/P8-reference-commerce-plan.md`

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P8-M0** | Roadmap + plan baseline | pass | `docs/roadmap/ROADMAP.md` § Phase 8 · current-state table |
| **P8-M1** | kekaba embed sync | pass | `scripts/sync-client-embed.sh` · kekaba PR #9 merged · `docs/reviews/P8-deploy-checklist.md` |
| **P8-M2** | Client templates — shop + events | pass | kekaba PR #10 · Birkly-Docs PR #14 · `shop.html`, `events.html`, `components/kekaba-shop.js` |
| **P8-M3** | Commerce ops v1 | pass | `commerce_tax_test.php` · `commerce_refund_test.php` · tax admin + checkout · refund UI · `commerce_order_receipt` / `commerce_order_admin_notify` on `order_paid` |
| **P8-M4** | Subscription lifecycle v1 | pass | `commerce_subscription_test.php` · `CommerceSubscriptionStore` · admin Subscriptions tab · webhook status → `past_due` / `canceled` |
| **P8-M5** | ContentEditor cell merge / split | pass | `fieldtypes/ContentEditor/tests/table_merge_test.php` · `table-layout.js` colspan/rowspan · layout inspector Merge/Split |
| **P8-M6** | Verification matrix, docs, closure | pass | This file · `P8-phase-closure-checklist.md` · AI context pack · roadmap complete |

## Integration flows

| Flow | Target | Result | Evidence |
|------|--------|--------|----------|
| kekaba embed has commerce + events | pass | `kekaba/Birkly-0.1/plugins/commerce`, `events` |
| Demo shop pages render products | pass | `shop-demo/kekaba_products/` · `kekaba-shop.js` |
| Demo events calendar/list | pass | `shop-demo/kekaba_events/` · `kekaba-events.js` |
| Checkout tax line (flat + country map) | pass | `commerce_tax_test.php` |
| Full + partial refund via provider shelf | pass | `commerce_refund_test.php` |
| Order paid → automation triggers | pass | `CommerceCheckoutService` enqueues receipt + admin notify |
| Recurring purchase → subscription record | pass | `commerce_subscription_test.php` |
| Admin cancel subscription | pass | `cancel_subscription` API |
| Webhook → subscription status update | pass | `webhook.php` + `CommerceSubscriptionService` |
| Table multi-cell merge → saved colspan/rowspan | pass | `table_merge_test.php` |
| Split restores grid | pass | `table_merge_test.php` |
| `data-ce-*` stripped on save | pass | existing `ContentEditorSync` pattern |
| Full harness | pass | `php tests/run.php` — **96 suites** |

## Deferred (post-P8)

- Birkly Marketplace (P7.0)
- Live Shopify/Woo API import (P7.1)
- Gift cards, B2B quotes, subscription plan upgrades, dunning automation
- Nested tables / grid-in-grid

## Human follow-up

| Check | Owner |
|-------|-------|
| Live deploy smoke on `birkly.kekaba.com` | Human — see `docs/reviews/P8-deploy-checklist.md` |
| Configure commerce tax rules + automation email workflows on staging | Human |
