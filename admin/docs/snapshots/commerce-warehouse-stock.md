# Commerce warehouse stock (Inventory)

SKU inventory with decrement on paid orders and restore on full refunds. Managed under **Commerce → Inventory**.

## Summary

**Inventory** is the stock ledger system of record. Merchants choose a **stock mode**: **Unlimited** (ignore counts), **Simple** (one row per SKU), or **Locations** (SKU stock per warehouse). Paid orders decrement stock; full refunds restore it. Checkout blocks or warns when stock is insufficient. Live import and inventory webhooks can sync the same SKU rows.

## Beginner

**Catalog vs Inventory**

- **Catalog** is a checkout read model. `quantity_available` on a Commerce entry is a **projection** of Inventory Available (or null for unlimited).
- **Inventory** is the ledger: **On hand**, **Reserved**, **Available** (on hand − reserved), and **Low threshold**.
- Edit product names and pricing on the CMS entry; adjust countable stock in **Inventory**.

**Stock modes**

| Mode | Behaviour |
|------|-----------|
| **Unlimited** | No ledger counts; catalog shows unlimited |
| **Simple** | One stock row per SKU |
| **Locations** | Stock per SKU per warehouse location |

**View and edit stock**

1. Open **Commerce** in the admin.
2. Go to **Inventory**.
3. Enter **SKU**, **On hand**, and optional **Low threshold** (and location when in Locations mode).
4. Save.

**Bulk import (optional)**

- Export a CSV with columns `sku`, `quantity_on_hand`, `low_stock_threshold`.
- Use **Commerce → Inventory → Import CSV** when available on your build.

**Checkout behaviour**

- Adding to cart checks availability when a countable SKU row exists.
- Checkout fails or shows a warning if Available is less than cart quantity.
- When payment completes (`order.paid`), stock decrements automatically.
- **Full refund** restores the decremented quantity.

**Low stock**

- Set a threshold per SKU; admin lists or badges can highlight SKUs at or below threshold.

## Advanced Users

**Store:** `data/plugins/commerce/warehouse_stock.json` (and location rows when Locations mode is active)

```json
{
  "skus": {
    "SKU-250G": {
      "sku": "SKU-250G",
      "quantity_on_hand": 42,
      "low_stock_threshold": 5,
      "updated_at": "2026-07-07T12:00:00+00:00"
    }
  }
}
```

**Class:** `WarehouseStockStore`

- `set($sku, $quantityOnHand, $lowStockThreshold?)`
- `decrement($sku, $qty)` — called from `CommerceCheckoutService` on paid order
- `restore($sku, $qty)` — on full refund
- `hasSufficient($sku, $qty)` — checkout guard

**Integration:**

- `CommerceInventoryWebhookService::applyStockUpdate` updates inventory when SKU matches
- Live import maps external SKU to catalog; inventory rows are manual, CSV, or webhook-synced

**Limitations (current):**

- Reserved-stock allocation queues beyond active holds are limited
- Partial refunds may not restore quantity (full refund restores)

**Tests:** `commerce_warehouse_stock_test.php`

## Related

- [Commerce plugin](plugins/commerce.md)
- [Commerce inventory webhooks](commerce-inventory-webhooks.md)
- [Commerce live import](commerce-live-import.md)
