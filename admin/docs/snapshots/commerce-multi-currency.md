# Commerce multi-currency checkout

Sell in more than one currency with a site default and per–purchase-option overrides (Phase 14). No automatic FX conversion — each order stores the currency charged.

## Summary

Commerce **multi-currency v1** sets a site-wide default (`commerce_default_currency`) and optional **currency per purchase option** on each product. Checkout passes the selected currency to payment providers; orders, analytics CSV, gift cards, and quotes respect the order currency without cross-currency math.

## Beginner

**How currencies work**

- One Birkly site can sell a product in **EUR** and another product in **USD** on the same catalog.
- Each **purchase option** (one-time or subscription) has its own `currency` code (ISO 4217 lowercase, e.g. `eur`, `usd`).
- The **site default** applies when a purchase option does not specify currency.
- Birkly does **not** convert prices between currencies — you set each price explicitly.

**Set the site default**

1. Open **Commerce → Settings**.
2. Find **Default currency** (for example `eur`).
3. Save.

**Set currency on a product**

1. Edit a Commerce entry (or variant).
2. Open each **purchase option** row.
3. Choose **Currency** from the selector (EUR, USD, GBP, etc.).
4. Enter **price** in that currency's smallest unit (cents).
5. Save the entry.

**Checkout behaviour**

- Cart lines group by currency; mixed-currency carts checkout one currency at a time (or block mixed checkout per site policy).
- Payment providers receive the correct currency code.
- Order receipts and **Commerce → Dashboard** CSV export show the charged currency column.

**Gift cards and quotes**

- Gift cards are issued in a single currency.
- B2B quotes store currency on the quote total — do not mix currencies on one quote.

## Advanced Users

**Settings key:** `commerce_default_currency` in Commerce plugin settings (default `eur`).

**Purchase option schema:**

```json
{
  "id": "usd-once",
  "billing": "one_time",
  "price_cents": 2499,
  "currency": "usd"
}
```

**Checkout shelf:** `CommerceCheckoutService` passes `currency` to all payment shelf providers. Order records persist `currency` at line and order level.

**Analytics:** `CommerceAnalyticsService` exports per-order currency; revenue summaries do not sum across currencies without explicit filtering.

**Limitations (v1):**

- No FX engine or display-currency ≠ charge-currency
- No automatic geo-IP currency selection
- Refunds use the original order currency

**Tests:** `commerce_multi_currency_test.php`

## Related

- [Commerce plugin](plugins/commerce.md)
- [Commerce analytics](commerce-analytics.md)
- [Commerce gift cards and quotes](commerce-gift-cards-quotes.md)
- [Commerce functions](../../03-templating/commerce-functions.md)
