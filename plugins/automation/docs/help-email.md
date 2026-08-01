# Email delivery

Send Email actions use the transport configured under **Automation → Setup**.

## Choose a transport

### SMTP (recommended)

Works on Dokploy, Docker, and most VPS hosts.

Typical providers:

- Brevo (Sendinblue)
- Mailgun
- Postmark
- Amazon SES
- Your hosting SMTP (cPanel, etc.)

Common settings:

| Field | Usual value |
|-------|-------------|
| Host | `smtp.…` from provider docs |
| Port | `587` |
| Encryption | STARTTLS |
| Username | mailbox or API user |
| Password | SMTP password or API key |
| From | Verified sender / domain |

After save, always click **Send test email**.

### Log only

Writes accepted messages to **Recent sends** instead of the network.

Use this to prove:

- the trigger fired
- the queue processed
- the email action ran

Then switch to SMTP for real inboxes.

### PHP mail()

Calls PHP `mail()`. Fine on some classic shared hosts with a local MTA. On cloud containers it often returns success while **nothing is delivered**. Prefer SMTP.

## Templates in email fields

Subject and body support `{{variable}}` placeholders, for example:

```text
Subject: New {{collection}} entry: {{entry.title}}
Body: Created by {{user.name}} at {{entry.created_at}}
```

Available variables depend on the trigger context (entry fields, collection, status change fields, webhook payload, etc.).

## Proving delivery

1. Setup → Send test email → your address
2. Check inbox **and spam**
3. Check Setup → Recent sends (method column shows `smtp`, `mail`, or `log`)
4. Run the workflow live (not only Test)
5. Open automation Logs for action errors

## Failure messages (what they mean)

| Message / symptom | Likely cause | Fix |
|-------------------|--------------|-----|
| Test says succeeded, no email | Dry-run only | Confirm & Run Live + SMTP |
| SMTP connect error | Wrong host/port/firewall | Fix host/port; allow outbound 587/465 |
| SMTP auth failed | Bad user/password | Reset SMTP credentials |
| Recipient rejected | Provider policy / bad To | Verify To; check provider logs |
| From rejected | Unverified domain | Verify domain / use allowed From |
| Logs empty | Queue not processed | Process queue now |
| Action error in logs | Handler threw | Read log detail; fix Setup or action fields |

## SPF / DKIM / DMARC (advanced)

For production inboxes, configure DNS at your domain registrar as your SMTP provider instructs. Without SPF/DKIM, messages may land in spam even when SMTP accepts them.

## Security

- Do not put SMTP passwords in workflow action fields — they belong in Setup only
- Prefer API keys with send-only scope when the provider offers them
- Rotate credentials if a URL or backup leaked
