# Automation overview

Automation runs workflows when something happens in Birkly — an entry is created, a status changes, a schedule fires, a webhook arrives, or another plugin emits an event.

## What Automation is for

Typical uses:

- Email an editor when a new entry is created
- Notify Slack when content is published
- Call an external webhook after a form submission
- Update a field or status as a follow-up step
- Run on a schedule (daily digest style jobs)

## Mental model (important)

An automation has three parts:

1. **Trigger** — *when* it should start (e.g. Entry created)
2. **Conditions** (optional) — *whether* it should continue
3. **Actions** — *what* to do (Send Email, Slack, webhook, …)

Creating the workflow is not enough. Delivery channels must be configured under **Automation → Setup**:

| You want… | You must configure… |
|-----------|---------------------|
| Email | SMTP (recommended) or Log / PHP mail under Setup |
| Slack / Teams | Incoming webhook URL **inside the action** (no OAuth login) |
| Generic webhook | HTTPS URL inside the action |
| Jobs to actually run | Queue runner (auto after trigger + optional cron) |

## Test vs live (do not confuse these)

| Mode | What it does | Sends email / Slack? |
|------|--------------|----------------------|
| **Test / dry-run** | Validates config and shows steps | **No** |
| **Confirm & Run Live** | Executes actions for real | **Yes** |
| **Real trigger** (create entry, etc.) | Queues + runs the workflow | **Yes** |

If Test says “succeeded” but no email arrives, that is expected until you run live *and* Setup email transport works.

## Quick start (email on entry create)

1. Open **Automation → Setup**.
2. Set transport to **SMTP**, fill host/port/user/password, set a real From address, **Save**.
3. Click **Send test email** to your inbox. Fix Setup until this works.
4. Create a workflow: trigger **Entry created** → action **Send Email**.
5. Enable it, save, then either **Confirm & Run Live** or create a real entry.
6. If logs are empty, click **Process queue now** on Setup.

## Where to read next

- **Getting started** — first workflow, step by step
- **Setup & connections** — what the Setup tab is for
- **Email delivery** — SMTP, Log, PHP mail, troubleshooting inbox
- **Slack, Teams & webhooks** — Incoming Webhooks
- **Triggers & blocks** — filter reference
- **Workflow builder** — canvas, branches, templates
- **Examples** — copyable recipes
- **Troubleshooting** — empty logs, no email, dry-run confusion
