# Automation — AI context

Official Birkly plugin for event-driven workflows (triggers → conditions → actions).

## Critical product facts

- **Test / dry-run never sends** email, Slack, Teams, or webhooks.
- **Setup** configures email transport (SMTP recommended, Log, or PHP mail) and queue processing.
- Cloud/Docker hosts almost never deliver PHP `mail()` — prefer SMTP; use **Send test email** to verify.
- Slack/Teams need an **Incoming Webhook URL on the action**, not OAuth in Setup.
- Triggers enqueue jobs; current builds also process a small batch inline after enqueue. **Process queue now** drains stuck jobs. Cron still recommended.
- Canvas persists `graph` with If-true / Else edges; runtime walks branches.

## Admin surfaces

- List + Setup + Documentation tabs (`?page=automation`)
- Dedicated editor (`?page=automation-editor`)

## Help topics (in-plugin Documentation tab)

overview, getting-started, setup, email, slack-webhooks, triggers, workflows, examples, troubleshooting
