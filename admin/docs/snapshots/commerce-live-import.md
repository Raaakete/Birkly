# Commerce live import — Shopify, WooCommerce, Etsy, and Square

Connect a live store and sync products into Commerce catalog entries (Phase 12–14). CSV export import remains available for all platforms. Shopify and WooCommerce also support **inventory webhooks** for real-time stock.

## Summary

**Live import** uses REST APIs to fetch products from **Shopify** (custom app access token), **WooCommerce** (REST API keys), **Etsy** (Open API v3 — shop ID, API key, OAuth access token), or **Square** (Catalog API access token). Credentials are stored encrypted in Commerce admin. The sync wizard supports **preview → import → schedule** (cron token). Products map to Commerce fieldtype values (title, SKU, price, stock, optional image URL ingest).

## Beginner

**Choose import mode**

| Mode | When to use |
|------|-------------|
| **CSV upload** | One-time export from any platform (see [Commerce import](commerce-import.md)) |
| **Live API** | Ongoing sync from Shopify, WooCommerce, Etsy, or Square |
| **Inventory webhooks** | Real-time stock for Shopify/Woo only (see [Commerce inventory webhooks](commerce-inventory-webhooks.md)) |

**Shopify setup**

1. In Shopify Admin, create a **custom app** with `read_products` scope.
2. Copy the **Admin API access token** and store domain (`your-store.myshopify.com`).
3. In Birkly: **Commerce → Import → Import products**.
4. Select **Shopify (live API)**.
5. Enter store URL and access token; click **Test connection**.
6. Choose target collection and click **Preview** then **Import**.

**WooCommerce setup**

1. In WordPress: **WooCommerce → Settings → Advanced → REST API** → Add key with **Read** permission.
2. Copy **Consumer key** and **Consumer secret** and site URL.
3. In Birkly import wizard, select **WooCommerce (live API)**.
4. Enter credentials, preview, and import.

**Etsy setup (live API)**

1. Create an Etsy app and obtain **API key** plus **OAuth access token** with `listings_r` scope.
2. Note your **Shop ID** from Etsy Seller account.
3. In Birkly: **Commerce → Import** → **Etsy (live API)**.
4. Enter shop ID, API key, and access token; **Test connection**.
5. Preview and import listings (paginated, max 100 pages in v1).

**Square setup (live API)**

1. In Square Developer Dashboard, create an application and **access token** with Catalog read scope.
2. In Birkly import wizard, select **Square (live API)**.
3. Paste access token; test, preview, and import catalog items (paginated).

**Scheduled sync**

After a successful live import, copy the **cron URL** from import settings. Call it from your server cron (for example daily) to re-sync changed products. Idempotency matches by SKU or external product id.

**Security notes**

- Use read-only API credentials where possible.
- Live import fetches product metadata only — it does not write back to Shopify/Woo.
- Credentials are encrypted at rest; never commit them to git.

## Advanced Users

**Adapters**

| Class | Endpoint pattern |
|-------|------------------|
| `ShopifyImportAdapter` | `GET /admin/api/.../products.json` (paginated) |
| `WooImportAdapter` | `GET /wp-json/wc/v3/products` (paginated) |
| `EtsyImportAdapter` | `GET /v3/application/shops/{id}/listings` (paginated) |
| `SquareImportAdapter` | `GET /v2/catalog/list?types=ITEM` (cursor paginated) |

All adapters run through `CommerceLiveImportService` with shared credential encryption and cron scheduling from P12.

**Field mapping**

| External | Commerce entry |
|----------|----------------|
| Title / name | Entry title |
| Description | Body field (when mapped) |
| SKU / variant SKU | `commerce.sku` |
| Price | `purchase_options[].price_cents` |
| Inventory | `quantity_available` |
| Handle / slug | Entry slug |
| Image URL | Optional media ingest |

**Credential store**

Encrypted in `data/plugins/commerce/` via `PaymentsCredentialStore` (separate connection type from payment providers). Admin actions:

- `save_import_connection` — platform, credentials, label
- `test_import_connection` — validates API reachability
- `import_live_preview` — paginated sample without write
- `import_live_run` — full sync
- `import_schedule_token` — signed cron token

**Threat model**

See Birkly `docs/reviews/P12-import-threat-model.md` and `P14-webhook-threat-model.md`: SSRF protection on store URLs, rate limits, HMAC on inventory webhooks, no arbitrary URL fetch from user input.

**Out of scope (v1)**

- Webhook-driven sync for Etsy/Square (Shopify/Woo inventory webhooks only)
- Order or customer import
- Automatic collection schema creation without Commerce fieldtype

## Related

- [Commerce CSV import](commerce-import.md)
- [Commerce inventory webhooks](commerce-inventory-webhooks.md)
- [Commerce warehouse stock](commerce-warehouse-stock.md)
- [Commerce plugin](plugins/commerce.md)
- [Commerce migration wizard](commerce-migration-wizard.md)
