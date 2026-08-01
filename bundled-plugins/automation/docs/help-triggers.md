# Triggers, conditions & actions

## Triggers

Triggers decide *when* a workflow starts. Filters on the trigger must match or the automation is skipped (no actions, often no useful log).

| Trigger | Fires when | Useful filters |
|---------|------------|----------------|
| `entry_created` | Entry created | collection |
| `entry_updated` | Entry saved/updated | collection |
| `entry_deleted` | Entry deleted | collection |
| `status_changed` | Status changes | collection, from, to |
| `sub_entry_created` | Sub-entry created | parent collection, sub-collection |
| `sub_entry_updated` | Sub-entry updated | parent collection, sub-collection |
| `sub_entry_deleted` | Sub-entry deleted | parent collection, sub-collection |
| `scheduled` | Schedule is due | cron / time / day |
| `webhook_inbound` | External POST hits inbound webhook | webhook ID, payload filter |
| `plugin_event` | Another plugin broadcasts | event name |

### Status changed tips

- Set **from** and **to** when you only care about a specific transition (e.g. draft → published)
- Leaving from/to empty is broader and may fire more often

### Scheduled tips

- Requires the scheduled processor (cron CLI or scheduled HTTP URL from Setup)
- Content triggers do not need schedule cron if inline queue processing is enough, but schedules do

### Webhook inbound tips

- Use a stable webhook ID in the trigger config
- Payload filters can match nested keys (see nested filter tests / builder fields)

## Conditions

Optional gates after the trigger:

| Condition | Purpose |
|-----------|---------|
| `field_match` | Entry field equals/contains a value |
| `status_check` | Current status matches |
| `collection_filter` | Collection slug matches |
| `regex_match` | Pattern match on a value |
| `rate_limit_guard` | Extra throttle |
| `json_path` | Match inside JSON/payload |

On the canvas, conditions expose **If true** and **Else** ports so each branch can run different actions.

Dry-run / test mode does not consume rate-limit counters.

## Actions

| Action | Side effect | Needs Setup? |
|--------|-------------|--------------|
| `email` | Sends mail via Setup transport | **Yes — email transport** |
| `slack` | Posts to Incoming Webhook | curl + URL on action |
| `teams` | Posts to Teams webhook | curl + URL on action |
| `webhook` | HTTP request | curl + URL on action |
| `set_status` | Changes entry status | content write access |
| `set_field` | Writes an entry field | content write access |
| `create_entry` | Creates an entry | content write access |
| `log` | Writes automation log line | none |
| `call_plugin_action` | Invokes another plugin | depends on plugin |

### Email action fields

- **To**, **Subject**, **Body** — required
- Templates: `{{entry.title}}`, `{{collection}}`, …

### Webhook-like safety

Localhost, private IPs, credentialed URLs, and non-80/443 ports are rejected before sending.

## Extending with plugins

Other plugins can register blocks via the `automation_blocks_register` hook. Core keywords win on conflicts.
