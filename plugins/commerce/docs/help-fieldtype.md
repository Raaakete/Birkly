# Commerce fieldtype

Add to any collection schema:

```json
{ "name": "pricing", "type": "commerce", "label": "Pricing" }
```

Collection field config: choose a **saved profile** as the field type (e.g. `commerce`, `commerce_events`, `commerce_digital`). Profiles are managed in **Commerce → Fieldtypes** — not in the collection editor.

Each profile controls: enabled fulfillment types, billing intervals (including custom values like `3_days`, `2_weeks`), optional custom merchant fields, and which sub-panels appear in the entry editor.

## Two axes (v2)

### Fulfillment

What happens **after** payment:

| Value | Use for | Checkout collects |
|-------|---------|-------------------|
| `ship` | Physical products | Shipping address |
| `deliver` | Digital downloads | Email (download link sent after pay) |
| `register` | Classes, events, workshops | Attendee name; capacity from quantity |
| `none` | Memberships, access grants | Email only |

### Purchase options

One entry can expose **multiple** payment choices:

```json
"purchase_options": [
  { "id": "once", "label": "Buy once", "billing": "one_time", "price_cents": 2500, "currency": "eur" },
  { "id": "sub", "label": "Monthly", "billing": "recurring", "interval": "month", "price_cents": 1999, "currency": "eur" }
]
```

At checkout the buyer picks **one** purchase option (and variant if applicable).

## Stored fields

| Field | Notes |
|-------|-------|
| `fulfillment` | `ship`, `deliver`, `register`, or `none` |
| `purchase_options[]` | id, label, billing, price_cents, currency, interval? |
| `quantity_available` | Integer or null (unlimited); capacity for `register` |
| `sku` | Optional; defaults to entry slug |

Fulfillment-specific panels in the editor:

- **ship** — optional weight
- **deliver** — asset URL or file reference
- **register** — uses quantity as max attendees

## Variants

Put the Commerce field on **variant sub-entries**, not only the parent:

- Parent: marketing content
- Sub-collection `variants`: each sub-entry = one SKU with its own commerce value

Catalog indexes one row per sellable unit. Simple products without variants: commerce field on the parent entry only.

## Legacy migration

Entries saved with the old flat schema (`price_cents` + `billing` only) migrate automatically to a single purchase_option with inferred fulfillment (default `ship` when unknown).

## Not on the fieldtype

- Discounts → Commerce **Discounts** tab
- Affiliate codes → `?ref=` on URLs + discount rules
- User tier grants → **Purchase mappings**

## Catalog

Saving an entry updates `catalog_index.json`. Cart and checkout read from the index, not raw collection files.
