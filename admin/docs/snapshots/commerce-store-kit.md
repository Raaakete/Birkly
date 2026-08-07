# Commerce store kit (MCP / Agent)

End-to-end starter path for seeding a physical shop: products collection, three shippable products, tax/shipping guidance, and a working cart/checkout page. AI topic: **`commerce-store-kit`**.

## Summary

P54 P3-M2 ships a **store kit** playbook for agents and MCP clients. Merchants ask an connected agent to “build a shop” or “seed products + checkout”; the agent follows `commerce-store-kit` step order instead of improvising. Depth reference remains the `commerce` playbook and this docs shelf.

## Beginner

### For merchants

1. Install and enable **Commerce** (Marketplace → birkly-commerce if needed).
2. Connect a payment provider under **Commerce → Settings → Connections** (agents cannot set secrets).
3. Ask your agent (MCP / admin AI) to seed a starter shop — e.g. “Build me an online store with three products and checkout.”
4. Confirm **Tax** (Manual) and **Shipping** zones under Commerce → Settings when the agent guides you there.
5. Rebuild **Catalog** if the index looks empty after seeding.

### For developers / agents

| Invoke | How |
|--------|-----|
| Playbook topic | `read_birkly_docs` → topic **`commerce-store-kit`** |
| MCP resource | `birkly://docs/commerce-store-kit` |
| Auto-load | Prompts about store kit, seed store, demo shop, build a shop |

Step order (do not skip ahead to HTML):

1. Ensure Commerce is active (`list_plugins` / `activate_plugin`).
2. Guide Manual tax + Manual shipping zones.
3. Create a products collection with a **`commerce`** field.
4. Create **3** published `fulfillment: ship` entries (SKU, `weight_grams`, purchase_options).
5. Write `project/` shop + cart/checkout using `commerce-storefront.js` + `{commerce_*}` template functions.
6. Smoke-check list → add to cart → checkout fields.

## Advanced Users

**Kit scope:** greenfield **physical** store smoke path. Variants, subscriptions, Stripe Tax, and EasyPost are optional later — use the `commerce` playbook and [hybrid cart](commerce-hybrid-cart.md) for mixed carts.

**AI limits:** no dedicated tax/shipping write tools; payment secrets stay in admin Connections; prefer plugin storefront helpers over custom `fetch` cart engines.

**Related AI topics:** `commerce`, `plugins-overview`, `site-building`, `web-project-patterns`.

## Related

- [Commerce plugin overview](plugins/commerce.md)
- [Commerce hybrid cart](commerce-hybrid-cart.md)
- [Commerce template functions](../03-templating/commerce-functions.md)
- [MCP connections](../01-admin/mcp-connections.md)
- [AI site builder](../01-admin/ai-site-builder.md)
