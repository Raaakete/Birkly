# Automation Overview

Automation in Birkly lets you run workflows when something happens (a trigger) — on a schedule, when the CMS receives a webhook, or when an entry or sub-entry changes. Each workflow can check conditions and then run actions (e.g. send a Slack message, call an external URL, or update content).

## Summary

**Automation** = "When X happens, do Y." You create **workflows** in the admin under **Automation** (a **deactivatable plugin** -- disable it under Settings -> Plugins to remove hooks, API routes, and the sidebar entry). Each workflow has a **trigger** (e.g. "Every day at 9:00", "When a webhook is received", or "When an entry is created"), optional **conditions** (e.g. "only if the new entry is in the Blog posts collection"), and **actions** (e.g. "POST to Slack", "Send email", "Call a URL"). Use it to notify your team, sync with other tools, or react to content changes -- without writing code. See [Triggers](triggers.md), [Conditions and actions](conditions-and-actions.md), and [Webhooks](webhooks.md) for details.

## Beginner

**Automation** means "when something happens, Birkly does something for you automatically." You set it up once in the admin; after that, it runs without you clicking anything.

**What you can do with it**

- **Scheduled tasks:** "Every Monday at 9:00, do X" or "Every hour, check something and call an API."
- **Reactive workflows:** "When someone submits the contact form, send a Slack message to the team" or "When a new entry is published, notify another system."
- **Content-driven workflows:** "When a comment is added to a blog post, send a moderation alert." "When a product review is submitted, call a rating API."
- **Integrations:** Connect Birkly to Slack, email, CRMs, or your own backend by having Birkly call a URL or send a message.

You don't need to set up cron jobs or write server code for basic flows -- you configure them in the Birkly admin.

**Main pieces**

1. **Trigger** -- When the workflow runs.
   - **Scheduled:** At a fixed time (e.g. daily at 9:00, every 5 minutes).
   - **Webhook:** When another app or your server sends an HTTP request to a special Birkly URL (e.g. after a form is submitted or a payment is received).
   - **Entry created / updated / deleted:** Automatically when an entry (or sub-entry) changes in a chosen collection. No external call needed.
2. **Conditions** (optional) -- "Only run the rest if ..." (e.g. only if the submission has an email, or only when the entry is in a specific sub-collection).
3. **Actions** -- What actually runs: e.g. "Post to Slack", "Send an email", "Call this URL with this data."

**Example: "Notify team when contact form is submitted"**

1. In the admin, go to **Automation** and create a new workflow.
2. **Trigger:** Choose **Webhook**. Birkly shows you a URL (e.g. `https://your-cms.com/api/automation_webhook.php?workflow=abc123`). When your form handler or another service POSTs to this URL, the workflow starts.
3. **Conditions:** Optional. e.g. "Payload has field `email`" so you only notify when the submission looks valid.
4. **Actions:** Add "Slack" -- enter your Slack webhook URL and a message like "New contact: {{ payload.name }} -- {{ payload.message }}".
5. Save and enable the workflow.
6. On your website, when the form is submitted, your backend (or a Birkly form endpoint) can POST to the workflow's webhook URL. Birkly then runs the workflow and sends the Slack message.

**Example: "Every day at 9:00, call an API"**

1. Create a workflow with a **Scheduled** trigger.
2. Set the schedule to "Daily at 9:00" (and your timezone).
3. Add an **HTTP** action: e.g. GET or POST to your own API or a third-party service.
4. Save and enable. The server will run this workflow every day at 9:00.

**Example: "Moderate new blog comments"**

1. Create a workflow with an **Entry created** trigger.
2. **Collection picker:** select "Blog posts". **Sub-collection filter:** select "Comments".
3. The workflow fires whenever a new comment is submitted under any blog post.
4. Add a Slack action: "New comment on '{{ entry.title }}' -- author: {{ entry.author }}. Review in admin."
5. Save and enable.

**Testing a workflow (dry-run)**

