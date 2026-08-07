# Commerce shipping rates

Shipping rate providers for Commerce **1.0.24+** / current **1.0.27**: **Manual** zones and **EasyPost** live quotes, with fallback to manual zones when live rates fail.

## Summary

Under **Commerce → Settings → Shipping**, choose **Manual** (zone table) or **EasyPost** (live carrier quotes). EasyPost needs an API key and **weight (grams)** on every shippable line. If live quoting fails or returns no rates, Commerce falls back to manual zones. Rate selection is separate from the **fulfillment** adapter (labels/tracking).

## Beginner

### Manual zones

1. Open **Commerce → Settings → Shipping**.
2. Leave **Shipping rate provider** on **Manual**.
3. Configure zones (countries/regions, flat or weight-based amounts as offered in the UI).
4. **Save**.

Manual zones also serve as the **fallback** when EasyPost is selected but live quoting cannot complete.

### EasyPost live rates

1. Set **Shipping rate provider** to **EasyPost**.
2. Enter the **EasyPost API key** and save (key status shows when stored).
3. On each `ship` product / variant Commerce field, set **Weight (grams)** (`weight_grams` > 0). Optional length/width/height (mm) improve parcel dimensions.
4. At checkout, the customer picks a returned rate; the order stores `shipping_lines` with `source: easypost`.

Without weight on a shippable line, live quoting returns an error asking for weight — fix the product before checkout can price shipping.

### Fallback

When EasyPost fails (missing destination, API error, no rates), Commerce quotes **manual zones** instead and may mark the snapshot with `fallback: true`. Keep zones configured even if you primarily use EasyPost.

## Advanced Users

**Setting:** `commerce_shipping_rate_provider` — `manual` | `easypost`. Secret: `commerce_easypost_api_key`.

**Interfaces:** `ShippingRateProviderInterface` → `ManualShippingRateProvider` | `EasyPostShippingRateProvider` via `ShippingRateProviderResolver`.

**Quote shape:** `rates[]`, selected `shipping_lines[]`, `shipping_cents`, `provider`, optional `ok` / `error` / `fallback`.

**Weight gate:** `CommerceCatalog::validateShippableWeights()` — every `fulfillment: ship` line (and shippable bundle components) must have `weight_grams > 0` when live rates are on.

**Field extras (`ship`):** `weight_grams` (required for EasyPost), optional `length_mm` / `width_mm` / `height_mm`.

> **Note:** Shipping **rate** provider ≠ fulfillment **adapter**. Labels and tracking webhooks are configured under [Fulfillment](commerce-fulfillment.md).

## Related

- [Commerce fulfillment](commerce-fulfillment.md)
- [Commerce fieldtype](commerce-fieldtype.md)
- [Commerce tax](commerce-tax.md)
- [Commerce plugin overview](plugins/commerce.md)
