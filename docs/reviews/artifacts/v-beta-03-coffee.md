# V-BETA-03 — Coffee: browse product and add to cart

**Journey:** J-COFFEE · **Date:** 2026-07-13

## Prerequisites

```bash
./scripts/prepare-reference-demo.sh coffee
php -S localhost:8080 -t . server-router.php
```

## Browser steps

1. Open `/reference-projects/coffee/website/index.html`
2. Wait for **Ember & Bean** header and product grid
3. Open `/reference-projects/coffee/website/product.html?slug=ethiopian-single-origin`
4. Wait for variant rows: **250g · Whole bean**, **1kg · Filter grind** with prices
5. Click **Add to cart** on a variant
6. After reload, cart header should show **Cart: 1 items**

## Fixes applied

- `normalize-reference-sub-entries.php` — variant files → `sub_entries/variants/` paths
- `birkly-client.js` — prefetch `sub_entries` on product pages; collection detection fix
- `plugins/commerce/api/commerce.php` — read `$_POST` for add-to-cart forms

## Automated gate

```bash
php scripts/journey-runner.php --journey J-COFFEE
```

Asserts: variants in page_bundle, add-to-cart API, cart count.

## Video

`v-beta-03-coffee-add-to-cart.mp4`
