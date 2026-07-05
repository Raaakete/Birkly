# Commerce plugin

**Extension — not part of core Birkly.** Activate Commerce under Settings → Plugins to use these features.

Commerce is Birkly’s content-first store: payment providers, catalog, cart, checkout, orders, and fulfillment — all driven by the **Commerce fieldtype** on your entries.

## Quick start

1. **Enable** Commerce in Settings → Plugins.
2. Open **Commerce** in the admin sidebar → **Connections** → add Stripe, Mollie, or another provider.
3. Add a **Commerce** field to a collection schema (`type: commerce`).
4. Create entries with pricing and fulfillment.
5. Use template functions on your client site (`{commerce_buy_url}`, `{commerce_cart_count}`, etc.).

## What you can sell

Commerce v2 uses two independent settings on each sellable entry:

| Setting | Meaning | Examples |
|---------|---------|----------|
| **Fulfillment** | What happens after payment | Ship a product, deliver a download, register for a class, grant membership |
| **Purchase options** | How the buyer pays | One-time €25, or subscribe €12/month — **both on the same item** |

A coffee bag can ship to the customer while offering **Buy once** and **Subscribe monthly** on the same variant.

## Variants (sizes, options)

For products with options (size, grind, color):

1. Create a **parent entry** with marketing copy and images.
2. Add a **variants** sub-collection on that entry.
3. Each **variant sub-entry** gets its own Commerce field (price, SKU, stock).

Your product page lists variants; the buyer picks one, then a purchase option at checkout.

## Admin areas

| Tab | Purpose |
|-----|---------|
| Connections | Payment providers |
| Catalog | Indexed sellable entries |
| Orders | Paid orders and fulfillment state |
| Discounts | Coupons and rules |
| Import | CSV import (WooCommerce, Shopify presets) |
| Mappings | Grant user tier on purchase |
| Fulfillment | Export to warehouse / ShipStation stub |
| Settings | Provider mode, shipping zones |

## After payment

**PostPurchaseRouter** handles fulfillment automatically:

- **Ship** — order goes to fulfillment export
- **Deliver** — buyer gets a secure download link
- **Register** — spot reserved, attendee recorded, capacity reduced
- **None** — tier mapping or automation only (memberships)

## Related topics

- **Commerce fieldtype** — schema, fulfillment panels, purchase options editor
- **Template functions** — cart count, buy URLs, price display on client sites

Full documentation: [Birkly-Docs — Commerce](https://docs.birkly.com/06-extensibility/plugins/commerce.html)
