# Commerce inventory webhooks

Real-time stock sync from Shopify and WooCommerce on top of scheduled live import (Phase 14). Webhook payloads update catalog `quantity_available` and warehouse rows when SKUs match.

## Summary

**Inventory webhooks** receive `inventory_levels/update` (Shopify) and `product.updated` (WooCommerce) events at `api/commerce_webhooks.php`. HMAC/signature verification is required. Matching SKUs update Commerce catalog stock and optional warehouse `quantity_on_hand` without a full scheduled sync.

## Beginner

**Why webhooks**

Scheduled cron import (see [Commerce live import](commerce-live-import.md)) can lag minutes or hours. Inventory webhooks push stock changes within seconds when someone buys on Shopify/Woo or staff adjusts quantity in the external admin.

**Shopify setup**

1. In **Commerce → Settings → Import & migrate**, save a Shopify live connection (store URL + access token).
2. Open **Inventory webhooks** (or Import & migrate → Webhooks).
3. Copy the **webhook URL** and **signing secret** shown for Shopify.
4. In Shopify Admin → Settings → Notifications → Webhooks, create:
   - Topic: **Inventory levels update**
   - URL: paste Birkly webhook URL
   - Format: JSON
5. Paste Shopify's shared secret into Birkly and click **Save**.
6. Use **Rotate secret** if you regenerate the Shopify secret.

**WooCommerce setup**

1. Save a WooCommerce REST connection in Commerce import settings.
2. Copy the Woo webhook URL and secret from Birkly.
3. In WordPress → WooCommerce → Settings → Advanced → Webhooks:
   - Topic: **Product updated**
   - Delivery URL: Birkly webhook URL
   - Secret: match Birkly stored secret
4. Save on both sides.

**What gets updated**

- Commerce catalog index entries whose **SKU** matches the payload
- Warehouse stock row for the same SKU (if configured — see [Commerce warehouse stock](commerce-warehouse-stock.md))
- Entries without a matching SKU are skipped (no error to the platform)

**Security**

- Unsigned or invalid HMAC requests are rejected with HTTP 401.
- Rotate secrets from Commerce admin when staff leave or after a leak.
- Webhooks are inbound-only — Birkly never writes stock back to Shopify/Woo in v1.

## Advanced Users

**Endpoint:** `POST /api/commerce_webhooks.php?platform=shopify|woocommerce`

**Verification:**

| Platform | Header | Algorithm |
|----------|--------|-----------|
| Shopify | `X-Shopify-Hmac-Sha256` | HMAC-SHA256 of raw body, base64 |
| WooCommerce | `X-WC-Webhook-Signature` | HMAC-SHA256 of raw body, base64 |

**Service:** `CommerceInventoryWebhookService`

- `handleShopifyInventoryUpdate($payload)` — reads `sku`, `available` / `quantity`
- `handleWooProductUpdated($payload)` — reads `sku`, `stock_quantity` when `manage_stock` is true
- `applyStockUpdate($sku, $quantity, $source)` — updates catalog index + `WarehouseStockStore`

**Admin actions** (`api/commerce.php`):

- `rotate_inventory_webhook_secret` — new secret for platform
- Webhook registration helper displays callback URL per connection

**Threat model:** Extends Birkly `docs/reviews/P12-import-threat-model.md` and `P14-webhook-threat-model.md` — HMAC required, no arbitrary URL fetch from webhook handler, replay limited by platform delivery semantics.

**Scheduled import:** Webhooks complement `CommerceLiveImportService` cron; they do not replace initial product import.

**Tests:** `commerce_inventory_webhook_test.php`

## Related

- [Commerce live import](commerce-live-import.md)
- [Commerce warehouse stock](commerce-warehouse-stock.md)
- [Commerce plugin](plugins/commerce.md)
- [Webhooks](../05-automation/webhooks.md) — outgoing automation webhooks (different feature)
