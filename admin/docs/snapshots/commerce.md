# Commerce plugin

The **Commerce** plugin (`plugins/commerce/`) is Birkly’s content-first store: payment providers, the **Commerce fieldtype**, catalog, cart, checkout, orders, customers, inventory, promotions, gift cards, tax providers, shipping rate providers, and **PostPurchaseRouter** fulfillment — all driven by CMS entries.

**Commerce 0→1 complete (P54 P3 / 1.0.29):** hybrid cart + split checkout, MCP/Agent store kit, Automation commerce recipes, and ops intelligence (cohorts, fulfillment SLA, low-stock trigger). This closes the foundation path from empty site → sellable catalog → checkout → ops. Do not expect P4 features here.

Plugin help in admin (when Commerce is active) mirrors this overview: Help → Plugin extensions → Commerce (including **Hybrid cart** → `help-hybrid-cart`).

## Summary

Activate **Commerce** under Settings → Plugins, configure a payment provider under **Settings → Connections**, add the Commerce fieldtype to collections, and model products with **fulfillment** (ship, deliver, register, gift_card, none) plus one or more **purchase_options** (one-time and/or recurring on the same entry). Use variant **sub-entries** for SKUs. Template functions expose cart count and buy URLs on client sites.

## Beginner

**What Commerce does**

Commerce turns Birkly entries into things you can sell: physical products, digital downloads, class tickets, memberships, and sellable gift cards — without a separate e-commerce product database. Edit content in the CMS; Commerce indexes sellable units for checkout.

**Two settings on each sellable item**

| Setting | Plain language |
|---------|----------------|
| **Fulfillment** | What happens after someone pays (ship a box, send a download, register for a class, issue store credit, or grant access) |
| **Purchase options** | How they pay — e.g. €25 once **or** €19/month on the **same** product |

**Catalog, inventory, and orders**

| Area | Role |
|------|------|
| **Catalog** | Read model of sellable CMS entries (edit the entry, then rebuild the index if needed) |
| **Inventory** | Ledger system of record for countable stock |
| **Orders** | Commercial history with frozen money/tax snapshots from checkout |

**Stock modes** (Inventory): **Unlimited** (ignore counts) · **Simple** (one row per SKU) · **Locations** (SKU stock per warehouse).

**Quick setup**

1. **Settings → Plugins** → enable **Commerce**.
2. Open **Commerce** in the sidebar → **Settings → Connections** → add Stripe, Mollie, or another provider.
3. Edit a collection schema → add field type **Commerce**.
4. Create an entry with price, fulfillment, and purchase options.
5. On your website, add a buy button using `{commerce_buy_url entry_id collection}`.

**Variants (sizes, options)**

For products with multiple SKUs (size, grind, color):

1. Create a **parent** product entry with photos and description.
2. Add a **variants** sub-collection.
3. Each **variant sub-entry** gets its own Commerce field (price, SKU, stock).

**After payment**

Commerce routes each order line by fulfillment:

- **Ship** — Manual export or EasyPost tracker/label; admin can create partial / multi-package shipments
- **Deliver** — secure download link emailed
- **Register** — attendee recorded, capacity reduced
- **Gift card** — store-credit codes issued (face value = unit price)
- **None** — tier grant or automation (memberships)

## Advanced Users

**Content model (v2)**

```json
{
  "fulfillment": "ship",
  "tax_class": "standard",
  "weight_grams": 250,
  "purchase_options": [
    { "id": "once", "label": "Buy once", "billing": "one_time", "price_cents": 1499, "currency": "eur" },
    { "id": "sub", "label": "Subscribe", "billing": "recurring", "interval": "month", "price_cents": 1299, "currency": "eur" }
  ],
  "quantity_available": null,
  "sku": "SKU-250G"
}
```

`quantity_available` is a catalog projection of Inventory **Available** (or null for unlimited / capacity for `register`). Inventory is the ledger SoR for countable SKUs. `tax_class` defaults to `standard`; rates come from **Settings → Tax**. `weight_grams` is required on `ship` lines when EasyPost live rates are enabled.

**PostPurchaseRouter** — idempotent handler keyed on `order_id` + line; fires typed automation triggers including `commerce_physical_fulfilled`, `commerce_digital_delivered`, `commerce_event_registered`, `commerce_gift_card_issued`, `commerce_membership_started`. Shipments also fire `commerce_shipment_created` / `commerce_order_shipped` when notify is enabled; EasyPost tracking fires `commerce_tracking_updated`.

**Admin areas** (Commerce **1.0.29**)

