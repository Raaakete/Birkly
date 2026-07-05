# Commerce — AI context (Birkly P6.5 v2)

Content-first commerce plugin: payment providers, catalog, cart, checkout, orders, discounts, import, fulfillment.

## Content model (two independent axes)

1. **fulfillment** — what happens after payment: `ship` | `deliver` | `register` | `none`
2. **purchase_options[]** — one or more ways to pay on the same sellable unit:
   `{ id, label, billing: one_time|recurring, price_cents, currency, interval? }`

Billing is **not** coupled to fulfillment. Example: coffee ships (`ship`) with both one-time and recurring purchase options on the same variant.

## Variants

- Parent entry = marketing content (title, ContentEditor, gallery)
- Sub-collection `variants` = sellable SKUs; each variant sub-entry has its own commerce field
- Catalog indexes **one row per sellable unit** (variant sub-entry OR parent if no variants)
- Templates buy via variant sub-entry id/slug: `{commerce_buy_url variant_id collection qty}`

## Commerce fieldtype (v2 stored value)

```json
{
  "fulfillment": "ship",
  "purchase_options": [
    { "id": "buy_once", "label": "One-time", "billing": "one_time", "price_cents": 1499, "currency": "eur" },
    { "id": "subscribe", "label": "Subscribe", "billing": "recurring", "interval": "month", "price_cents": 1299, "currency": "eur" }
  ],
  "quantity_available": null,
  "sku": "SKU-250G-WB"
}
```

Conditional panels by fulfillment:
- **ship** — weight (optional), shipping address at checkout
- **deliver** — digital asset URL/file, signed download token after payment
- **register** — uses `quantity_available` as capacity; attendee name at checkout
- **none** — purchase mappings / automation only (e.g. recurring membership)

Collection field config: `commerce_settings` — enable/disable sub-panels, interval options, fulfillment presets.

## PostPurchaseRouter

Single router class switches on fulfillment after successful payment (idempotent on order_id + line_id):

| fulfillment | Action |
|-------------|--------|
| ship | Fulfillment adapter + `commerce_physical_fulfilled` automation |
| deliver | DigitalDeliveryStore signed token + `commerce_digital_delivered` |
| register | EventRegistrationStore, capacity decrement + `commerce_event_registered` |
| none | Purchase mappings; `commerce_membership_started` when recurring |

Order lines snapshot commerce value + `purchase_option_id` at checkout.

## Admin (Commerce page)

Tabs: Connections, Catalog, Orders, Discounts, Import, Mappings, Fulfillment, Activity, Settings. Shipping zones v1 for flat-rate shipping.

## Shelf services

- `commerce_shelf` — primary payment/catalog API
- `payment_shelf` — legacy alias (same instance)

## Template functions

| Function | Purpose |
|----------|---------|
| `{commerce_cart_count}` | Items in session cart |
| `{commerce_cart_url}` | Cart API URL |
| `{commerce_buy_url entry_id collection qty}` | Checkout/add URL for sellable unit |
| `{commerce_add_to_cart entry_id collection qty}` | Alias for buy URL |
| `{commerce_price_from entry_id collection}` | Lowest price display ("from €X") |

## Scenarios

- **Yoga class** — `register`, one purchase_option, capacity 20, attendee at checkout
- **Coffee** — variant sub-entries, `ship`, dual purchase_options (buy + subscribe)
- **Course** — `deliver`, one_time, download token
- **Community** — `none`, recurring, purchase mapping → user tier

## Coexistence

Legacy `shopping_cart` and `events` plugins remain; new sites use Commerce fieldtype + content model only.

## Public API

`/api/index.php?route=commerce` — cart actions: get, add, update, remove, checkout. Checkout selects purchase_option; collects shipping/attendee per line fulfillment.
