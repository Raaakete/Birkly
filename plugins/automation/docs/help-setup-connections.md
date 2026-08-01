# Setup & connections

The **Setup** tab is the control panel for delivery. Automations do not magically send email or post to Slack until this layer works.

## Why Setup exists

| Layer | Question it answers |
|-------|---------------------|
| Workflow editor | What should happen? |
| Setup | Can this server actually deliver it? |
| Queue runner | Did waiting jobs get processed? |

Without Setup, users often see: empty logs, dry-run “success”, and no email. That is a Setup/runner problem, not a canvas problem.

## Checklist items explained

### 1. Workflow runner (queue)

**What it is:** When a trigger fires, Birkly writes a job to a queue file, then tries to process a small batch immediately.

**Why it matters:** If jobs never process, actions never run and logs stay empty.

**What to do:**

- Click **Process queue now** if jobs are pending
- Optionally install minute cron (shown on Setup) for backlog/schedules

### 2. Email transport

**What it is:** How Send Email actions leave the server.

| Transport | Use when | Delivers to inbox? |
|-----------|----------|--------------------|
| **SMTP** | Production / cloud / Docker | Yes (if credentials + From are valid) |
| **Log only** | Local testing / proving the workflow | No — writes to Recent sends |
| **PHP mail()** | Rare local hosts with a working MTA | Often **no** on cloud |

**Why it matters:** Cloud hosts almost never deliver PHP `mail()`. Prefer SMTP.

### 3. From address

**What it is:** The sender shown on outbound mail.

**Why it matters:** SMTP providers reject unverified domains; Gmail/etc. spam-folder `localhost` / `.local` addresses.

### 4. Outbound HTTP (Slack / Teams / webhooks)

**What it is:** PHP curl capability for HTTPS posts.

**Why it matters:** Slack/Teams/webhook actions need outbound HTTP. There is **no** Slack OAuth button in Setup — you paste an Incoming Webhook URL into each action.

## Buttons on Setup

| Button | Effect |
|--------|--------|
| **Save email settings** | Persists transport + From + SMTP |
| **Send test email** | Real send with current transport (not dry-run) |
| **Process queue now** | Drain pending automation jobs now |
| **Refresh status** | Reload checklist / recent sends |

## Recommended order

1. Configure SMTP (or Log)
2. Send test email until it works
3. Build workflows
4. Live-run or create entries
5. Use Process queue now only if jobs stick

## Cron (optional but recommended)

```bash
* * * * * php plugins/automation/cli/process_queue.php 10
```

Run from the CMS root. HTTP token URLs are a fallback when CLI cron is impossible (see Setup → Alternative URLs).

## Security notes

- SMTP password is stored in CMS settings storage; leave the password field blank on save to keep the existing secret
- Cron HTTP URLs include a secret token — keep them masked; do not paste into public tickets
- Webhook URLs in actions are secrets — treat like passwords
