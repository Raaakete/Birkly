# Monitoring and health alerts

Configure email or webhook notifications when the CMS health endpoint reports **degraded** or **error** status (Phase 13 production ops).

## Summary

**Settings → Monitoring** lets administrators register an **alert email** and/or **webhook URL**. When `GET /api/health.php` returns `degraded` or `error` (for example writable directories missing, plugin state failure, or marketplace unreachable), Birkly dispatches a plain-text alert. Use this with `./scripts/production-smoke.sh` and external uptime checks for staging and production.

## Beginner

**Why monitoring matters**

Self-hosted Birkly depends on disk permissions, PHP extensions, and network reachability. A silent failure (unwritable `content/`, expired TLS, marketplace down) can break checkout or admin saves. Health alerts give you an early warning without logging into the server daily.

**Set up alerts**

1. Open **Settings** in the admin sidebar.
2. Click the **Monitoring** tab.
3. Enter an **Alert email** — a valid address that receives plain-text messages when health is not OK.
4. Optionally enter an **Alert webhook URL** — POST JSON payload to Slack, PagerDuty, or your own endpoint.
5. Click **Save settings** (global Save bar at the top).

**What triggers an alert**

| Health status | Typical cause |
|---------------|---------------|
| `degraded` | Non-critical check failed (for example marketplace slow or optional plugin warning) |
| `error` | Critical check failed (for example `content/` not writable, database connection failed) |

Alerts fire when the health endpoint is evaluated and status is `degraded` or `error`. OK status does not send mail.

**Verify it works**

1. Visit `/api/health.php` in a browser or with `curl` — note the JSON `status` and `checks` array.
2. Run `./scripts/production-smoke.sh` on the server — the script validates required health keys.
3. For staging, the agent digest (`scripts/send-agent-digest.php`) can include an ops section when smoke fails.

**Marketplace parity**

The Birkly Marketplace has its own `/health` endpoint and smoke script. Configure alerts on both CMS and marketplace hosts in production.

## Advanced Users

**Storage:** `settings/monitoring.json`

```json
{
  "alert_email": "ops@example.com",
  "alert_webhook_url": "https://hooks.example.com/birkly-health"
}
```

**Health report shape** (`api/health.php`):

- `status` — `ok`, `degraded`, or `error`
- `service` — `birkly-cms`
- `version` — CMS semver
- `checks[]` — `{ id, status, message }` catalog (disk writable, settings readable, plugins, marketplace reachability, etc.)

**Alert dispatch** (`core/health_alerts.php`):

- `birkly_health_dispatch_alerts($report)` — called after health evaluation when status warrants alert
- Email via PHP `mail()` when `alert_email` validates
- Webhook POST with JSON body when `alert_webhook_url` is set
- Mock hook: `$GLOBALS['birkly_health_alert_mock']` for tests (`health_alert_test.php`)

**Settings API:** Monitoring fields are part of global `save_all` (`monitoringAlertEmail`, `monitoringAlertWebhookUrl`). Category read: `GET /api/settings.php?category=monitoring`.

**Ops digest:** `scripts/send-agent-digest.php` includes smoke failure summary when staging cron runs the production smoke script.

## Related

- [Settings](../01-admin/settings.md)
- [Production deploy checklist](production-deploy-checklist.md)
- [Troubleshooting](troubleshooting.md)
- [Security](security.md)
