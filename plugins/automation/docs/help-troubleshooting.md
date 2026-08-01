# Troubleshooting

## Empty automation logs after creating an entry

**Meaning:** The workflow likely never executed (or logging did not run).

Check in order:

1. Automation is **Enabled**
2. Trigger type matches the event (`entry_created` vs `entry_updated`, etc.)
3. Collection / status filters match the entry you created
4. Open **Setup → Process queue now**
5. Refresh Logs on the automation list/editor

With current Automation builds, create/update should process a small batch immediately. If your install is older, cron or Process queue now is required.

## Test succeeded but no email

**Expected** if you only ran Test/dry-run. Dry-run never sends.

Do this:

1. Setup → Send test email (must succeed first)
2. Confirm & Run Live, or create a real entry
3. Check spam + Setup → Recent sends
4. Read Logs for action errors

## Live run / action “sent” but inbox empty

Usually **PHP mail()** on a cloud host, or SMTP From/domain issues.

Fix:

1. Switch Setup transport to **SMTP**
2. Use a verified From address
3. Send test email until the provider accepts it
4. Check provider activity logs

## SMTP errors

| Error text | Fix |
|------------|-----|
| Could not connect | Wrong host/port; firewall blocking outbound SMTP |
| AUTH failed / 535 | Username/password/API key wrong |
| Sender rejected | Verify domain / From at provider |
| STARTTLS failed | Try port 465 + SSL, or 587 + TLS per provider docs |

## Slack/Teams quiet after Test

Dry-run does not post. Use Confirm & Run Live. Confirm the Incoming Webhook URL is pasted on the **action**, not somewhere in Setup.

## Webhook URL rejected

Automation blocks localhost/private IPs and odd ports. Use a public `https://` URL on 443.

## Workflow runs for the wrong collection

Clear or correct the trigger’s collection filter. Empty filter = all collections.

## Rate limit / stop on failure

- Rate limits can skip executions under heavy load
- **Stop on failure** halts later actions when one fails — check Logs for the first error

## Still stuck

1. Setup checklist — fix every **fail**
2. Send test email
3. Process queue now
4. Confirm & Run Live on a minimal Log or Email workflow
5. Read the exact error string in Logs (not only “failed”)
