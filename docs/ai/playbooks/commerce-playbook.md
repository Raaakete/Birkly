# Commerce playbook

**Requires:** Commerce plugin installed and active. Read `plugins-overview` first.

Use for: online shop, cart, checkout, product variants, subscriptions, digital/physical/event fulfillment.

## Content model (two axes)

1. **fulfillment** — after payment: `ship` | `deliver` | `register` | `none`
2. **purchase_options[]** — payment choices on same SKU: one-time and/or recurring

## Typical setup order

1. User: Marketplace → Install → Enable Commerce
2. User: Commerce admin → **Connections** — payment provider (Stripe, etc.)
3. AI: `update_collection` — add Commerce fieldtype field (or user picks profile in collection editor)
4. AI: `create_entry` — product with commerce JSON value
5. AI: `write_project_file` — templates with `{commerce_buy_url}`, `{commerce_cart_count}`
6. Optional: variants as sub-collection `variants` on product entry

## Commerce field value (v2)

```json
{
  "fulfillment": "ship",
  "purchase_options": [
    {"id": "once", "label": "Buy once", "billing": "one_time", "price_cents": 1499, "currency": "eur"},
    {"id": "sub", "label": "Subscribe", "billing": "recurring", "interval": "month", "price_cents": 1299, "currency": "eur"}
  ],
  "quantity_available": null,
  "sku": "SKU-001"
}
```

## Variants pattern

- Parent entry = marketing (title, gallery, editor content)
- Sub-collection `variants` = sellable SKUs, each with own commerce field
- Templates: `{commerce_buy_url variant_id collection qty}`

## Template functions

| Function | Purpose |
|----------|---------|
| `{commerce_cart_count}` | Cart item count |
| `{commerce_cart_url}` | Cart API URL |
| `{commerce_buy_url entry_id collection qty}` | Add/buy link |
| `{commerce_price_from entry_id collection}` | "From €X" display |

## Fulfillment routing

| fulfillment | After payment |
|-------------|---------------|
| ship | Shipping workflow, physical fulfillment |
| deliver | Digital download token |
| register | Event registration, capacity |
| none | Membership / mapping only |

## Admin areas (user-operated)

Connections, Catalog, Orders, Subscriptions, Discounts, Import, Tax, Fulfillment, Activity.

## Automation hooks

Triggers: `order_paid`, `commerce_order_receipt`, `commerce_digital_delivered`, `commerce_event_registered`, etc. See `automation` playbook.

## Scenarios

| Business | fulfillment | Notes |
|----------|-------------|-------|
| Physical goods | ship | Variants for sizes |
| Digital course | deliver | One-time purchase_option |
| Yoga class | register | capacity = quantity_available |
| Membership | none | recurring purchase_option |

## AI limits

- No tools for payment provider secrets or order refunds UI
- Catalog/orders managed in Commerce admin
- AI can create product **content entries** with commerce fields

## Anti-patterns

- Events paid tickets without Commerce
- Mixing legacy shopping_cart plugin with new Commerce fieldtype on greenfield sites

## Deep reference

Active plugin: `read_birkly_docs` `commerce` loads full ai-context.md from plugin install.