| Tab | Purpose |
|-----|---------|
| **Dashboard** | Revenue, AOV, new vs returning, first-order cohorts, needs-shipping / SLA breaches, avg fulfillment time, top products, affiliates |
| **Orders** | Paid orders, fulfillment state, frozen money/tax lines; **Draft orders / Quotes** include a cart-free editor |
| **Customers** | Email-keyed profiles, CSV import, order/subscription timeline; optional User Management link; no passwords |
| **Catalog** | Indexed read model of sellable CMS entries |
| **Inventory** | Stock ledger: Unlimited, Simple, or Locations; low-stock threshold feeds `commerce_low_stock` |
| **Subscriptions** | Recurring purchases and lifecycle actions |
| **Discounts** | Promotions v2: usage limits, min purchase, date window, free shipping, stack rules |
| **Gift Cards** | Admin-issued store credit; sellable products use fulfillment `gift_card` |
| **Settings** | Connections, General (incl. fulfillment SLA hours), Fieldtypes, Mappings, Import & migrate, Shipping, Tax, Fulfillment, Activity, Documentation |

**Differentiation (P3 / 1.0.29)**

- **Hybrid cart:** mix ship + deliver + register + subscribe; split checkout by billing group — see [Hybrid cart](../commerce-hybrid-cart.md) and plugin `help-hybrid-cart`.
- **Store kit:** MCP/Agent playbook topic **`commerce-store-kit`** seeds products + checkout — see [Store kit](../commerce-store-kit.md).
- **Automation recipes:** import post-purchase, dunning, low-stock, shipped starters — see [Commerce recipes](../../05-automation/commerce-recipes.md).
- **Ops intelligence:** Dashboard cohorts + fulfillment SLA (`commerce_fulfillment_sla_hours`, default 48) + `commerce_low_stock` — see [Analytics](../commerce-analytics.md).

**Provider honesty (P2, still current)**

- **Tax:** Manual or Stripe Tax (orthogonal to charge PSP). Frozen `tax_lines` on paid orders. Optional B2B reverse charge via `vat_id` — no live VIES.
- **Shipping rates:** Manual zones or EasyPost live quotes; fallback to zones on live failure; weight required for EasyPost.
- **Fulfillment:** Manual / Export (default) or EasyPost (push + tracking webhook). ShipStation is an internal stub and is not offered.
- **Draft orders:** Admin editor under Orders — create/edit lines without a cart, preview tax/shipping, save & send accept link.
- **Import & migrate:** Shopify cutover, CSV, and live product import adapters (Beta). Legacy Shop / Events migration appears only when detected.
- **Gift cards:** Admin-issued **and** sellable products (`fulfillment: gift_card` issues codes on pay).

**Plugin docs shelf:** `register_plugin_docs('commerce', …)` ships `plugins/commerce/docs/` for AI, Admin Help, and MCP (`birkly://docs/plugin/commerce`).

**Public API:** `/api/index.php?route=commerce` — cart get/add/update/remove/checkout; cart payload may include `split_required` / `groups[]`; checkout selects `purchase_option_id` (and `group_key` + `checkout_session_id` when split) and collects shipping/attendee/VAT per fulfillment.

**Legacy:** P6.4 flat schema (`price_cents` + `billing`) migrates to single purchase_option + inferred fulfillment.

## Related

- [Commerce hybrid cart](../commerce-hybrid-cart.md) — ship+deliver+register+subscribe / split checkout
- [Commerce store kit](../commerce-store-kit.md) — MCP/Agent `commerce-store-kit`
- [Automation Commerce recipes](../../05-automation/commerce-recipes.md) — import post-purchase, dunning, low-stock, shipped
- [Commerce analytics](../commerce-analytics.md) — Dashboard cohorts, SLA, low-stock
- [Commerce fieldtype](../commerce-fieldtype.md)
- [Commerce customers](../commerce-customers.md)
- [Commerce promotions](../commerce-promotions.md)
- [Commerce gift cards and draft orders](../commerce-gift-cards-quotes.md)
- [Commerce tax](../commerce-tax.md)
- [Commerce shipping rates](../commerce-shipping.md)
- [Commerce fulfillment](../commerce-fulfillment.md)
- [Commerce template functions](../../03-templating/commerce-functions.md)
- [Commerce subscriptions](../commerce-subscriptions.md) — upgrades and dunning
- [Shopify cutover playbook](../commerce-shopify-cutover.md) — one-time Shopify transfer (not perpetual sync)
- [Commerce migration wizard](../commerce-migration-wizard.md) — Shop/Events import (when detected)
- [Commerce live import](../commerce-live-import.md) — Shopify/Woo/Etsy/Square API (Beta)
- [Commerce multi-currency](../commerce-multi-currency.md)
- [Commerce inventory webhooks](../commerce-inventory-webhooks.md)
- [Commerce warehouse stock](../commerce-warehouse-stock.md) — Inventory modes
- [Commerce membership wiring](../commerce-membership.md)
- [Commerce CSV import](../commerce-import.md)
- [Plugin security denylist](../plugin-security-denylist.md)
- [Coexistence with Shop & Events](../commerce-coexistence.md)
