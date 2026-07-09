# Commerce warehouse stock

Single-warehouse SKU inventory with decrement on paid orders and restore on full refunds (Phase 14).

## Summary

**Warehouse stock v1** tracks `quantity_on_hand` per SKU in one logical warehouse. Paid orders decrement stock; full refunds restore it. Checkout blocks or warns when stock is insufficient. Live import and inventory webhooks can sync the same SKU rows.

## Beginner

**Warehouse vs catalog quantity**

- **Catalog** `quantity_available` on a Commerce entry is what customers see on the product.
- **Warehouse** stock is SKU-level inventory used for fulfillment accuracy and checkout guards.
- When both exist for a SKU, webhooks and import update both; checkout uses warehouse counts when a warehouse row is present.

**View and edit stock**

1. Open **Commerce** in the admin.
2. Go to **Catalog** and open a product (or the **Stock** tab when shown).
3. Enter **SKU**, **Quantity on hand**, and optional **Low stock threshold**.
4. Save.

**Bulk import (optional)**

- Export a CSV with columns `sku`, `quantity_on_hand`, `low_stock_threshold`.
- Use **Commerce → Stock → Import CSV** when available on your build.

**Checkout behaviour**

- Adding to cart checks warehouse availability when a SKU row exists.
- Checkout fails or shows a warning if `quantity_on_hand` is less than cart quantity.
- When payment completes (`order.paid`), stock decrements automatically.
- **Full refund** restores the decremented quantity.

**Low stock**

- Set a threshold per SKU; admin lists or badges can highlight SKUs at or below threshold (exact UI varies by Commerce version).

## Advanced Users

**Store:** `data/plugins/commerce/warehouse_stock.json`

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

- `CommerceInventoryWebhookService::applyStockUpdate` updates warehouse when SKU matches
- Live import maps external SKU to catalog; warehouse rows are manual or CSV unless synced by webhook

**Limitations (v1):**

- Single warehouse only — no multi-location routing
- No reserved stock / allocation queues
- Partial refunds may not restore warehouse quantity (full refund restores)

**Tests:** `commerce_warehouse_stock_test.php`

## Related

- [Commerce plugin](plugins/commerce.md)
- [Commerce inventory webhooks](commerce-inventory-webhooks.md)
- [Commerce live import](commerce-live-import.md)
