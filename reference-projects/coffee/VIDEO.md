# V-TPL-04 — Coffee shop reference site

**Status:** Stub — record walkthrough when ready.

## Story

A specialty roaster sells coffee with size/grind variants. Product pages show `commerce_price_from` and add-to-cart via `data-birkly-route="commerce"` forms.

## Pages to demo

1. `/reference-projects/coffee/website/index.html` — product grid with from-prices
2. `/reference-projects/coffee/website/product.html?slug=ethiopian-single-origin` — variants + add to cart

## Talking points

- Commerce field with `purchase_options`
- Variant sub-entries
- `{commerce_price_from commerce}` on parent listing
- `data-birkly-route="commerce"` `data-birkly-action="add"` forms

## Prerequisites

```bash
./scripts/install-dev-plugins.sh
./scripts/install-reference-project.sh coffee
# Activate commerce plugin in Admin
```
