# Shopping Cart — payment_shelf consumer

Checkout uses `get_plugin_service('payment_shelf')` only. Subscribe to `payment_succeeded` / `payment_failed` and match orders by `payment_id` stored at checkout time.

```php
// plugin.php registers handlers via ConsumerPaymentHelper
subscribe_to_event('payment_succeeded', fn($data) => mark order paid by $data['payment_id']);
```

Always pass shelf context:

```php
'source_plugin' => 'shopping_cart',
'source_ref'    => $orderId,
```
