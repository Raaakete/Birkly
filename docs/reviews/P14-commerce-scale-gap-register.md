# P14 — Commerce scale gap register

**Date:** 2026-07-07 · **Phase:** P14 commerce scale & import depth  
**Threat model:** `docs/reviews/P14-webhook-threat-model.md`  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 14

---

## Gap summary

| Area | P12 baseline | P14 target | Milestone |
|------|--------------|------------|-----------|
| Currency | Single default per import | Site default + per purchase option | M1 |
| Inventory sync | Scheduled pull only | Shopify/Woo webhooks | M2 |
| Stock model | `quantity_available` on bundle | Warehouse SKU store | M3 |
| Import platforms | Shopify/Woo live API | + Etsy + Square live API | M4 |
| Denylist spaces | Stored, not enforced | UI + hook enforcement | M5 |

---

## P14-M1 — Multi-currency

| Gap | Resolution |
|-----|------------|
| Single currency checkout | `commerce_default_currency` setting |
| Per-product currency | Purchase option `currency` field |
| No FX engine | P14-D1: charge currency = display currency |

---

## P14-M2 — Inventory webhooks

| Gap | Resolution |
|-----|------------|
| No real-time stock | `api/commerce_webhooks.php` endpoints |
| Signature trust | HMAC verification per platform |
| SKU mapping | External SKU → commerce entry / warehouse row |

---

## P14-M3 — Warehouse stock

| Gap | Resolution |
|-----|------------|
| No warehouse model | `WarehouseStockStore` single warehouse |
| Pay/refund lifecycle | Decrement on paid; restore on refund |
| Checkout guard | Block when qty insufficient |

---

## P14-M4 — Etsy + Square

| Gap | Resolution |
|-----|------------|
| CSV presets only | `EtsyImportAdapter`, `SquareImportAdapter` |
| Scheduling | Reuse `CommerceLiveImportService` |

---

## P14-M5 — deny_spaces

| Gap | Resolution |
|-----|------------|
| Stub in settings | Functional UI + `is_resource_protected()` for spaces |
