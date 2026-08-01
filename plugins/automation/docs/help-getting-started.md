# Getting started

This guide walks through a complete first automation: **when an entry is created → send an email**.

## Before you start

1. Plugin **Automation** is active (Settings → Plugins).
2. You can open **Automation** in the admin sidebar.
3. You know which collection should fire the workflow (or leave collection empty for all).

## Step 1 — Prove email delivery first

Do this **before** building the workflow. Otherwise you will debug the wrong layer.

1. Open **Automation → Setup**.
2. Under **Email delivery**, choose **SMTP** (cloud/Docker) or **Log only** (prove execution without an inbox).
3. Set **From name** and **From email** (must be allowed by your SMTP provider).
4. For SMTP: host, port (usually 587 + STARTTLS), username, password/API key → **Save email settings**.
5. Enter your address under **Send test email** → send.
6. Confirm either the inbox message **or** a row under **Recent sends** (Log mode).

If the test email fails, stop here and fix Setup. Workflows cannot deliver better than Setup.

## Step 2 — Create the workflow

1. Open the **Automations** tab → **Create**.
2. You land on the dedicated **Editor** canvas.
3. Name it, e.g. `Notify on new entry`.
4. Keep **Enabled** checked when ready to go live.

### Trigger

1. Drag **Entry created** (or pick from the palette).
2. Optionally set **Collection** so only that content type fires the workflow.

### Action

1. Drag **Send Email**.
2. Connect the trigger output port to the email input port.
3. Fill:
   - **To** — a real address, or a template like `{{entry.email}}` if the entry has that field
   - **Subject** — e.g. `New entry: {{entry.title}}`
   - **Body** — e.g. `A new entry was created in {{collection}}.`

Template variables use `{{path}}` syntax (double braces).

### Save

Click **Save**. The editor URL will include the automation id.

## Step 3 — Dry-run (optional, never sends)

1. Click **Test (dry run)**.
2. Read the steps. Email lines should say **not sent - dry run**.
3. Setup warnings appear here if SMTP/mail is incomplete.

Dry-run success only means “config looks valid.”

## Step 4 — Live execution

Pick one:

**A. Confirm & Run Live** (from the dry-run panel)

- Executes actions with sample/manual context
- Real SMTP/Log/webhook side effects

**B. Create a real entry**

1. Open Collections → your collection → create an entry
2. Automation queues a job and tries to process it immediately
3. Open the automation **Logs** (list page) — you should see a success or error
4. If logs stay empty → **Setup → Process queue now**

## Step 5 — Verify delivery

- **SMTP**: check inbox + spam
- **Log**: Setup → Recent sends
- **Slack**: check the channel (only after live run)

## Common first-run mistakes

| Mistake | Result |
|---------|--------|
| Only clicking Test | No email — dry-run by design |
| PHP mail() on cloud host | Action may “succeed”, inbox empty |
| Wrong From domain for SMTP | Provider rejects send |
| Automation disabled | Trigger ignored |
| Collection filter mismatch | Trigger skipped |
| Expecting Slack OAuth in Setup | Slack only needs a webhook URL on the action |

## Next

- Add a **condition** (e.g. status is published)
- Branch with **If true / Else** on the canvas
- See **Examples** for Slack + publish recipes
