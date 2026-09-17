# Commerce store kit playbook (P3-M2)

**Requires:** Commerce plugin installed and active. Depth reference: `commerce` playbook + plugin `docs/ai-context.md` when active.

Use when the user asks to **build a shop / online store / seed products + checkout**, or to run the **MCP / Agent store kit** end-to-end.

**Goal:** One greenfield physical store: `products` collection, **3** published shippable products, tax + shipping configured (or guided), catalog indexed, and a working `project/` cart/checkout page.

Layer on: `plugins-overview`, `commerce`, `site-building`, `web-project-patterns`, `fieldtypes`.

---

## Step order (always follow)

Do **not** skip ahead to HTML before schema + published products exist.

### 1. Ensure Commerce is active

```
list_plugins
```

| Result | Action |
|--------|--------|
| Commerce missing | Instruct user: Marketplace → **birkly-commerce** → Install → Enable. Stop until active. |
| Installed, inactive | `activate_plugin` with slug `commerce` (or guide Enable in admin) |
| Active | Continue |

Payment secrets stay in admin UI: **Commerce → Settings → Connections** (Stripe, etc.). AI cannot set provider secrets — tell the user once if checkout payment will fail without a connection.

### 2. Configure tax (manual) + shipping zones

AI has **no** dedicated tax/shipping write tools. Guide the user (or note Settings paths) before or while seeding products:

| Setting | Admin path | Starter recommendation |
|---------|------------|------------------------|
| Tax provider | **Commerce → Settings → Tax** | Provider **`manual`**; set a default rate (e.g. 19% DE / 0% for demo) |
| Tax classes | Same Tax screen | Keep default `standard` on products unless reduced/zero needed |
| Shipping provider | **Commerce → Settings → Shipping** | Provider **`manual`** (zones + flat rates); EasyPost later |
| Shipping zones | Same Shipping screen | At least one zone covering the merchant country with a flat rate |

Optional later: Stripe Tax, EasyPost live rates — not required for the store kit smoke path.

### 3. Create a `products` collection

Reuse an existing products-like collection if `list_collections` already has one with a Commerce field. Otherwise:

```json
{
  "type": "create_collection",
  "slug": "products",
  "name": "Products",
  "fields": [
    { "name": "title", "type": "text", "label": "Title", "required": true },
    { "name": "description", "type": "textarea", "label": "Description" },
    { "name": "image", "type": "image", "label": "Image" },
    { "name": "pricing", "type": "commerce", "label": "Pricing" }
  ]
}
```

Rules:

- Field `type` must be **`commerce`** (plugin fieldtype). Never invent `price` / `sku` top-level text fields as a substitute.
- Always define full `fields` before `create_entry` — never `fields: []`.
- Adapt slug/labels to the business (`menu`, `goods`, …) but keep one Commerce field on the sellable entry.
- Simple kit = commerce on the **parent** entry (no variants). For sizes/options later, see `commerce` → variants sub-collection.

Confirm with `read_collection` after create/update.

### 4. Create **3** published product entries

Seed **exactly three** (or at least three) **published** physical products with **`fulfillment: ship`**, SKUs, prices, and `weight_grams`.

Commerce field value (v2) — place under the commerce field name (example: `pricing`):

```json
{
  "fulfillment": "ship",
  "tax_class": "standard",
  "sku": "SKU-DEMO-001",
  "weight_grams": 400,
  "quantity_available": null,
  "purchase_options": [
    {
      "id": "once",
      "label": "Buy once",
      "billing": "one_time",
      "price_cents": 1499,
      "currency": "eur"
    }
  ]
}
```

Example `create_entry` payloads (adapt titles/prices to the brand):

| # | Title | sku | price_cents | weight_grams |
|---|-------|-----|-------------|--------------|
| 1 | Starter Mug | SKU-DEMO-001 | 1499 | 400 |
| 2 | House Blend 250g | SKU-DEMO-002 | 1299 | 250 |
| 3 | Gift Box Small | SKU-DEMO-003 | 2999 | 800 |

