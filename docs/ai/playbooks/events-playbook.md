# Events playbook

**Requires:** Events plugin. **Paid tickets:** Commerce plugin + payment connection.

Use for: event listings, RSVP, calendars, iCal feeds, ticket sales.

## Setup order

1. `plugins-overview` — install Events (+ Commerce if paid)
2. Add **Event** fieldtype to collection (or use Events collection profile)
3. Create entries with event fields (date, location, capacity, etc.)
4. Templates with event functions
5. Commerce integration for paid registration (`fulfillment: register`)

## Template functions

| Function | Purpose |
|----------|---------|
| `{upcoming_events}` | List future events |
| `{event_register_url}` | RSVP / register link |

(Exact names in plugin templating docs — read active `events` ai-context when installed.)

## Free vs paid

| Type | Requirements |
|------|--------------|
| Free RSVP | Events plugin only |
| Paid tickets | Commerce + Events; purchase_option on event/variant; register fulfillment |

## Common workflow

1. Collection `events` with title, date, description, event field
2. Seed published entries
3. `write_project_file` — listing page with `{upcoming_events}` or loop over collection
4. Optional Automation on registration trigger

## AI tools

- Content: `create_collection`, `create_entry`, `write_project_file`
- No direct RSVP/admin event config tools

## Related

- `commerce` — paid tickets, register fulfillment
- `content-model` — event entries
- `site-building` — event listing pages

## Reference

`read_birkly_docs` `events` when plugin active (loads plugin ai-context).
