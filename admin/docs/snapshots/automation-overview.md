# Automation

Automation lets you run workflows when something happens — on a schedule or via webhook.

## Concepts

- **Trigger** — When the workflow runs (scheduled cron or incoming webhook).
- **Conditions** — Optional filters (e.g. only weekdays, only if a field is set).
- **Actions** — What runs: HTTP request, Slack message, email, etc.

## Example: webhook on form submit

1. Create a workflow in **Automation**.
2. Choose **Webhook** trigger and copy the URL.
3. POST from your form handler when a submission arrives.
4. Add actions (e.g. notify Slack).

## Example: daily task

1. Create a workflow with a **Scheduled** trigger.
2. Set time and recurrence.
3. Add actions (e.g. call an external API).

## Related

- Plugins — automation may add extra actions
