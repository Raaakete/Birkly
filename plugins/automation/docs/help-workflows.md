# Workflow builder

Open an automation from the list (or Create) to use the dedicated editor page: **Automation › Editor**.

## Canvas basics

- Drag blocks from the palette (search + tabs: All / Triggers / Conditions / Actions)
- Connect **output → input** ports
- Conditions expose **If true** and **Else**
- Select a connection and press Delete to remove it
- Nodes show live summaries (email To, webhook URL, collection, …)
- Incomplete required fields show a **Setup** badge on the node

Workflows persist a `graph` (nodes + edges). Runtime follows edges, including Else branches.

## Building a reliable flow

1. One trigger
2. Optional condition(s)
3. One or more actions on each branch
4. Save
5. Dry-run to validate
6. Live-run or real event after Setup works

## Testing

### Dry-run (Test)

- Validates required config
- Evaluates conditions against sample context
- Describes actions with “not sent / not applied”
- **Never** emails, webhooks, Slack, or content mutations
- Does not consume rate-limit counters

### Confirm & Run Live

- Executes real side effects
- Requires explicit confirmation + CSRF
- Still uses sample context unless you pass real context from a trigger

Prefer creating a real entry when you need real `{{entry.*}}` values.

## Queue behaviour

1. Trigger fires → job enqueued
2. Plugin tries to process a small batch immediately
3. Cron (optional) keeps draining backlog + schedules

If Logs stay empty: **Setup → Process queue now**.

## Templates (starter recipes)

The list page offers templates (e.g. notify on publish). They seed a sensible graph; you still must:

- Set real email addresses / webhook URLs
- Configure Setup email transport
- Enable and live-test

## Import / Export

Editor toolbar can export/import canvas JSON for backup or duplication across environments. Review secrets (webhook URLs) before sharing exports.

## Validation errors

The editor surfaces canvas validation (missing trigger, disconnected actions, required fields). Fix these before expecting runtime success.

## Related docs

- Getting started
- Setup & connections
- Email delivery
- Examples
- Troubleshooting
