# Shopify → Birkly cutover playbook

One-time transfer from Shopify into Birkly Commerce: products (with variants and images), customers, and **archived** order history. **Cutover is not perpetual sync.** For ongoing product-only sync after you stay on both platforms, use [Commerce live import](commerce-live-import.md) (Beta).

## Summary

**Shopify cutover** lives under **Commerce → Settings → Import & migrate → Shopify cutover**. Save Shopify credentials on the Shopify beta card in the same Import & migrate area, then run **Test connection → Dry-run → Import cutover**. Dry-run never writes catalog data. Import can be rolled back with **Rollback last batch**. Customers import by email (no passwords). Archived orders are commercial history only — they do **not** re-fulfill, decrement stock, capture payment, or fire paid Automation emails.

## Cutover vs live sync

| Path | Purpose |
|------|---------|
| **Shopify cutover** | Planned move off Shopify: products + customers + archived orders, with dry-run and rollback |
| **Live store sync (Beta)** | Ongoing product catalog sync (Shopify and other adapters) — not customers/orders |
| **CSV import** | One-time product rows from export files — see [Commerce CSV import](commerce-import.md) |
| **Legacy migration wizard** | Birkly Shopping Cart / Events → Commerce — see [Commerce migration wizard](commerce-migration-wizard.md) |

Admin path for cutover: **Commerce → Settings → Import & migrate → Shopify cutover**.

## Beginner

### Before you start

1. Activate **Commerce** (Settings → Plugins).
2. Configure a payment provider under **Settings → Connections** if you will take new orders in Birkly after cutover.
3. Optionally activate **User Management** if buyers will get accounts later (invite / password-reset only — Shopify passwords are never imported).
4. Back up `content/` and `data/` before a production import.
5. Freeze Shopify catalog edits for the window you are transferring so dry-run counts match import.

### Steps 1–3 (solid)

#### 1. Prep / connect

1. In Shopify Admin, create a **custom app** (Admin API) with **read** access to **products**, **customers**, and **orders**.
2. Copy the **Admin API access token** and shop domain (`your-store.myshopify.com`).
3. In Birkly: **Commerce → Settings → Import & migrate**.
4. On the **Shopify** beta card under Live store sync, save **shop domain**, **access token**, target **collection**, and default **currency**. Cutover reuses these credentials.
5. Open the **Shopify cutover** panel above the live sync cards.
6. Click **1. Test connection** and confirm the API responds.

Do not treat this as a standing two-way sync. Credentials are for transfer (and optional later Beta product sync), not for Birkly writing back to Shopify.

#### 2. Map the content model

Decide how Shopify objects become Birkly content before you import.

| Shopify | Birkly |
|---------|--------|
| Product | Parent CMS entry in the target collection (Commerce field) |
| Variants | Variant **sub-entries** (same pattern as CSV / live product import) |
| Images | First image URL mapped onto the product row (media ingest when configured) |
| Customer | Commerce **Customers** profile (`source: shopify`, email as unique key) |
| Order (history) | Commerce order with `status: archived` and `source: shopify_archive` |

**Passwords are never imported.** Existing Shopify login credentials do not move. If you use User Management, invite customers or send password-reset links after go-live.

Pick a **target collection** that already has the Commerce fieldtype (or create one during import setup). Align SKUs and parent/variant relationships with how you want Catalog and Inventory to read after import — see [Commerce warehouse stock](commerce-warehouse-stock.md) for stock modes.

#### 3. Dry-run product import

1. In **Shopify cutover**, set **Resources** to **Products only** for the first pass (or **All** when you are ready for a combined run).
2. Click **2. Dry-run**. Review product counts and sample titles/SKUs. No catalog entries are written.
3. When samples look right, click **3. Import cutover** and confirm. Import requires a dry-run token or an explicit confirm.
4. Continue with **Customers only**, then **Archived orders only**, or run **All** once you trust the mapping.

After products land, open **Catalog** (and CMS entries) to spot-check prices, SKUs, variants, and images before you import history.

### Customers and archived orders (same panel)

Use the same **Resources** selector and dry-run → import flow:

- **Customers only** — upserts Commerce customer profiles by email; tags and Shopify customer id are retained when present; **no passwords**.
- **Archived orders only** — writes historical orders tagged as Shopify archive. Idempotent on Shopify order id (re-import skips existing).

**Archived orders do not re-fulfill.** They skip payment capture, inventory decrement/reservation, PostPurchaseRouter fulfillment, and paid Automation emails. Treat them as read-only commercial history for support and reporting.

