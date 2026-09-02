# Connections provider recipes

These recipes walk through adding each supported email provider in **Settings → Connections**. Each recipe assumes you already have an account with the provider and a verified domain or sender address. If you are unsure which provider to choose, start with a managed Email Service Provider (ESP) such as Resend or Postmark rather than your host’s SMTP, because ESPs handle deliverability, bounces, and DNS records for you.

## Summary

Birkly supports five email transports out of the box: **SMTP** (generic), **Resend**, **Postmark**, **Amazon SES**, and **Mailgun**. For each provider you create an **Email** Connection in **Settings → Connections**, enter the provider-specific fields, and click **Test**. The recipes below list the exact values to paste and common pitfalls. After setup, map the Connection to an alias such as `transactional_email` so plugins can use it without knowing which provider you picked.

## Beginner

### Before you start

1. Open **Settings → Connections**.
2. Click **Add connection** and choose the **Email** type.
3. Pick the provider/transport that matches your account.
4. Give the Connection a clear name, for example `Resend production` or `Mailgun EU`.
5. Save, then click **Test send**.
6. If the test succeeds, go to **Aliases** and map `transactional_email` to this Connection.

### Generic SMTP

Use SMTP when you have your own mail server or a host-provided relay.

Fields you need:

| Field | Example | Notes |
|-------|---------|-------|
| Host | `smtp.example.com` | Your mail server hostname. |
| Port | `587` or `465` | 587 usually uses STARTTLS; 465 usually uses SSL. |
| Encryption | `STARTTLS`, `SSL`, or `None` | Match your port. |
| Username | `you@example.com` | Often the full email address. |
| Password | app-specific password | Use an app password, not your login password. |
| From address | `noreply@example.com` | Must be allowed by the server. |
| From name | `Birkly` | Display name recipients see. |

Common problems:

- **Docker hosts** often block outbound SMTP on port 25/587. If test fails with a network timeout, switch to an ESP or ask your host to open the port.
- **Gmail / Outlook personal accounts** require app-specific passwords and may have strict sending limits. For production sites, use a transactional ESP instead.
- Wrong encryption/port pair is the most common failure.

### Resend

Resend is a transactional email service with a simple HTTP API.

Fields you need:

| Field | Example | Notes |
|-------|---------|-------|
| API key | `re_xxxxxxxxxxxxxxxx` | Create in Resend dashboard → API keys. |
| From address | `onboarding@yourdomain.com` | Must be a verified domain or a Resend-provided test address. |
| From name | `Your site` | Display name. |

After saving:

1. Click **Test send**.
2. If the domain is not verified, Resend accepts mail only from a test address or may reject depending on your plan.
3. Add the Resend bounce/complaint webhook URL in Connections → Webhooks if you want automatic suppression updates.

### Postmark

Postmark focuses on transactional email and provides clear delivery events.

Fields you need:

| Field | Example | Notes |
|-------|---------|-------|
| Server API token | `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` | From a Postmark server, not an account token. |
| From address | `noreply@yourdomain.com` | Must match a verified sender signature. |
| From name | `Your site` | Display name. |

After saving:

1. Click **Test send**.
2. In Postmark, copy the inbound webhook URL from Connections → Webhooks and paste it into Postmark → Webhooks so bounces and spam complaints update Birkly’s suppression list.

### Amazon SES

Birkly prefers the Amazon SES HTTPS API adapter. You can also use SES-SMTP credentials through the generic SMTP transport if you prefer.

Fields for the SES HTTPS API adapter:

| Field | Example | Notes |
|-------|---------|-------|
| Access key ID | `AKIA...` | IAM user with `ses:SendRawEmail` permission. |
| Secret access key | `...` | Stored encrypted. |
| Region | `us-east-1` | Your SES region. |
| From address | `noreply@yourdomain.com` | Must be a verified identity in SES. |
| From name | `Your site` | Display name. |

