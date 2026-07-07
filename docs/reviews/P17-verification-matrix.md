# P17 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P17 commerce pro & fulfillment depth  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 17  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** P19-M8 (combined wave) — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P17-M0** | Gap register | pass | `P17-commerce-pro-gap-register.md` |
| **P17-M1** | Multi-warehouse | pass | `commerce_multi_warehouse_test.php` |
| **P17-M2** | Display currency / FX | pass | `commerce_display_currency_test.php` |
| **P17-M3** | Amazon + eBay import | pass | `commerce_amazon_ebay_import_test.php` |
| **P17-M4** | Bundle + shipping v2 | pass | `commerce_bundle_shipping_test.php` |
| **P17-M5** | Post-purchase audit | pass | `commerce_post_purchase_idempotency_test.php` |
| **P17-M6** | Docs | pass | Birkly-Docs `06-extensibility/commerce-warehouse-stock.md`, `commerce-multi-currency.md`, `commerce-live-import.md` |
| **P17-M7** | Matrix green | pass | this file |

## Multi-warehouse

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| MW-01 | Two warehouses CRUD | pass | `WarehouseStockStore`, `commerce_multi_warehouse_test.php` |
| MW-02 | Checkout allocates from warehouse with stock | pass | `commerce_multi_warehouse_test.php` |
| MW-03 | Transfer between warehouses | pass | `commerce_multi_warehouse_test.php` |
| MW-04 | `commerce_multi_warehouse_test.php` | pass | PASS (2026-07-07) |

## Display currency

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| DC-01 | Manual rate table applied to display | pass | `commerce_display_currency_test.php` |
| DC-02 | Checkout charges purchase-option currency | pass | `commerce_display_currency_test.php` |
| DC-03 | `commerce_display_currency_test.php` | pass | PASS (2026-07-07) |

## Import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IM-01 | Amazon mock → entries | pass | `AmazonImportAdapter`, `commerce_amazon_ebay_import_test.php` |
| IM-02 | eBay mock → entries | pass | `EbayImportAdapter`, `commerce_amazon_ebay_import_test.php` |
| IM-03 | `commerce_amazon_ebay_import_test.php` | pass | PASS (2026-07-07) |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly commerce suites green | pass | 135 suites PASS incl. P17 commerce tests (2026-07-07) |
