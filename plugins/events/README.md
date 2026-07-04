# Events — payment_shelf consumer

Free RSVP works without Payments. Paid tickets require Payments active + `access_payment_shelf`.

Payment handlers confirm registrations by `payment_id` after webhooks broadcast `payment_succeeded`.

```php
'source_plugin' => 'events',
'source_ref'    => $registrationId,
```
