# Automation playbook

**Requires:** Automation plugin installed and active.

Use for: "when X happens, do Y" — Slack, email, HTTP, entry triggers, schedules, webhooks.

## Model

**Workflow** = Trigger → optional Conditions → Actions

Configure in **Admin → Automation** (AI guides; no workflow CRUD tools).

## Trigger types

| Trigger | When it runs |
|---------|--------------|
| **Scheduled** | Cron-like (daily 9:00, every N minutes) |
| **Webhook** | HTTP POST to workflow URL |
| **Entry created/updated/deleted** | CMS entry or sub-entry changes |
| **Commerce** | order_paid, receipt, etc. (when Commerce active) |
| **User** | user_registered, user_login (User Management) |

## Sub-entry triggers

Payload includes `is_sub_entry`, `parent_collection`, `parent_entry_id`, `sub_collection`. Restrict trigger to sub-collection (e.g. only new `comments` on `blog`).

## Common recipes

### Contact form → Slack
1. Public form creates entry (`public-forms`)
2. Workflow: Entry created → collection `contact` → Slack action with `{{entry.name}}`

### Daily API ping
1. Scheduled trigger daily 9:00
2. HTTP GET/POST action

### New comment moderation
1. Entry created trigger, sub-collection filter `comments`
2. Slack: "New comment on {{entry.title}}"

### Order receipt email
1. Commerce trigger `commerce_order_receipt`
2. Email action to customer

## Dry-run

Always **Test (dry-run)** in workflow editor before enable — logs actions without side effects.

## Template variables in actions

`{{entry.title}}`, `{{payload.email}}`, `{{sub_collection}}`, etc.

## Webhook URL

Automation admin shows URL like `/api/automation_webhook.php?workflow={id}`. External systems POST JSON payload.

## AI role

- Explain trigger/condition/action design
- Link to `public-forms` for form → automation chain
- Link to `commerce` for order triggers
- Cannot create/edit workflows via API — user uses Automation UI

## Anti-patterns

- Enabling without dry-run
- Entry trigger on parent collection when only sub-entries matter (use sub-collection filter)
- Secrets in chat logs

## Related

- `public-forms` — form submissions as triggers
- `sub-collections` — sub-entry events
- `commerce` — order triggers
