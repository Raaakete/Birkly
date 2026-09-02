# Connections

Connections is the shared home in **Settings → Connections** for all the pipes Birkly and official plugins use to talk to the outside world: transactional email, notification channels (Slack, Discord, SMS), HTTP API credentials, and webhook signing secrets. A Connection is a named record with a type, a provider or transport, encrypted credentials, and a health status. Until at least one Connection is healthy, no plugin can send email or post to chat on your behalf — enabling a template or workflow is not the same as delivering a message.

## Summary

**Connections** replaces the scattered SMTP settings that used to live in Automation → Setup, Commerce, and other plugins. One Settings tab holds every shared pipe: Email (SMTP, Log, Resend, Postmark, Amazon SES, Mailgun), Notification channels (Slack, Discord, Twilio SMS), HTTP API credentials, and Webhook secrets. Each Connection has a **type**, a **provider/transport**, encrypted **credentials**, and a **health** status. Plugins ask for a Connection by **alias** (for example `transactional_email`) instead of storing passwords themselves. You can add, edit, test, and delete Connections from the admin; the delivery log shows what happened to each message. Aliases let you route different jobs to different providers without changing templates or plugin code.

## Beginner

### What a Connection is

Think of a Connection as a saved account Birkly uses to send something out of the CMS:

- **Email Connection** — the server or service that delivers your mail.
- **Notification channel** — a Slack workspace, Discord server, or SMS number that receives alerts.
- **HTTP API credential** — a base URL and secret for a shipping provider, CRM, or other API.
- **Webhook signing secret** — a shared password that proves an incoming webhook really came from a provider.

Each Connection has a friendly name you choose, a type (for example **Email — SMTP**), and the secret details needed to connect. Secrets are encrypted at rest; the admin only shows whether they exist, never the actual password or key.

### Why this matters

Before Connections, email settings were hidden inside Automation → Setup. If you turned Automation off, Commerce order emails, form notifications, and password resets could stop working because they relied on that plugin’s SMTP settings. Connections moves that responsibility into the platform, so every plugin shares the same healthy pipe. The main thing to remember: **turning on a feature is not the same as having a working Connection**. A Commerce email template can be enabled, but if no Email Connection is healthy, the message will not reach a real inbox.

### Opening Connections

1. In the Birkly admin, open **Settings**.
2. Click **Connections**.
3. The first viewport shows your **Email** health. If nothing is configured yet, the status is **Not configured**.

### Adding a Connection

1. In **Settings → Connections**, click **Add connection**.
2. Choose the **type** you need:
   - **Email — SMTP** (your own mail server)
   - **Email — Resend / Postmark / Amazon SES / Mailgun** (a mail service)
   - **Email — Log** (writes to a log file; good for local development only)
   - **Slack**, **Discord**, **SMS (Twilio)**
   - **HTTP API credential**
   - **Webhook signing secret**
3. Enter a **name** (for example `Primary SMTP` or `Resend production`).
4. Fill in the provider fields. Passwords and API keys are masked and encrypted.
5. Save.

After saving, click **Test** to send a test message or request. The result updates the Connection’s health.

### Editing a Connection

1. Find the Connection card and click **Edit**.
2. Change the fields you need. Existing secrets stay in place unless you overwrite them.
3. Save. The health status resets until the next successful test or send.

If a field is managed by an environment variable (for example `BIRKLY_SMTP_HOST`), the UI shows **Managed by environment** and the field is read-only. Change the value in your server environment, not in the admin.

### Deleting a Connection

1. Open the Connection and click **Delete**.
2. Confirm. Birkly warns you if any alias or plugin still references this Connection.

Deleting a Connection does not delete the delivery log, but new sends that relied on it will fail until you point the alias at another Connection.

### Health and status

Each Connection shows a status:

| Status | What it means |
|--------|---------------|
| **Healthy** | The last test or real send succeeded. |
| **Unhealthy** | The last attempt failed; the card shows the staged error (network, auth, DNS, TLS). |
| **Not configured** | No Connection of this type exists yet. |
| **Managed by environment** | Some or all values come from environment variables; the UI is read-only. |
| **Warning** | The Connection works, but a checklist item is missing (for example production is using the Log transport). |

Email Connections also include a small checklist:

- From address and Reply-To are set.
- SPF/DKIM/DMARC DNS records are mentioned (Birkly does not generate keys; your provider does).
- For Docker hosts, SMTP is available or an ESP is recommended.

Click **Test send** on an Email Connection to send yourself a message and confirm the provider accepted it. A successful test only means the provider accepted the message; it does not guarantee delivery to the inbox.

### Aliases

An **alias** is a stable purpose name that plugins use so they do not need to know which provider you chose. Birkly ships with three email aliases:

| Alias | Typical use |
|-------|-------------|
| `transactional_email` | Order receipts, form notifications, password resets, invites, health alerts. |
| `marketing_email` | Opt-in promotions and newsletters sent by plugins. |
| `ops_alerts` | System health and urgent alerts. |

To map an alias:

1. In **Settings → Connections**, open **Aliases**.
2. Pick an alias from the list.
3. Choose which Email Connection should handle it.
4. Save.

If a plugin asks for `transactional_email` and you map it to a Resend Connection, every plugin that uses the alias sends through Resend. You can change the provider later without touching templates or plugin settings.

You can also create custom aliases for your own plugins or integrations.

### Notification routes

A **route** maps an event to one or more channels. For example, when the event `commerce.order_paid` happens, you might want:

- an email to the customer (`transactional_email` alias),
- a Slack message to the `#orders` channel,
- an SMS to the on-call number.

