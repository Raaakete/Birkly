# Commerce customers

**Customers** is a first-class Commerce tab (1.0.17+ / current **1.0.22**): email-keyed profiles with addresses, tags, notes, CSV import, and an order/subscription timeline. Passwords are never stored or imported.

## Summary

Commerce customer profiles are commercial records, not login accounts. Checkout and subscriptions **auto-upsert** a profile by email. Optionally link a profile to **User Management** when that plugin is active (invite / password-reset only — no password migration).

## Beginner

### Open Customers

1. Open **Commerce → Customers**.
2. Search by email, name, or tags.
3. Click a row for detail, or open the **timeline** for orders and subscriptions tied to that email.

### Add or edit a profile

1. Click **Add customer**, or open an existing profile and **Save**.
2. Email is required and is the unique key (normalized lowercase).
3. Optional: name, phone, tags, notes, shipping address fields.

### Import CSV

1. Click **Import CSV** and choose a file.
2. Required column: `email`.
3. Optional columns: `name`, `phone`, `tags`, `notes`, `line1`, `line2`, `city`, `region`, `postal`, `country`.
4. Password-like columns (`password`, `passwd`, `password_hash`, etc.) are **ignored**.

Shopify cutover also upserts customers by email — see [Shopify cutover playbook](commerce-shopify-cutover.md).

### User Management link

When **User Management** is active, the customer detail/timeline can show a linked user (matched by `user_id` or email) and an admin deep link. Commerce does **not** create passwords. Use User Management invite or password-reset after go-live.

## Advanced Users

**Storage:** `CommerceCustomerStore` under `data/plugins/commerce/customers/` (`cust_*.json`). Soft-delete via `deleted_at`.

**Admin API actions:** `list_customers`, `get_customer`, `save_customer`, `import_customers_csv`, `delete_customer`, `customer_timeline`.

**Timeline:** Aggregates orders and subscriptions for the customer email; includes optional `user_management` resolution when that plugin is active.

**Checkout:** Paid checkout upserts/updates the customer profile from buyer email and shipping details.

## Related

- [Commerce plugin overview](plugins/commerce.md)
- [Shopify cutover playbook](commerce-shopify-cutover.md)
- [User plugin](user-plugin.md)
- [Commerce membership wiring](commerce-membership.md)