```json
{
  "type": "create_entry",
  "collection": "products",
  "title": "Starter Mug",
  "status": "published",
  "description": "Ceramic mug for everyday use.",
  "pricing": {
    "fulfillment": "ship",
    "tax_class": "standard",
    "sku": "SKU-DEMO-001",
    "weight_grams": 400,
    "quantity_available": null,
    "purchase_options": [
      { "id": "once", "label": "Buy once", "billing": "one_time", "price_cents": 1499, "currency": "eur" }
    ]
  }
}
```

Checklist per entry:

- [ ] `status: "published"`
- [ ] `fulfillment: "ship"`
- [ ] At least one `purchase_options[]` with `price_cents` + `currency`
- [ ] Unique `sku`
- [ ] `weight_grams` > 0 (needed for shipping weight / EasyPost later)
- [ ] Optional `image` if media exists — omit rather than invent broken paths

### 5. Rebuild / reindex catalog if needed

Saving an entry normally updates `catalog_index.json`. If cart/add fails with “not in catalog” or Commerce → Catalog is empty:

1. User: **Commerce → Catalog → Rebuild / Reindex** (admin button posts `rebuild_catalog` / `reindex_catalog`)
2. Or re-save one product entry after the Commerce field is confirmed

AI has no MCP catalog-rebuild tool — instruct the user when the index looks stale.

### 6. Write `project/` shop + checkout pages

Discover once:

```
get_project_info   → site_url_prefix (e.g. /project/)
list_project_files
```

Use `site_url_prefix` for CSS/page links. Every templated page needs:

```html
<script src="/birkly-client.js"></script>
<script src="/plugins/commerce/assets/commerce-storefront.js"></script>
```

Prefer plugin storefront helpers over custom cart fetch engines (`BirklyCommerce.bind`, `cartNeedsShipping`, split checkout). See `commerce` + plugin `help-templating` / `help-hybrid-cart`.

#### Catalog / shop list (`shop.html` or homepage section)

```html
{for each entry in 'products' with status = published ordered by newest}
  <article>
    <h2>{title}</h2>
    <p>{'description'}</p>
    <p>€{commerce_price_from slug products}</p>
    <button type="button"
      data-commerce-add
      data-commerce-entry="{slug}"
      data-commerce-collection="products"
      data-qty="1"
      data-commerce-redirect="cart"
      data-cart-url="{SITE_PREFIX}cart.html">
      Add to cart
    </button>
  </article>
{endfor}
<script>
  document.addEventListener('birkly:templates-processed', function () {
    if (window.BirklyCommerce) BirklyCommerce.bind(document);
  });
</script>
```

Replace `{SITE_PREFIX}` with the real `site_url_prefix` from `get_project_info` (e.g. `/project/`).

Alternative buy link: `<a href="{commerce_buy_url slug products 1}">Buy</a>`.

#### Cart + checkout (`cart.html` and/or `checkout.html`)

Minimal pattern (combine cart + checkout on one page if preferred):

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout</title>
  <link rel="stylesheet" href="{SITE_PREFIX}css/style.css">
