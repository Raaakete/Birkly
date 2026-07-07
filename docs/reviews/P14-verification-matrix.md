# P14 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P14 commerce scale & import depth  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 14  
**Closure:** 2026-07-07 (P15-M8 combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P14-M0** | Gap register, webhook threat model | pass | `P14-commerce-scale-gap-register.md` |
| **P14-M1** | Multi-currency | pass | `tests/commerce_multi_currency_test.php` |
| **P14-M2** | Inventory webhooks | pass | `tests/commerce_inventory_webhook_test.php` |
| **P14-M3** | Warehouse stock | pass | `tests/commerce_warehouse_stock_test.php` |
| **P14-M4** | Etsy + Square import | pass | `tests/commerce_etsy_square_import_test.php` |
| **P14-M5** | deny_spaces | pass | `tests/plugin_denylist_spaces_test.php` |
| **P14-M6** | Docs | pass | Birkly-Docs commerce scale guides |
| **P14-M7** | Matrix green | pass | this file |

## Multi-currency

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| MC-01 | Site default currency setting | pass | `commerce_default_currency` |
| MC-02 | Per purchase option currency override | pass | purchase option `currency` field |
| MC-03 | Checkout passes currency to provider | pass | order records currency |
| MC-04 | Analytics CSV shows order currency | pass | `revenue_by_currency` |
| MC-05 | `commerce_multi_currency_test.php` | pass | suite PASS |

## Inventory webhooks

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WH-01 | Shopify HMAC verified | pass | `api/commerce_webhooks.php` |
| WH-02 | Woo signature verified | pass | Woo adapter signature check |
| WH-03 | Stock count updates from webhook | pass | SKU → warehouse/catalog update |
| WH-04 | Invalid signature rejected | pass | webhook test negative case |
| WH-05 | `commerce_inventory_webhook_test.php` | pass | suite PASS |

## Warehouse stock

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WS-01 | Paid order decrements qty | pass | `WarehouseStockStore` |
| WS-02 | Full refund restores qty | pass | refund restore path |
| WS-03 | Out-of-stock blocks checkout | pass | checkout guard |
| WS-04 | Admin stock tab | pass | Commerce admin Stock tab |
| WS-05 | `commerce_warehouse_stock_test.php` | pass | suite PASS |

## Etsy + Square import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IM-01 | Etsy mock API → entries | pass | `EtsyImportAdapter` |
| IM-02 | Square mock API → entries | pass | `SquareImportAdapter` |
| IM-03 | Scheduled sync reuses P12 service | pass | `CommerceLiveImportService` |
| IM-04 | `commerce_etsy_square_import_test.php` | pass | suite PASS |

## Denylist spaces

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| DS-01 | Settings UI manage deny_spaces | pass | Settings Security tab |
| DS-02 | Denylisted space blocks plugin UI | pass | `is_resource_protected()` |
| DS-03 | `plugin_denylist_spaces_test.php` | pass | suite PASS |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pass | 121 suites PASS |
| T-02 | Commerce-related suites green | pass | 5 new P14 suites PASS |
