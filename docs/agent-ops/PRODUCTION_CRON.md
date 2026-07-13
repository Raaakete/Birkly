# Production cron — scheduled smoke & J-HEALTH

P28-M13 wires `scripts/scheduled-production-smoke.sh` for cron-friendly production checks:

1. `production-smoke.sh` — CMS health, build info, marketplace reachability
2. **J-HEALTH** journey subset — `journey-runner.php --journey J-HEALTH` when available, otherwise inline health checks (writable dirs, marketplace ping, admin login page)
3. On failure — dispatches alerts via `core/health_alerts.php` using `settings/monitoring.json`
4. Writes combined status to `logs/last-production-smoke.json`

View last results in **Admin → Settings → Operations**.

## Environment

| Variable | Purpose |
|----------|---------|
| `BIRKLY_SMOKE_BASE_URL` | CMS base URL (e.g. `https://example.com/Birkly-0.1`) |
| `BIRKLY_MARKETPLACE_URL` | Marketplace base (default `https://marketplace.birkly.io`) |
| `BIRKLY_SMOKE_SKIP_MARKETPLACE=1` | Skip marketplace probe (local/CI) |
| `BIRKLY_JOURNEY_BASE_URL` | Override base for journey-runner (defaults to smoke base) |
| `BIRKLY_SMOKE_LOG_DIR` | Log directory (default `logs/`) |

Configure alert targets in **Settings → Monitoring** (`settings/monitoring.json`):

```json
{
  "alert_email": "ops@example.com",
  "alert_webhook_url": "https://hooks.example.com/health"
}
```

## Cron example (hourly)

Run from the Birkly install root (or set paths explicitly):

```cron
# Birkly CMS — hourly production smoke + J-HEALTH (P28-M13)
0 * * * * cd /var/www/birkly && \
  BIRKLY_SMOKE_BASE_URL=https://your-domain.com/Birkly-0.1 \
  BIRKLY_MARKETPLACE_URL=https://marketplace.birkly.io \
  ./scripts/scheduled-production-smoke.sh >> logs/production-smoke-cron.log 2>&1
```

Subdirectory install example:

```cron
15 * * * * cd /var/www/kekaba/Birkly-0.1 && \
  BIRKLY_SMOKE_BASE_URL=https://service.kekaba.com/Birkly-0.1 \
  ./scripts/scheduled-production-smoke.sh
```

## Daily agent digest

Separate from smoke cron — daily summary email:

```cron
45 4 * * * cd /var/www/birkly && php scripts/send-agent-digest.php
```

(04:45 UTC ≈ 06:45 CEST; adjust for your timezone.)

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Smoke and J-HEALTH passed |
| `1` | Smoke or J-HEALTH failed (alert dispatched when configured) |
| `2` | Script error (missing dependencies, smoke script not found) |

## Manual run

```bash
cd /path/to/birkly
chmod +x scripts/scheduled-production-smoke.sh
BIRKLY_SMOKE_SKIP_MARKETPLACE=1 ./scripts/scheduled-production-smoke.sh http://127.0.0.1:8080
cat logs/last-production-smoke.json
```

## Related

- `scripts/production-smoke.sh` — core smoke checks
- `docs/reviews/P16-production-deploy-playbook.md` — deploy operations
- `admin/docs/snapshots/monitoring-alerts.md` — alert configuration
