# Commerce fulfillment

Physical (`ship`) fulfillment for Commerce **1.0.25+** / current **1.0.27**: selectable **Manual / Export** (default) or **EasyPost** live push + tracking webhook. Supports **partial ship**, **multi-package** shipments, **packing slips**, and Automation notify.

## Summary

**Settings → Fulfillment** offers **Manual / Export** and **EasyPost**. Manual remains the default. EasyPost reuses the API key from **Settings → Shipping**, creates a tracker/label on `create_shipment`, and accepts a public `platform=easypost` tracking webhook. **ShipStation** stays an internal stub and is **not** merchant-selectable.

## Beginner

### Choose an adapter

1. Open **Commerce → Settings → Fulfillment**.
2. Set **Active adapter** to **Manual / Export** or **EasyPost**.
3. For EasyPost: ensure the Shipping API key is saved; optionally set a **webhook secret** and copy the webhook URL shown in the panel.
4. Save.

### Create a shipment (Manual or EasyPost)

1. Open **Commerce → Orders** → order detail.
2. For `ship` lines with remaining quantity, create a shipment:
   - Select line(s) and quantities (partial OK), or ship all remaining.
   - Optional tracking number, carrier, **package count** (≥ 1) — EasyPost may fill tracking from the push.
   - Optional **notify customer**.
3. Repeat for additional packages / remaining lines.

With **EasyPost** active, `create_shipment` calls EasyPost `pushOrder` (tracker/label). Fulfillment status per line moves unfulfilled → partial → shipped.

### Packing slips

From the order detail (or bulk actions where available), generate packing slips for a shipment or remaining unshipped items. Print/PDF opens in a new window — allow pop-ups if blocked.

### Tracking webhook (EasyPost)

Point EasyPost tracker events at the Commerce webhook URL with `platform=easypost`. Updates write tracking status onto the shipment and can fire Automation `commerce_tracking_updated`. Use the configured webhook secret when verifying signatures.

### Automation notify

When **notify customer** is set on a shipment (and on tracking updates):

| Trigger | When |
|---------|------|
| `commerce_shipment_created` | Each new shipment record |
| `commerce_order_shipped` | Customer-facing shipped notify (when notify is enabled) |
| `commerce_tracking_updated` | EasyPost tracker webhook updates shipment status |

Wire email/SMS workflows in Automation against these triggers. See [Triggers](../05-automation/triggers.md).

## Advanced Users

**Resolver:** `FulfillmentAdapterResolver` — merchant list is `manual` | `easypost` only (`listAvailableAdapters` omits ShipStation).

**Admin API:**

| Action | Purpose |
|--------|---------|
| `create_shipment` | Partial/full ship; EasyPost adapter runs `pushOrder` when active |
| `packing_slips` | HTML packing slip for order ids / shipment |
| `update_order_shipment` / `bulk_mark_shipped` | Legacy/simple ship helpers |

**Order shape:** `shipments[]` with `id`, `items`, `package_count`, tracking/carrier, `notify_customer`; EasyPost may add `source: easypost`, `easypost_tracker_id`. Lines track `quantity_shipped` and `fulfillment_status`.

Only lines with `fulfillment: ship` are shippable. Digital, register, gift_card, and none lines use PostPurchaseRouter instead.

**Webhook:** `api/commerce_webhooks.php` with `platform=easypost` → `EasyPostTrackingWebhookService`.

## Related

- [Commerce shipping rates](commerce-shipping.md)
- [Commerce plugin overview](plugins/commerce.md)
- [Automation triggers](../05-automation/triggers.md)
- [Commerce warehouse stock](commerce-warehouse-stock.md)
