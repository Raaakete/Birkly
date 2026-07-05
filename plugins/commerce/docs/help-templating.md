# Commerce template functions

Register when Commerce is **active**. Use in client-site HTML templates (kekaba, Birkly-Website, etc.).

## Cart

| Function | Returns | Example |
|----------|---------|---------|
| `{commerce_cart_count}` | Number of items in session cart | Header badge |
| `{commerce_cart_url}` | URL to cart API endpoint | Link to cart page |

## Buy / add to cart

| Function | Arguments | Purpose |
|----------|-----------|---------|
| `{commerce_buy_url entry_id}` | entry id or slug | Checkout URL for sellable unit |
| `{commerce_buy_url entry_id collection}` | + collection slug | Disambiguate id |
| `{commerce_buy_url entry_id collection qty}` | + quantity | Default qty 1 |
| `{commerce_add_to_cart entry_id collection qty}` | Same as buy_url | Semantic alias for cart-first UX |

**Variants:** pass the **variant sub-entry** id/slug, not the parent product id.

```html
<a href="{commerce_buy_url variant_slug products 1}">Buy 250g whole bean</a>
```

## Price display

| Function | Purpose |
|----------|---------|
| `{commerce_price_from entry_id collection}` | Lowest purchase_option price for "from €X" on parent pages |

## Example product page (with variants)

```html
<h1>{title}</h1>
{content}

{for each variant in sub_entries variants}
  <div class="variant">
    <h3>{variant.title}</h3>
    <p>From {commerce_price_from variant.id products}</p>
    <a href="{commerce_buy_url variant.id products 1}">Buy</a>
  </div>
{endfor}
```

## Example class registration

```html
{if pricing.quantity_available > 0}
  <a href="{commerce_buy_url slug classes 1}">Book — €25</a>
{else}
  <p>Sold out</p>
{endif}
```

## Affiliate tracking

Append `?ref=partner_id` to any commerce URL path on your site; Commerce stores the ref in session for checkout discount rules.

## API alternative

Templates can link to `/api/index.php?route=commerce&action=checkout&...` directly; template functions build the correct query string for you.

## Related

- [Commerce fieldtype](help-fieldtype.md) — fulfillment and purchase_options on entries
- [Birkly-Docs — Commerce template functions](https://docs.birkly.com/03-templating/commerce-functions.html)
