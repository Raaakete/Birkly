# Events playbook

**Requires:** Events plugin. **Paid tickets:** Commerce plugin + payment connection.

Use for: event listings, RSVP, calendars, iCal feeds, ticket sales, registration workflows.

**Admin doc (human UI):** `admin/docs/snapshots/plugins.md` (Events row), plugin templating docs when installed.  
**Load depth:** `read_birkly_docs` topic `events` (this file); when plugin active, also loads plugin `docs/ai-context.md`.

---

## When to use Events vs a plain collection

| Need | Use |
|------|-----|
| Simple dated posts (news, blog) | Top-level collection + `date` field — no Events plugin |
| RSVP, capacity, registration links, iCal | **Events plugin** + Event fieldtype |
| Paid tickets | Events + **Commerce** + `fulfillment: register` |

Do not install Events for a basic “events blog” unless you need RSVP/register/calendar features.

---

## Setup order (always follow)

1. **`plugins-overview`** — confirm Events (and Commerce if paid) is installed and active (`list_plugins`)
2. **Install Commerce first** if selling tickets — Events paid flow depends on it
3. **Collection schema** — add Event fieldtype (or use Events collection profile from plugin docs)
4. **Seed entries** — published events with dates, location, capacity as needed
5. **Templates** — listing + detail in `project/` (see below)
6. **Commerce** (paid only) — purchase_option on event/variant, register fulfillment
7. **Automation** (optional) — `commerce_event_registered` or registration triggers

---

## Content model example

Collection slug: `events` (adapt to project)

| Field | type | Purpose |
|-------|------|---------|
| `title` | text | Event name |
| `description` | editor or textarea | Details |
| `event` | event (plugin fieldtype) | Date, time, location, capacity, registration settings |
| `image` | image | Hero / card image |
| `featured` | boolean | Highlight on homepage |

```json
{
  "slug": "events",
  "fields": [
    { "name": "title", "label": "Title", "type": "text", "required": true },
    { "name": "description", "label": "Description", "type": "editor", "required": false },
    { "name": "event", "label": "Event details", "type": "event", "required": true },
    { "name": "image", "label": "Image", "type": "image", "required": false }
  ]
}
```

Always **`read_collection`** after schema changes — Event fieldtype sub-structure is plugin-defined.

---

## Template functions (Events plugin)

Exact names and options are in the active plugin ai-context when installed. Common patterns:

| Function / pattern | Purpose |
|--------------------|---------|
| `{upcoming_events}` | List future events (plugin helper) |
| `{event_register_url}` | RSVP / register link for current event context |
| `{for each entry in 'events' …}` | Manual loop when you want full layout control |

### Listing page (loop pattern)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Events</title>
  <link rel="stylesheet" href="/project/css/style.css">
</head>
<body>
  <h1>Upcoming events</h1>
  {for each entry in 'events' with status = published ordered by newest}
    <article>
      <h2><a href="/project/event.html?collection=events&slug={slug}">{title}</a></h2>
      <div>{'description'}</div>
    </article>
  {endfor}
  <script src="/birkly-client.js"></script>
</body>
</html>
```

### Detail page

Use URL context — **no loop over the whole collection** on detail HTML:

```html
<meta name="birkly-collection" content="events">
<h1>{ title }</h1>
<div>{ 'description' }</div>
<!-- Plugin may expose registration URL in event field output -->
<script src="/birkly-client.js"></script>
```

See **`web-project-patterns`** for list vs detail rules.

---

## Free RSVP vs paid tickets

| Type | Requirements | AI workflow |
|------|--------------|-------------|
| **Free RSVP** | Events plugin only | Configure event field registration; template `{event_register_url}` or plugin docs |
| **Paid tickets** | Commerce + Events | `read_birkly_docs` `commerce` → purchase_option, variants, register fulfillment |

AI cannot configure payment provider secrets — direct user to **Commerce → Connections** in admin.

---

## Automation hooks

When Automation plugin is active:

- Registration / purchase may fire **`commerce_event_registered`** (paid path via Commerce)
- Plugin may expose **`user_registered`**-style hooks for free RSVP — check plugin ai-context

Workflow example: “Email admin when someone registers” → Automation admin UI (AI cannot CRUD workflows — guide user or use `read_birkly_docs` `automation`).

---

## AI tools available

| Task | Tool |
|------|------|
| Install check | `list_plugins` |
| Schema | `create_collection`, `update_collection`, `read_collection` |
| Content | `create_entry`, `list_entries` |
| Public site | `write_project_file`, `get_project_info` |
| Plugin config (RSVP rules, payment) | Mostly **admin UI** — no dedicated event-config tool |

---

## Common failure modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| Events menu missing | Plugin not enabled | Marketplace → Install → Settings → Enable |
| Paid register fails | Commerce not configured | Install Commerce; payment connection in admin |
| Blank event dates | Event fieldtype not on schema | `update_collection` with `type: event` field |
| Detail shows all events | Loop on detail page | Use `?collection=events&slug={slug}` pattern |
| Register link 404 | Wrong template function or plugin version | `read_birkly_docs` `events` + plugin ai-context |

---

## Worked example: “Add events to my site”

1. `list_plugins` — Events active?
2. If not: tell user **Marketplace → birkly-events → Install → Enable**
3. `create_collection` `events` with fields above (or extend existing)
4. `create_entry` × 2 with `status: published`, future dates in event field
5. `write_project_file` — `events.html` (list), `event.html` (detail)
6. Link from `index.html`; use `site_url_prefix` from `get_project_info`
7. If paid: `commerce` playbook before promising checkout

---

## Related playbooks

- **`plugins-overview`** — install order, Marketplace
- **`commerce`** — paid tickets, register fulfillment
- **`content-model`** — entries, publishing, slugs
- **`web-project-patterns`** — list/detail wiring
- **`site-building`** — full site build order
- **`automation`** — post-registration workflows

## Admin cross-reference

| Topic | Location |
|-------|----------|
| Install Events | Admin → Marketplace / Settings → Plugins |
| Event fieldtype | Collection schema editor |
| Paid tickets | Commerce plugin + Events integration |
| Human docs | `admin/docs/snapshots/plugins.md`, plugin templating docs |
