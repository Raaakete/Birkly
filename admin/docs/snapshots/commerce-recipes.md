# Automation Commerce recipe pack

Installable starter workflows for common Commerce events (P54 P3-M3). Pack lives in the Automation plugin under `recipes/commerce/`.

## Summary

Import four disabled workflows from **Automation → Recipe pack**, set real addresses, then enable. Recipes cover post-purchase receipt, subscription dunning, low-stock alert, and order shipped.

## Beginner

### Recipes

| Recipe id | Name | Trigger |
|-----------|------|---------|
| `commerce-post-purchase-receipt` | Post-purchase receipt | `commerce_order_paid` |
| `commerce-subscription-dunning` | Subscription dunning | `commerce_subscription_payment_failed` |
| `commerce-low-stock-alert` | Low stock alert | `commerce_low_stock` |
| `commerce-order-fulfilled` | Order shipped / fulfilled | `commerce_order_shipped` |

### How to import (admin)

1. Activate **Automation** and **Commerce**.
2. Open **Automation** in the sidebar.
3. Under **Recipe pack**, click **Import** on a commerce recipe.
4. Edit the workflow: replace placeholder emails, review the canvas, configure **Setup → SMTP**.
5. Enable and dry-run / live-test.

Imported workflows are **disabled** by default so placeholders are not sent live.

### Template variables

| Recipe | Useful keys |
|--------|-------------|
| Order paid | `order_id`, `customer_email`, `total_cents`, `currency`, `status`, `lines` |
| Dunning | `subscription_id`, `order_id`, `customer_email`, `status`, `attempt_count`, `dunning` |
| Shipped | `order_id`, `customer_email`, `shipment_id`, `tracking_number`, `carrier`, `package_count` |
| Low stock | `sku`, `on_hand`, `reserved`, `available`, `threshold` |

## Advanced Users

### API

```http
GET  /api/automation.php?action=list_recipes
GET  /api/automation.php?action=list_recipes&pack=commerce
GET  /api/automation.php?action=get_recipe&recipe_id=commerce-post-purchase-receipt
POST /api/automation.php
     { "action": "import_recipe", "recipe_id": "commerce-post-purchase-receipt", "_csrf": "…" }
```

Optional import body: `enabled`, `name`, `to` (override email action `to`).

### CLI

From the CMS root (plugin active):

```bash
php plugins/automation/cli/import_recipe.php commerce-post-purchase-receipt
php plugins/automation/cli/import_recipe.php --pack=commerce
php plugins/automation/cli/import_recipe.php --list
```

> **Note:** `commerce_low_stock` is registered by Commerce **1.0.29** (`CommerceLowStockService`) — debounced per SKU until stock recovers above threshold. See [Commerce analytics](../06-extensibility/commerce-analytics.md).

## Related

- [Automation overview](automation-overview.md)
- [Triggers](triggers.md)
- [Commerce plugin overview](../06-extensibility/plugins/commerce.md)
- [Commerce subscriptions](../06-extensibility/commerce-subscriptions.md)
- [Commerce analytics (ops intelligence)](../06-extensibility/commerce-analytics.md)
