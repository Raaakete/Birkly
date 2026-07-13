# V-BETA-03 — Coffee shop with commerce cart

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-COFFEE

Shopper browses coffee products and adds to cart using commerce plugin.

## Prerequisites

- Birkly dev server running
- Commerce plugin installed: `./scripts/install-dev-plugins.sh commerce`
- Coffee reference project: `./scripts/install-reference-project.sh coffee`
- Commerce index built: `php scripts/commerce-reindex.php`

## Steps

1. **Coffee shop homepage**
   - Navigate to `/reference-projects/coffee/website/index.html`
   - Hero with brand messaging
   - Featured products grid
   - Products loaded from `products` collection via `page_bundle`

2. **Product listing**
   - Products displayed with images, names, prices
   - Each product links to detail page
   - Price variants shown (e.g., 250g, 500g, 1kg)
   - "Add to Cart" buttons visible

3. **Product detail page**
   - `/reference-projects/coffee/website/product.html?slug=ethiopian-single-origin`
   - Full product description
   - Variant selector (size, grind type)
   - Purchase option selector (buy once, subscription)
   - Commerce template functions render pricing: `commerce_price_from`

4. **Add to cart**
   - Select variant: `var-eth-250-whole` (250g whole bean)
   - Click "Add to Cart"
   - API request to `/api/index.php?route=commerce&action=add`
   - Cart badge updates with item count
   - Success confirmation shown

5. **Cart verification**
   - Cart API returns `status: ok`, `count: 1`
   - Session cookie tracks cart state
   - Product variant ID and quantity stored

## Automated verification

```bash
./scripts/install-dev-plugins.sh commerce
./scripts/install-reference-project.sh coffee
php scripts/commerce-reindex.php
./scripts/journey-runner.php --journey J-COFFEE
```

Journey verifies product pages load, page_bundle returns products, add-to-cart API succeeds with cart count > 0.

## Status

✅ Automated journey passes (J-COFFEE)
