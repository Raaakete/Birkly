# Connections API reference

This reference documents the public PHP API for sending mail, sending notifications, resolving credentials, and verifying webhooks through Birkly Connections. It is intended for plugin authors and site developers who integrate with Birkly core.

## Summary

Connections exposes four groups of helpers: **mail** (`birkly_send_mail`), **notifications** (`birkly_notify`), **credential resolution** (`birkly_get_connection`, `birkly_http_with_connection`), and **webhook verification** (`birkly_verify_webhook`). All functions are native PHP; no Composer SDK is required. Secrets are never returned in plain text unless the caller explicitly requests them with the correct capability, and even then they should only be used inside a server-side helper, never logged or echoed.

## Beginner

### When to use each function

| I want to... | Function |
|--------------|----------|
| Send an email from a plugin or core path | `birkly_send_mail` |
| Notify multiple channels about an event | `birkly_notify` |
| Read a Connection’s metadata or health | `birkly_get_connection` |
| Make an HTTP call using a stored credential | `birkly_http_with_connection` |
| Check that an incoming webhook really came from a provider | `birkly_verify_webhook` |

### Mail from a plugin

The simplest way to send email is:

```php
birkly_send_mail(
    $to = 'customer@example.com',
    $subject = 'Order confirmed',
    $html = '<p>Thank you for your order.</p>',
    $opts = [
        'alias' => 'transactional_email',
        'text' => 'Thank you for your order.',
        'reply_to' => 'support@example.com',
    ]
);
```

Birkly looks up the `transactional_email` alias, picks the configured provider, and sends the message. If no alias is mapped, it returns a clear error instead of silently logging.

### Notifications

To notify several channels at once, use an event key:

```php
birkly_notify(
    $event = 'commerce.order_paid',
    $payload = ['order_id' => 123, 'customer' => 'customer@example.com'],
    $opts = ['sync' => false]
);
```

Birkly checks the notification routes for `commerce.order_paid` and fans out to email, Slack, Discord, and SMS channels that the admin configured.

### Credentials

If your plugin needs to call an external HTTP API, do not store the secret in plugin metadata. Create an **HTTP API credential** Connection and resolve it:

```php
$conn = birkly_get_connection('shipping_api');
// $conn contains base URL, auth mode, and masked metadata — not the secret.
```

For a ready-made signed request:

```php
$response = birkly_http_with_connection('shipping_api', [
    'method' => 'GET',
    'path' => '/rates',
    'query' => ['from' => '90210', 'to' => '10001'],
]);
```

### Webhooks

When a provider such as Resend or SES posts a bounce event to `/api/webhooks/connections/{id}`, verify it before acting:

```php
$verified = birkly_verify_webhook($endpointId, $rawBody, $headers);
if ($verified) {
    // Process bounce/complaint/delivery event.
}
```

## Advanced Users

### `birkly_send_mail`

```php
$result = birkly_send_mail(
    string $to,
    string $subject,
    string $html,
    array $opts = []
);
```

Options:

| Option | Type | Description |
|--------|------|-------------|
| `alias` | string | Alias to resolve (`transactional_email`, `marketing_email`, `ops_alerts`, custom). |
| `text` | string | Plain-text body. If omitted, a text version is generated from HTML. |
| `from` | string | Override From address. |
| `from_name` | string | Override From display name. |
| `reply_to` | string | Reply-To address. |
| `attachments` | array | Array of `[name, mime, data]` or file paths. |
| `headers` | array | Additional headers as key/value pairs. |
| `idempotency_key` | string | Prevents duplicate sends on retry. |
| `sync` | bool | `true` = send immediately; `false` = queue for async processing. Default depends on context. |
| `connection_id` | string | Bypass alias and use a specific Connection. |

Return value:

| Field | Meaning |
|-------|---------|
| `ok` | Whether the message was accepted/queued successfully. |
| `status` | `queued`, `accepted`, `logged`, `failed`, `suppressed`. |
| `message_id` | Provider or internal message id, when available. |
| `error` | Error message if `ok` is false. |
| `stage` | Pipeline stage that failed, for debugging. |

### `birkly_notify`

```php
$result = birkly_notify(
    string $event,
    array $payload,
    array $opts = []
);
```

Looks up the route for `$event` in `settings/connections/notification_routes.json` and fans out to each configured channel. Options include `sync` and `connection_id` overrides. The return value is an array of per-channel results.