Before enabling a workflow for real, use the **Test** (dry-run) button in the workflow editor. Dry-run logs show exactly what would happen -- which conditions would pass, which actions would fire, and what data would be sent -- **without actually executing the actions**. Nothing is sent to Slack, no HTTP calls are made, no content is changed. This lets you verify your workflow is correct before it goes live.

To run for real (not just dry-run), you must explicitly confirm in the UI after reviewing the dry-run log.

**Where to configure**

In the admin sidebar, open **Automation** (or **Workflows**). Create a new workflow, pick a trigger, add conditions if needed, then add one or more actions. For webhook triggers, the admin shows the URL to call; for scheduled triggers, set the time and timezone; for entry triggers, use the collection and sub-collection pickers.

**Good practices**

- **Secrets:** Put API keys and webhook URLs in the workflow config or environment variables -- not in screenshots or public docs.
- **Dry-run first:** Always test with dry-run before enabling. Check the log to confirm the right data flows to the right actions.
- **Errors:** If an action fails (e.g. Slack is down), check the Automation logs in the admin to see the error and whether you need to fix the URL or credentials.
- **Rate limits:** If you run workflows very often, respect external APIs' rate limits; the system may throttle or queue runs.

## Advanced Users

**Building blocks**

| Block | Role |
|-------|------|
| **Trigger** | When the workflow runs: Scheduled (cron-like), Webhook (HTTP request), Entry created/updated/deleted (CMS event). |
| **Conditions** | Optional filters: payload field equals X, time in range, entry collection/sub-collection, etc. AND/OR logic. |
| **Actions** | Steps executed in order: Slack, HTTP request, Email, internal (e.g. update entry, log). |
| **Dry-run / test** | Executes condition + action logic against real or simulated payload; logs what would happen; **no side effects** unless the user confirms real execution. |
| **Queue / rate limit** | System may queue or throttle to avoid overloading external services. |

**Where it runs**

- Triggers: scheduler (cron or internal) for time-based; webhook endpoint for HTTP-triggered; CMS hooks for entry-triggered.
- Conditions and actions run in order; conditions gate whether actions execute.

**Sub-entry triggers**

Entry triggers fire on both regular entries and sub-entries. The trigger payload includes `is_sub_entry: true`, `parent_collection`, `parent_entry_id`, and `sub_collection` when the event source is a sub-entry. Use the **sub-collection picker** in the trigger config to restrict a workflow to a specific sub-collection (e.g. only react to new "comments" on "blog_posts", not to new blog posts themselves). See [Sub-Collections](../02-content/sub-collections.md) and [Triggers](triggers.md) for details.

**Dry-run mode**

The automation engine supports a dry-run execution path: all condition evaluation and action template rendering runs in full, but HTTP calls, email sends, and other external side effects are suppressed. The dry-run log includes: trigger payload, condition results, per-action resolved template and suppressed output. Real execution requires explicit user confirmation in the UI -- this prevents accidental sends during testing and aligns with D4 (automation test-run = dry-run log only unless user confirms).

**Idempotency**

If a workflow can run twice for the same event (e.g. webhook retried), design actions to be idempotent where possible (e.g. "create entry" only if it doesn't already exist, or use a unique key).

**Secrets and errors**

- Store API keys and tokens in workflow config or env; never in templates or logs.
- On action failure, the engine may retry or log; check automation logs. Retry/backoff behavior is version-dependent.

**Relation to webhooks**

- **Incoming webhook** = trigger for a workflow (something calls Birkly).
- **Outgoing webhook** = Birkly calls your URL when an event happens (e.g. entry published). See [Webhooks](webhooks.md).

## Related

- [Triggers](triggers.md)
- [Commerce recipe pack](commerce-recipes.md) — import post-purchase, dunning, low-stock, shipped starters
- [Conditions and actions](conditions-and-actions.md)
- [Webhooks](webhooks.md)
- [Public forms](../04-integration/public-forms.md)
- [Sub-collections](../02-content/sub-collections.md)