Routes are managed under **Connections → Notification routes**. Plugins can suggest default routes, but the admin owns the final map.

### Delivery log

The delivery log shows every send attempt: recipient, alias used, Connection, status (`queued`, `accepted`, `logged`, `failed`, `suppressed`, `bounced`, `complained`), timestamp, and a short message. Sensitive body content is truncated. You can filter by status and retry failed queued items manually.

### Suppressions

If an email hard-bounces or a recipient complains, the address is added to the suppression list. Future sends to that address are skipped with status `suppressed`. You can view and manually remove addresses from **Connections → Suppressions**, but only remove addresses you are sure are valid and willing.

### What to do when email is not working

1. Open **Settings → Connections** and look at the Email health card.
2. If no Connection exists, add one using a provider recipe.
3. If the status is **Unhealthy**, read the staged error.
4. Click **Test send** to yourself.
5. Check the delivery log for the real status of plugin sends.
6. If you are on Docker or a restricted host, SMTP may be blocked; use an ESP such as Resend or Mailgun instead.

## Advanced Users

### Connection model

A Connection is a typed record stored in `settings/connections/index.json` with secrets encrypted separately. The schema is roughly:

| Field | Meaning |
|-------|---------|
| `id` | UUID of the Connection. |
| `name` | Human label. |
| `type` | `email-transactional`, `slack-ops`, `http-shipping`, `webhook-signing`, etc. |
| `provider` / `transport` | `smtp`, `resend`, `postmark`, `ses`, `mailgun`, `log`, `slack_webhook`, `discord_webhook`, `twilio_sms`, `http_bearer`, etc. |
| `config` | Non-secret settings: host, port, from address, base URL, etc. |
| `secrets` | Encrypted credential fields; never returned in full on read. |
| `health` | Last check result, checklist state, timestamp. |
| `managed_by_env` | Whether the Connection is overridden by environment variables. |

Storage paths (subject to core implementation):

| Item | Location |
|------|----------|
| Connection metadata | `settings/connections/index.json` |
| Encrypted secrets | `settings/connections/secrets.enc` or per-id files |
| Aliases | `settings/connections/aliases.json` |
| Notification routes | `settings/connections/notification_routes.json` |
| Delivery log | `settings/connections/delivery_log/` |
| Durable queue | `settings/connections/queue/` |
| Suppressions | `settings/connections/suppressions.json` |

### Encryption and environment overrides

Secrets are encrypted with a key derived from `BIRKLY_SECRETS_KEY` in production. Local installs without that env var use an install-generated key and show a persistent warning. When an environment variable such as `BIRKLY_SMTP_HOST`, `BIRKLY_SMTP_PASS`, or a provider API key is set, it wins over the stored value and the UI shows **Managed by environment**.

### Email transports and status semantics

| Transport | Behavior |
|-----------|----------|
| `smtp` | Native dependency-free socket client with STARTTLS/SSL and AUTH LOGIN. |
| `log` | Writes to the delivery log; status `logged`; never labeled as “sent.” |
| `mail` | PHP `mail()`; available but discouraged, especially on Docker. |
| `resend` / `postmark` / `ses` / `mailgun` | Thin HTTPS adapters; store the provider message id. |

Pipeline statuses:

| Status | Meaning |
|--------|---------|
| `queued` | In the durable retry queue. |
| `accepted` | Provider or SMTP server accepted the message. |
| `logged` | Log transport only. |
| `failed` | Exhausted retries or hard error. |
| `suppressed` | Address is on the suppression list. |
| `bounced` / `complained` | Reported by provider webhook. |

Never claim end-to-end inbox delivery unless a provider event confirms it.

### Aliases and plugin contract

Aliases decouple plugins from providers. A plugin calls `birkly_send_mail(..., ['alias' => 'transactional_email'])`; the core resolves the alias to the current Connection id. The shipped aliases are `transactional_email`, `marketing_email`, and `ops_alerts`, but custom aliases are allowed.

Plugins declare their needs in `plugin.json` via `requires_connections: [{ type: 'email-transactional', alias: 'transactional_email', optional: false }]`. Hub shows a warning if the requirement is missing.

### Notification fan-out

`birkly_notify($event, $payload)` looks up the route for `$event` and fans out to each configured channel. Built-in event keys include `commerce.order_paid`, `commerce.order_shipped`, `form.submission`, `user.password_reset`, `user.email_verify`, `system.health_alert`, and `team.invitation`. Plugins may register `plugin_slug.event` keys.

### Durable queue and retries

Interactive tests and small transactional paths send synchronously by default. The durable file queue handles retries after transient failures, burst protection, and optional scheduled Commerce delays. Retry policy is exponential backoff over a small number of attempts; dead-lettered items remain in the delivery log. Idempotency keys prevent duplicate sends on retry.

### Permissions

| Action | Required role |
|--------|---------------|
| View metadata / health / log | Settings read |
| Edit secrets, routes, suppressions | Settings write / system admin |
| Test send / test channel | Settings write |
| MCP configure / test | Privileged AI role + approval |

Audit events record who changed what fields (never password values) and delivery failures as system events.

## Related

- [Connections provider recipes](connections-provider-recipes.md)
- [Migrate from Automation Setup email](connections-migration.md)
- [Connections API reference](../04-integration/connections-api-reference.md)
- [Connections MCP rules](connections-mcp-rules.md)
- [Automation overview](../05-automation/automation-overview.md)
- [Monitoring and health alerts](../07-operations/monitoring-alerts.md)
- [Commerce plugin](../06-extensibility/plugins/commerce.md)
