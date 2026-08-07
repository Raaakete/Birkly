# Commerce tax (providers + B2B VAT)

Tax for Commerce **1.0.23+** / current **1.0.27**: selectable **tax providers** (Manual or Stripe Tax), product **tax classes**, class×country rates, optional **B2B reverse charge**, and frozen **`tax_lines`** on each paid order.

## Summary

Choose a tax provider under **Commerce → Settings → Tax**. **Manual** uses your rate table. **Stripe Tax** calls Stripe’s Tax Calculations API for quotes — orthogonal to the payment PSP (you can charge with Mollie/PayPal/etc.). Paid orders freeze `tax_cents` / `tax_lines` and never re-call the provider. Optional B2B reverse charge zeroes tax when a VAT ID and country rules match (format normalize only — no live VIES).

## Beginner

### Choose a provider

1. Open **Commerce → Settings → Tax**.
2. Set **Tax provider** to **Manual** (default) or **Stripe Tax**.
3. Check **Enable tax at checkout**.
4. **Save Tax Rules**.

Stripe Tax uses the Stripe **secret key** from **Settings → Connections** for calculation only. Charge PSP can be any configured provider.

### Manual rates and classes

1. Set a **default rate** (%) and optional **country overrides**.
2. Under **Tax classes**, add classes (`standard`, `reduced`, `zero`, plus custom). Leave a class default blank to inherit; use `0` for explicit zero-rated.
3. Under **Class × country rates**, add overrides (e.g. reduced × DE = 7%).
4. On each sellable Commerce field, set **Tax class** (default `standard`).

**Resolution order (Manual, per line):** class×country → class default → country override → site default. Tax applies to the goods subtotal (after line discounts).

### B2B reverse charge (VAT ID)

1. In **Settings → Tax**, enable **B2B reverse charge**.
2. Set **Merchant country** (ISO) and optionally edit the **eligible countries** list (defaults to an EU-style set).
3. Leave **Require different country** on (default) so same-country B2B still uses normal rates.
4. At checkout (or draft order), the buyer enters a **VAT ID** (`vat_id` / `tax_id`).

When enabled + both countries are eligible + VAT ID present (+ different-country rule if on) → `tax_cents` is `0` and `tax_lines` use `reason: reverse_charge`. The order stores `vat_id`. IDs are normalized for format only — **live VIES validation is not included**.

## Advanced Users

**Setting:** `commerce_tax_provider` — `manual` | `stripe_tax` (default `manual`).

**Interfaces:** `TaxProviderInterface` → `ManualTaxProvider` | `StripeTaxProvider` via `TaxProviderResolver`. Catalog `calculateTax` delegates to the resolved provider.

**Field:** `tax_class` on the Commerce fieldtype (string; default `standard`).

**Order snapshot:** Paid orders freeze `tax_cents` and `tax_lines[]` (`tax_class`, title, rate, `tax_cents`, `source` / `provider`). Mixed carts emit **one tax line per class**. Recalculating from current catalog settings must not mutate paid-order lines.

**Stripe Tax:** Uses Connections Stripe secret when present; otherwise mock / soft-fallback shaped results so checkout does not break. Reverse-charge eligibility is evaluated before (or inherited into) Stripe Tax mock paths; live Stripe Tax still accepts `vat_id` in context.

**B2B rules shape** (`tax_rules.b2b_reverse_charge`):

| Key | Meaning |
|-----|---------|
| `enabled` | Toggle reverse charge |
| `merchant_country` | Merchant ISO country |
| `eligible_countries` | List of ISO codes (default EU set) |
| `require_different_country` | Default `true` |

> **Note:** OSS/IOSS automation and live VIES are out of scope for this release.

## Related

- [Commerce fieldtype](commerce-fieldtype.md)
- [Commerce shipping rates](commerce-shipping.md)
- [Commerce plugin overview](plugins/commerce.md)
- [Commerce plugin reference](commerce-plugin.md)