### Rollback

If a batch looks wrong:

1. Click **Rollback last batch** in the Shopify cutover panel (enabled after a successful import), or
2. Call the admin API action `shopify_cutover_rollback` with the `batch_id` from the import result.

Rollback removes customers and archived orders created by that cutover batch and rolls back the linked product import when recorded. Prefer rollback over hand-deleting mixed catalog rows.

### Steps 4–11 (checklist outline)

These steps complete a full merchant move. Steps **4–5** are available in the cutover panel today; **6–11** are the remaining go-live checklist. Cutover itself does **not** import gift-card balances, discount rules, tax class mappings, or open fulfillments — recreate those in Commerce after products/customers land (see [promotions](commerce-promotions.md), [tax](commerce-tax.md), [gift cards](commerce-gift-cards-quotes.md), [fulfillment](commerce-fulfillment.md)).

| Step | Checklist |
|------|-----------|
| **4. Customers** | Dry-run → import customers; verify profiles under Commerce Customers; plan invite / password-reset (no password migration). |
| **5. Orders archive** | Dry-run → import archived orders; confirm status `archived`, no stock movement, no fulfillment emails; spot-check totals and line items. |
| **6. Verify catalog + inventory** | Rebuild catalog index if needed; set Inventory mode (Unlimited / Simple / Locations); reconcile on-hand vs Shopify before go-live. |
| **7. Memberships / purchase options** | Map Shopify subscriptions or memberships to Commerce `purchase_options` and Settings → Mappings; test one recurring or membership SKU if used. |
| **8. Storefront / checkout smoke** | Rebuild storefront on Birkly (`commerce-storefront.js` / templates — not Liquid); test ship, deliver, register, and membership paths you sell. |
| **9. Gift cards / credits** | Recreate or migrate balances only when your Commerce version supports it; disable Shopify gift-card codes at cutover so they are not double-spent. *(Balance import is not claimed as shipped with cutover alone.)* |
| **10. Discounts + DNS cutover** | Recreate critical discount codes in Commerce; put Shopify in password / closed mode; point domain to Birkly; smoke-test checkout on the live host. |
| **11. SEO + hypercare** | Publish redirects CSV (old Shopify URLs → Birkly); reconcile inventory daily for the first week; disable Shopify apps that still charge fees; keep Shopify admin read-only as needed. |

## Advanced Users

### Admin UI actions

| Control | Behavior |
|---------|----------|
| **1. Test connection** | `shopify_cutover_test` — validates saved Shopify credentials |
| **2. Dry-run** | `shopify_cutover_dry_run` — preview counts/samples; returns `dry_run_token` / `batch_id`; no catalog writes |
| **3. Import cutover** | `shopify_cutover_import` — requires `confirm: true` or `dry_run_token`; resources: `products`, `customers`, `orders`, or `all` |
| **Rollback last batch** | `shopify_cutover_rollback` — `batch_id`; deletes created customers/orders; rolls back linked product import |

### Resource flags

```text
products | customers | orders | all
```

Comma-separated combinations (for example `products,customers`) are accepted by the API. The admin select exposes common presets.

### Archived order invariants

Imported archive orders are created through the order store only (not checkout). Markers include:

- `status: archived`
- `source: shopify_archive`
- `archive_import` / `skip_fulfillment` / `skip_inventory` / `skip_automation`
- note author `shopify_cutover`

Fulfillment spies and PostPurchaseRouter must not run on archive import.

### Customer upsert

- Unique key: email
- `source: shopify`
- Password / hash fields are stripped before upsert
- Order import may upsert a lightweight customer from the order email when missing

### Out of scope / not claimed here

Cutover does **not** include:

- Perpetual Shopify ↔ Birkly sync of customers or orders
- Shopify password migration
- Gift-card **balance** import (recreate cards in Commerce; sellable `gift_card` products are separate)
- Discount / promotion rule import
- Tax class or shipping zone import
- Open / unfulfilled shipment migration
- Automatic Liquid → Birkly theme conversion

## Related

- [Commerce plugin overview](plugins/commerce.md)
- [Commerce plugin reference](commerce-plugin.md)
- [Commerce customers](commerce-customers.md)
- [Commerce live import](commerce-live-import.md) — Beta product sync (not cutover)
- [Commerce CSV import](commerce-import.md)
- [Commerce migration wizard](commerce-migration-wizard.md) — legacy Birkly Shop / Events
- [Commerce warehouse stock](commerce-warehouse-stock.md)
- [Commerce inventory webhooks](commerce-inventory-webhooks.md)
