# Commerce promotions (Discounts v2)

**Discounts** (Promotions v2, Commerce **1.0.19+** / current **1.0.22**) covers coupons, automatic rules, and affiliate discounts with usage limits, min purchase, date windows, free shipping, and stack rules — edited in a structured admin form.

## Summary

Open **Commerce → Discounts → Add Rule**. Rules evaluate at cart/checkout. Paid orders increment usage counts. Non-stackable rules compete (highest customer savings wins); stackable rules may combine afterward.

## Beginner

### Create a rule

1. Open **Commerce → Discounts**.
2. Click **Add Rule**.
3. Set **Name**, **Type** (`coupon`, `automatic`, or `affiliate`), and value type:
   - **Percent off**
   - **Fixed amount** (cents)
   - **Free shipping**
4. Optional constraints:
   - **Min purchase** (cents of goods subtotal)
   - **Usage limit** (blank = unlimited; increments when an order is paid)
   - **Starts at** / **Ends at** (date window)
   - **Target** (optional `collection:…` or `entry:…:slug`)
5. Check **Stackable** if the rule may combine with other stackable rules.
6. Leave **Enabled** on, then **Save rule**.

### Stack rules (plain language)

| Kind | Behavior |
|------|----------|
| **Non-stackable** (`stackable` off) | Among matching exclusive candidates, only the **highest customer savings** applies |
| **Stackable** (`stackable` on) | May apply **in addition** to the winning exclusive rule |
| **Coupon + automatic** | May combine when **both** are stackable |

### Affiliate

Affiliate rules match `?ref=partner_id` on storefront URLs (session) plus the rule’s affiliate ID field.

## Advanced Users

**Engine:** `DiscountEngine::evaluateCart()` — returns `line_discounts`, `free_shipping`, `discount_cents`, `allocations`, `applied_rule_ids`.

**Value types:** `percent`, `fixed`, `free_shipping`. Free shipping can apply cart-wide when target is empty; with a target, matching lines are required.

**Admin form fields:** name, type, code / affiliate id, value_type, value, `min_purchase_cents`, `usage_limit`, `starts_at`, `ends_at`, target, `stackable`, enabled.

> **Note:** This page covers **promotions only**. Tax providers and live shipping rates are documented under [Commerce tax](commerce-tax.md) and [Commerce shipping rates](commerce-shipping.md).

## Related

- [Commerce plugin overview](plugins/commerce.md)
- [Commerce gift cards and quotes](commerce-gift-cards-quotes.md)
- [Commerce multi-currency](commerce-multi-currency.md)
