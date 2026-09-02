# Migrate from Automation Setup email

Before Phase 65, transactional email settings lived inside **Automation → Setup** in a file called `automation_mail.json`. That made Automation a hidden dependency for Commerce receipts, form notifications, password resets, and health alerts. Phase 65 moves email into **Settings → Connections**. This guide explains how to migrate without losing sends.

## Summary

If you previously configured SMTP or Log mail under **Automation → Setup**, those settings are now read by **Settings → Connections** during a short dual-read period. The recommended path is to create a new Email Connection in **Settings → Connections**, map the `transactional_email` alias to it, and let Automation become a wrapper around the shared pipe. After migration, Automation can be deactivated without breaking Commerce, forms, User, or Events email. The old Automation Setup email UI redirects to Connections.

## Beginner

### What changed

| Before P65 | After P65 |
|------------|-----------|
| SMTP host, port, username, password in Automation → Setup | Same fields in **Settings → Connections** as an Email Connection |
| `automation_send_mail` sends mail | `birkly_send_mail` sends mail; Automation actions call it |
| Commerce depends on Automation being active | Commerce uses Connections directly |
| Form notifications use PHP `mail()` | Form notifications use Connections alias |
| Password resets use plugin stub SMTP | Password resets use Connections alias |

### Who needs to migrate

You need to migrate if any of the following are true:

- You configured SMTP under **Automation → Setup**.
- You rely on Commerce order emails.
- You use public form notifications.
- You use password reset or email verification emails.
- You use health alert emails.

You do not need to migrate Ops Marketplace license emails; those use a separate runtime.

### Migration steps

1. Open **Settings → Connections**.
2. Click **Add connection** and choose the same transport you had in Automation (SMTP, Resend, etc.).
3. Copy the values from Automation → Setup into the Connection fields.
   - Host, port, encryption, username, password for SMTP.
   - API key and from address for ESPs.
4. Give the Connection a clear name such as `Primary transactional email`.
5. Save and click **Test send**.
6. Go to **Aliases**, find `transactional_email`, and set it to this Connection.
7. Visit **Automation → Setup**. The email section now shows a summary and a link to **Settings → Connections**. It no longer stores its own SMTP password.
8. Send a test Commerce receipt or submit a form to confirm delivery.

### What happens to old settings

During the dual-read period, Birkly reads in this order:

1. Connections core (`transactional_email` alias).
2. Legacy `automation_mail.json` if no alias is mapped.
3. User plugin `email_settings` if no other source exists.

The first time you save a Connection and map the alias, the legacy file is marked migrated. New writes no longer update `automation_mail.json`. Eventually the legacy dual-read is removed, so do not rely on it long-term.

### Commerce-specific notes

Commerce previously showed `ok: true` even when email was only logged and not sent. After migration:

- Commerce sends through `birkly_send_mail` using the `transactional_email` alias.
- The delivery log shows the real status (`accepted`, `logged`, `failed`, etc.).
- If no alias is mapped, Commerce shows an empty state pointing to Connections instead of faking success.

### Stripe receipt vs Commerce thank-you

Stripe can send its own receipt when you set `receipt_email`. Commerce also sends thank-you / shipping / donation emails through Connections. Both may fire. Make sure your templates do not double-thank the customer.

### Form notifications

Public form notifications now use the `transactional_email` alias. A common bug fixed in P65 is the mismatch between `notify_email` and `notification_email` settings. After migration, the form notify field is unified to the Connections alias.

## Advanced Users

### Dual-read / dual-write mechanics

The migration is designed for zero downtime:

1. Core Connections and the encrypted store ship first.
2. Consumers read core first, then fall back to legacy sources.
3. The first admin save to Connections writes the canonical record and marks the legacy source migrated.
4. `automation_send_mail` becomes a thin wrapper around `birkly_send_mail`.
5. Automation Setup SMTP UI redirects to Connections.
6. Legacy writes stop.
7. One-shot migration script cleans remaining installs at the deprecation freeze (P65-M14).

### Consumer cutover table

| Consumer | New call | Notes |
|----------|----------|-------|
| `birkly-plugin-automation/includes/mailer.php` | `birkly_send_mail` | Wrapper; Setup redirect. |
| `birkly-plugin-commerce/includes/CommerceEmailService.php` | `birkly_send_mail` / `birkly_notify` | Honest statuses; remove fake `ok`. |
| `birkly/api/public_submit.php`, `public/collections.php`, `public_sub_entry.php`, `secure_forms.php` | `birkly_send_mail` | Fix `notify_email` vs `notification_email`. |
| `birkly/api/invitations.php`, `birkly/core/health_alerts.php` | `birkly_send_mail` alias `ops_alerts` | May share `transactional_email`. |
| `birkly-plugin-user/includes/EmailVerification.php` | `birkly_send_mail` alias `transactional_email` | Remove stub SMTP. |
| `birkly-plugin-events` | `birkly_send_mail` / `birkly_notify` | No private SMTP UI. |

### Migration script for unattended installs

For installs that cannot use the admin UI, a one-shot migration helper reads `automation_mail.json` and creates the equivalent Connection. Run it from the CLI or as part of P65-M14 deprecation freeze. It preserves existing secrets by writing them into the encrypted store.

### Testing the migration

1. Deactivate the Automation plugin.
2. Submit a public form.
3. Place a test Commerce order.
4. Request a password reset.
5. Check the delivery log for statuses.

If all four deliver correctly with Automation off, the migration is complete.

### Rollback during dual-read

If a Connection test fails, legacy settings still work until the deprecation freeze. Re-enable Automation or correct the legacy file, then retry the Connection setup. Do not wait until M14 to validate; test as soon as you create the Connection.

## Related

- [Connections](connections.md)
- [Connections provider recipes](connections-provider-recipes.md)
- [Connections API reference](../04-integration/connections-api-reference.md)
- [Connections MCP rules](connections-mcp-rules.md)
- [Automation overview](../05-automation/automation-overview.md)
- [Commerce plugin](../06-extensibility/plugins/commerce.md)