Built-in event keys:

- `commerce.order_paid`
- `commerce.order_shipped`
- `form.submission`
- `user.password_reset`
- `user.email_verify`
- `system.health_alert`
- `team.invitation`

Plugins may register custom keys as `plugin_slug.event`.

### `birkly_get_connection`

```php
$conn = birkly_get_connection(
    string $idOrAlias,
    array $opts = []
);
```

Resolves a Connection by id or alias. By default the returned array does **not** contain secrets. To include decrypted secrets, pass `with_secrets => true`; this requires the caller to hold the appropriate capability and should only be used inside server-side helpers.

Returned metadata includes:

| Field | Description |
|-------|-------------|
| `id` | Connection UUID. |
| `name` | Human label. |
| `type` | Connection type. |
| `provider` | Provider/transport slug. |
| `config` | Non-secret configuration. |
| `health` | Last health result. |
| `managed_by_env` | Whether env overrides are active. |

### `birkly_list_connections`

```php
$connections = birkly_list_connections([
    'type' => 'email-transactional',
    'provider' => 'resend',
]);
```

Returns metadata for picker UIs. Never includes secrets.

### `birkly_connection_health`

```php
$health = birkly_connection_health('transactional_email');
```

Returns the stored health record and checklist for a Connection. Use this in admin dashboards and plugin empty states. Does not perform a live test; use the admin Test button or the REST API for that.

### `birkly_http_with_connection`

```php
$response = birkly_http_with_connection(
    string $idOrAlias,
    array $request
);
```

Applies the Connection’s auth mode to an HTTP request:

- `bearer` — adds `Authorization: Bearer {secret}` header.
- `basic` — adds `Authorization: Basic {secret}` header.
- `header` — adds a custom header with the secret.
- `query` — adds the secret as a query parameter.

`$request` fields:

| Field | Description |
|-------|-------------|
| `method` | HTTP method. |
| `path` | Path appended to the Connection base URL. |
| `query` | Query parameters. |
| `headers` | Extra headers. |
| `body` | Request body. |
| `timeout` | Request timeout in seconds. |

The secret is applied inside the helper; plugin code never handles it directly.

### `birkly_verify_webhook`

```php
$verified = birkly_verify_webhook(
    string $endpointId,
    string $rawBody,
    array $headers
);
```

Verifies the signature on an incoming webhook payload using the signing secret stored for that endpoint. Returns `true` or `false`. On success, the caller is responsible for parsing the event and updating the delivery log or suppression list.

Provider-specific signature methods:

| Provider | Header / mechanism |
|----------|--------------------|
| Resend | `svix-signature` |
| Postmark | `X-Postmark-Signature` |
| SES | SNS signature validation |
| Mailgun | `X-Mailgun-Signature` (timestamp + token + signature) |

### Admin REST endpoints

The admin REST API mirrors the PHP helpers:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/admin/connections` | List metadata. |
| POST | `/api/admin/connections` | Create Connection. |
| GET | `/api/admin/connections/{id}` | Read metadata. |
| PATCH | `/api/admin/connections/{id}` | Update config/secrets. |
| DELETE | `/api/admin/connections/{id}` | Delete Connection. |
| POST | `/api/admin/connections/{id}/test` | Test send / test request. |
| GET | `/api/admin/connections/aliases` | List alias mappings. |
| POST | `/api/admin/connections/aliases` | Update alias mapping. |
| GET | `/api/admin/connections/delivery-log` | Query log. |
| POST | `/api/admin/connections/queue/drain` | Process queued items. |
| POST | `/api/webhooks/connections/{id}` | Inbound provider webhook. |

Exact REST paths and field names may be finalized when core implementation reports stable API names.

### Plugin manifest contract

Plugins declare their needs in `plugin.json`:

```json
{
  "requires_connections": [
    { "type": "email-transactional", "alias": "transactional_email", "optional": false },
    { "type": "slack-ops", "alias": "ops_alerts", "optional": true }
  ]
}
```

Hub uses this to warn admins before installing a plugin that needs a Connection that is not configured.

## Related

- [Connections](../01-admin/connections.md)
- [Connections provider recipes](../01-admin/connections-provider-recipes.md)
- [Migrate from Automation Setup email](../01-admin/connections-migration.md)
- [Connections MCP rules](../01-admin/connections-mcp-rules.md)
- [Plugin development](../06-plugin-economy/plugin-development.md)