</head>
<body>
  <header>
    <a href="{SITE_PREFIX}shop.html">Shop</a>
    <span>Cart (<span data-commerce-cart-count>{commerce_cart_count}</span>)</span>
  </header>

  <main>
    <h1>Checkout</h1>
    <p data-commerce-status></p>

    <form data-commerce-checkout-form>
      <label>Email <input type="email" name="email" required></label>

      <fieldset data-commerce-shipping-fields>
        <legend>Shipping</legend>
        <label>Name <input name="shipping_address[name]" required></label>
        <label>Line 1 <input name="shipping_address[line1]" required></label>
        <label>City <input name="shipping_address[city]" required></label>
        <label>Postal <input name="shipping_address[postal_code]" required></label>
        <label>Country <input name="shipping_address[country]" value="DE" required></label>
      </fieldset>

      <div id="payment-element"></div>
      <button type="submit">Pay</button>
    </form>
  </main>

  <script src="/birkly-client.js"></script>
  <script src="/plugins/commerce/assets/commerce-storefront.js"></script>
  <script>
    document.addEventListener('birkly:templates-processed', function () {
      BirklyCommerce.bind(document);
      BirklyCommerce.on('checkout-ready', function (data) {
        // Mount Stripe Payment Element with data.client_secret when Connections are configured.
        // After confirmPayment, if BirklyCommerce.getSplitState().nextGroupKey:
        //   await BirklyCommerce.continueSplitCheckout();
      });
    });
  </script>
</body>
</html>
```

Markup hooks (plugin-owned):

| Attribute | Role |
|-----------|------|
| `data-commerce-add` | Add line (`data-commerce-entry`, `data-commerce-collection`) |
| `data-commerce-cart-count` | Live cart count |
| `data-commerce-checkout-form` | Collects email + shipping; starts checkout |
| `data-commerce-shipping-fields` | Shown when cart has `ship` lines |
| `data-commerce-status` | Status / errors |

Tool write paths: `shop.html`, `cart.html`, `checkout.html`, `css/style.css` — **never** prefix `project/` in `write_project_file`.

### 7. Smoke checklist

Before declaring done:

| Check | Pass criteria |
|-------|----------------|
| Catalog list | Shop page lists 3 CMS products (no raw `{for each…}`) |
| Add to cart | Add updates cart count / redirects to cart |
| Checkout fields | Email + shipping fields visible for ship cart |
| Tax / ship | User confirmed Tax=`manual` + at least one shipping zone (or documented as pending) |
| Scripts | `birkly-client.js` + `commerce-storefront.js` on shop/cart/checkout |
| URLs | Assets use `site_url_prefix` |
| Payment | If no Connection yet: tell user checkout payment needs Commerce → Connections |

---

## Tools sequence (summary)

```
list_plugins → (activate_plugin / instruct install)
→ guide Tax + Shipping settings
→ create_collection / update_collection (products + commerce field)
→ create_entry × 3 (published, ship, sku, weight_grams, price)
→ (user) rebuild catalog if index empty
→ get_project_info + write_project_file (shop + cart/checkout + css)
→ smoke checklist
```

## AI limits (store kit)

| Can do via tools | Requires admin UI / user |
|------------------|--------------------------|
| Activate plugin (if installed) | Marketplace install zip if missing |
| Collection + commerce field schema | Payment provider secrets (Connections) |
| Seed product entries | Tax rules editor, shipping zones editor |
| Project HTML/CSS + template functions | Catalog Rebuild button if index stale |
| Guide Stripe mount on `checkout-ready` | Live payment confirmation in browser |

## Anti-patterns

- Skipping `list_plugins` and assuming Commerce exists
- Empty `fields: []` then inventing price fields
- Digital/`deliver` products when the kit asks for ship + weight
- Custom `fetch` cart JS instead of `commerce-storefront.js`
- CSS/links at `/css/` when prefix is `/project/`
- Hard-coded product cards after a `products` collection exists
- Declaring done without three **published** entries
- Mixing legacy `shopping_cart` plugin with Commerce fieldtype on greenfield

## Related

| Topic | When |
|-------|------|
| `commerce` | Variants, subscriptions, fulfillments, template function reference |
| `plugins-overview` | Install / enable flow |
| `site-building` / `web-project-patterns` | Broader site shell, list/detail rules |
| Plugin hybrid cart | Mixed ship+deliver+register+subscribe carts |

**Invoke:** `read_birkly_docs` topic **`commerce-store-kit`**, or MCP `birkly://docs/commerce-store-kit`. Auto-loaded when the user asks to build/seed an online store with checkout.
