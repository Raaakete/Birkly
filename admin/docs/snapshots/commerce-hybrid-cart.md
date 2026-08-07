# Commerce hybrid cart

One session cart can mix **ship**, **deliver**, **register**, and **subscribe** lines. Checkout splits **billing groups** (one-time vs recurring) and reuses email / address from a checkout draft. Plugin help shelf: **Hybrid cart** (`help-hybrid-cart`).

## Summary

Commerce **1.0.29** (P54 P3) certifies mixed-fulfillment carts with **split checkout**. One-time lines (ship + deliver + register) settle together; recurring / subscribe lines pay in a later group. Prefer `/plugins/commerce/assets/commerce-storefront.js` over a custom cart engine.

## Beginner

### What shoppers can mix

| Line type | Fulfillment | Billing | Checkout group |
|-----------|-------------|---------|----------------|
| Physical product | `ship` | one-time | One-time |
| Digital download | `deliver` | one-time | One-time |
| Event / class | `register` | one-time | One-time |
| Membership / club | `none` (or product) | recurring | **Separate** subscription group |

Example: coffee bag (ship) + PDF (deliver) + workshop seat (register) + monthly club (subscribe).

### What buyers enter once

Collect **email**, **shipping address** (only when the cart has `ship` lines), and **attendee name** (when it has `register` lines) on the first step. Commerce stores a checkout draft and reuses those details for later groups.

### Payment order

1. **One-time group** — ship + deliver + register (gift cards and coupons apply here).
2. **Subscription group(s)** — each recurring interval is its own payment (gift cards/coupons do **not** re-apply).

Shipping is calculated only from `ship` lines. Digital-only or register-only carts do not require a shipping address for fulfillment.

### After payment

**PostPurchaseRouter** runs per paid line: ship → fulfillment adapter; deliver → download token; register → attendee + capacity; subscribe / none → subscription + optional membership mapping.

## Advanced Users

### Groups API

Cart `get` / `buildCartLines` expose `split_required` and `groups[]`. Checkout without `group_key` on a multi-group cart returns `needs_split: true` plus `checkout_session_id` and `groups`. Pass `group_key` + `checkout_session_id` to pay one group.

### Storefront helper

```js
BirklyCommerce.checkout({ email, shipping_address, attendee_name });
await BirklyCommerce.confirmPayment(stripe, elements);
if (BirklyCommerce.getSplitState().nextGroupKey) {
  await BirklyCommerce.continueSplitCheckout();
}
```

Helpers: `cartNeedsShipping()`, `cartNeedsAttendee()`, `continueSplitCheckout()`, `getSplitState()`.

Markup hooks: `[data-commerce-shipping-fields]`, `[data-commerce-attendee-fields]`, `[data-commerce-status]`.

### Capacity and stock

- **Register** uses catalog `quantity_available`; spots reserved at checkout, committed when paid.
- **Deliver / gift_card / none** do not hold warehouse stock.
- **Ship** tracked SKUs reserve inventory until pay or release.

> **Note:** In-admin plugin help: Commerce → Documentation → **Hybrid cart** (`plugins/commerce/docs/help-hybrid-cart.md`).

## Related

- [Commerce plugin overview](plugins/commerce.md)
- [Commerce store kit (MCP / Agent)](commerce-store-kit.md)
- [Commerce template functions](../03-templating/commerce-functions.md)
- [Commerce subscriptions](commerce-subscriptions.md)
- [Commerce fulfillment](commerce-fulfillment.md)
