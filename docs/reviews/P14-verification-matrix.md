# P14 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P14 commerce scale & import depth  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 14  
**Handoff:** `docs/reviews/P13-P15-lead-engineer-handoff.md`  
**Closure:** Target P15-M8 (combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P14-M0** | Gap register, webhook threat model | pending | `P14-commerce-scale-gap-register.md` |
| **P14-M1** | Multi-currency | pending | `tests/commerce_multi_currency_test.php` |
| **P14-M2** | Inventory webhooks | pending | `tests/commerce_inventory_webhook_test.php` |
| **P14-M3** | Warehouse stock | pending | `tests/commerce_warehouse_stock_test.php` |
| **P14-M4** | Etsy + Square import | pending | `tests/commerce_etsy_square_import_test.php` |
| **P14-M5** | deny_spaces | pending | `tests/plugin_denylist_spaces_test.php` |
| **P14-M6** | Docs | pending | Birkly-Docs P14 guides |
| **P14-M7** | Matrix green | pending | this file |

## Multi-currency

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| MC-01 | Site default currency setting | pending | |
| MC-02 | Per purchase option currency override | pending | |
| MC-03 | Checkout passes currency to provider | pending | |
| MC-04 | Analytics CSV shows order currency | pending | |
| MC-05 | `commerce_multi_currency_test.php` | pending | |

## Inventory webhooks

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WH-01 | Shopify HMAC verified | pending | |
| WH-02 | Woo signature verified | pending | |
| WH-03 | Stock count updates from webhook | pending | |
| WH-04 | Invalid signature rejected | pending | |
| WH-05 | `commerce_inventory_webhook_test.php` | pending | |

## Warehouse stock

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WS-01 | Paid order decrements qty | pending | |
| WS-02 | Full refund restores qty | pending | |
| WS-03 | Out-of-stock blocks checkout | pending | |
| WS-04 | Admin stock tab | pending | |
| WS-05 | `commerce_warehouse_stock_test.php` | pending | |

## Etsy + Square import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IM-01 | Etsy mock API → entries | pending | |
| IM-02 | Square mock API → entries | pending | |
| IM-03 | Scheduled sync reuses P12 service | pending | |
| IM-04 | `commerce_etsy_square_import_test.php` | pending | |

## Denylist spaces

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| DS-01 | Settings UI manage deny_spaces | pending | |
| DS-02 | Denylisted space blocks plugin UI | pending | |
| DS-03 | `plugin_denylist_spaces_test.php` | pending | |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pending | |
| T-02 | Commerce-related suites green | pending | |