IAM permission needed:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["ses:SendEmail", "ses:SendRawEmail"],
      "Resource": "*"
    }
  ]
}
```

After saving:

1. Click **Test send**.
2. SES in sandbox mode only sends to verified addresses. Move out of sandbox for production.
3. Configure SNS → HTTPS webhook to `/api/webhooks/connections/{id}` for bounce/complaint delivery.

### Mailgun

Mailgun provides both US and EU endpoints.

Fields you need:

| Field | Example | Notes |
|-------|---------|-------|
| API key | `key-xxxxxxxxxxxxxxxx` | From Mailgun dashboard → Settings → API keys. |
| Domain | `mg.yourdomain.com` | Must be active in Mailgun. |
| Region | `US` or `EU` | Choose the region where your domain is hosted. |
| From address | `noreply@mg.yourdomain.com` | Must belong to the configured domain. |
| From name | `Your site` | Display name. |

After saving:

1. Click **Test send**.
2. Add Mailgun webhook URLs for permanent fail and spam complaints in Connections → Webhooks.

### DNS: SPF, DKIM, DMARC

Birkly does not generate DKIM keys for you; your provider does. The provider recipe is incomplete until DNS is correct:

1. Add the provider’s SPF include to your domain’s TXT record.
2. Add the DKIM record the provider gives you.
3. Add a DMARC policy record such as `v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com`.

The Email Connection checklist links to provider-specific DNS instructions. Incorrect DNS is the most common cause of messages landing in spam.

### Choosing between providers

| Choose this | When |
|-------------|------|
| **Resend** | Fastest setup, generous free tier, strong developer focus. |
| **Postmark** | Strictly transactional, excellent deliverability reputation, detailed events. |
| **Amazon SES** | Lowest cost at scale, already in AWS, comfortable managing IAM/SNS. |
| **Mailgun** | Need EU residency, complex routing, or existing Mailgun workflows. |
| **SMTP** | You already run a reliable mail server and cannot use an ESP. |
| **Log** | Local development only; never for production. |

## Advanced Users

### Environment-variable overrides

Each provider field can be overridden by an environment variable. When an env override is active, the admin shows **Managed by environment** and the field is read-only. Common variables:

| Provider | Variable examples |
|----------|-------------------|
| SMTP | `BIRKLY_SMTP_HOST`, `BIRKLY_SMTP_PORT`, `BIRKLY_SMTP_USER`, `BIRKLY_SMTP_PASS`, `BIRKLY_SMTP_FROM` |
| Resend | `BIRKLY_RESEND_API_KEY`, `BIRKLY_RESEND_FROM` |
| Postmark | `BIRKLY_POSTMARK_API_TOKEN`, `BIRKLY_POSTMARK_FROM` |
| SES | `BIRKLY_SES_ACCESS_KEY`, `BIRKLY_SES_SECRET_KEY`, `BIRKLY_SES_REGION`, `BIRKLY_SES_FROM` |
| Mailgun | `BIRKLY_MAILGUN_API_KEY`, `BIRKLY_MAILGUN_DOMAIN`, `BIRKLY_MAILGUN_REGION`, `BIRKLY_MAILGUN_FROM` |

Exact variable names may be finalized when core implementation reports stable API names.

### Provider adapter interface

Core exposes a thin adapter interface. Official adapters are native PHP + cURL; no Composer SDK is required. A custom adapter can be registered by a plugin if it implements the contract:

- `send($envelope)` — accepts to, from, subject, html, text, attachments, headers, idempotency key.
- `parseResponse($httpResponse)` — returns provider message id or error.
- `webhookHandlers()` — returns map of event types to suppression/ status actions.

### Webhook endpoints per provider

Bounce/complaint webhooks are registered under **Connections → Webhooks**. The URL pattern is `/api/webhooks/connections/{id}`. Provider-specific setup:

| Provider | Webhook events to enable |
|----------|--------------------------|
| Resend | `bounce`, `complaint`, `delivery` |
| Postmark | `Bounce`, `SpamComplaint` |
| SES | SNS → HTTPS to the endpoint; subscribe the endpoint to `Bounce` and `Complaint` topics |
| Mailgun | `permanent_fail`, `complained` |

The verify helper checks the provider signature before acting on the payload.

### SES SigV4 fallback

If the native SES HTTPS adapter is unavailable on your build, use SES-SMTP credentials through the generic SMTP transport. Retrieve SMTP credentials from the SES console, choose region-specific host such as `email-smtp.us-east-1.amazonaws.com`, port 587, and STARTTLS. Document this path in the Connection notes.

### Rate limits and queueing

Providers enforce rate limits. Birkly respects them with per-connection soft caps and queues bursts. If you hit a rate limit, the status remains `queued` and retries automatically. For high-volume sites, warm up the provider domain reputation before sending large batches.

## Related

- [Connections](connections.md)
- [Migrate from Automation Setup email](connections-migration.md)
- [Connections API reference](../04-integration/connections-api-reference.md)
- [Connections MCP rules](connections-mcp-rules.md)
- [Monitoring and health alerts](../07-operations/monitoring-alerts.md)
