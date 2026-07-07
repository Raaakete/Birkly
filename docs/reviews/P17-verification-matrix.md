# P17 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P17 commerce pro & fulfillment depth  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 17  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** Target P19-M8 (combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P17-M0** | Gap register | pending | `P17-commerce-pro-gap-register.md` |
| **P17-M1** | Multi-warehouse | pending | `commerce_multi_warehouse_test.php` |
| **P17-M2** | Display currency / FX | pending | `commerce_display_currency_test.php` |
| **P17-M3** | Amazon + eBay import | pending | `commerce_amazon_ebay_import_test.php` |
| **P17-M4** | Bundle + shipping v2 | pending | `commerce_bundle_shipping_test.php` |
| **P17-M5** | Post-purchase audit | pending | `commerce_post_purchase_idempotency_test.php` |
| **P17-M6** | Docs | pending | Birkly-Docs |
| **P17-M7** | Matrix green | pending | this file |

## Multi-warehouse

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| MW-01 | Two warehouses CRUD | pending | |
| MW-02 | Checkout allocates from warehouse with stock | pending | |
| MW-03 | Transfer between warehouses | pending | |
| MW-04 | `commerce_multi_warehouse_test.php` | pending | |

## Display currency

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| DC-01 | Manual rate table applied to display | pending | |
| DC-02 | Checkout charges purchase-option currency | pending | |
| DC-03 | `commerce_display_currency_test.php` | pending | |

## Import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IM-01 | Amazon mock → entries | pending | |
| IM-02 | eBay mock → entries | pending | |
| IM-03 | `commerce_amazon_ebay_import_test.php` | pending | |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly commerce suites green | pending | |
