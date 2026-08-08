# Public Forms

Public forms let visitors submit data (contact, newsletter, applications) that become entries in a Birkly collection. Prefer the **`{entry for}`** template directive with `birkly-client.js` over hand-rolled `fetch` calls.

## Summary

**In short:** (1) Create a collection with the fields you need. (2) Set **Entry method** to **Public API**. (3) On your site, wrap form markup in `{entry for 'contact'}…{endentry}` and load the client script. Field `name="{slug}"` values must match the collection schema. Deep templating guide: [Public forms templating](../03-templating/public-forms-templating.md).

## Beginner

**Public forms** mean: a visitor fills out a form on your website, and that data is saved as an **entry** you manage in admin (and optionally trigger automations).

### Step 1: Create a collection

| Field label | Type | Required? | Key (example) |
|-------------|------|-----------|----------------|
| Name | Text | Yes | name |
| Email | Email | Yes | email |
| Message | Textarea | Yes | message |

Give the collection a **slug** (e.g. `contact`) — used in `{entry for 'contact'}`.

### Step 2: Allow public submissions

In the collection **Settings**:

- **Entry method** → **Public API** (runtime value `public_api`).
- Enable **rate limiting** and optional **CAPTCHA**.
- Prefer **draft / not auto-published** for contact-type collections so you moderate first.

### Step 3: Template form (recommended)

```html
<div class="contact-form-wrapper">
{entry for 'contact'}
  <label>Name <input type="text" name="{name}" required></label>
  <label>Email <input type="email" name="{email}" required></label>
  <label>Message <textarea name="{message}" required></textarea></label>
  <div data-birkly-form-status hidden aria-live="polite"></div>
  <button type="submit">Send</button>
{endentry}
</div>

<script src="/birkly-client.js"
        data-cms-url="https://your-cms.example"></script>
```

The client wraps your markup in a `<form>`, posts to `/api/public_submit.php`, and keeps API keys server-side. Same-host project pages can omit `data-cms-url`. See [Client setup](../03-templating/client-setup.md).

### Auto-generated form

```html
{public_form 'contact'}
<script src="/birkly-client.js"></script>
```

### After submission

- New entries appear under that collection in admin.
- Add **Automation** (entry created → email/Slack) if needed — [Automation overview](../05-automation/automation-overview.md).

### Security checklist

- Never put admin credentials in the browser.
- Use **HTTPS**.
- Enable rate limiting / CAPTCHA when available.
- Rely on server-side validation in Birkly.

### Known-bad patterns

| Bad | Do instead |
|-----|------------|
| Custom `fetch` to guessed REST URLs | `{entry for}` |
| Field names that don’t match schema | `read_collection` / admin field keys |
| Phantom `{entry 'x'.'y'}…{endentry}` | `{entry for}` for forms; `{from …}` for reading |

## Advanced Users

### Sub-entry public submission (comments)

Use `/api/public_sub_entry.php` with parent refs — not `{entry for}` on the parent. Example and flags: [Public forms templating](../03-templating/public-forms-templating.md), [Sub-collections in templates](../03-templating/sub-collections-in-templates.md).

### Manual POST (escape hatch)

Only when you cannot use the client: `POST /api/public_submit.php?collection={slug}` with field keys matching the schema. Prefer `{entry for}` so keys and UX stay consistent.

### WebMCP

Declarative forms can become website tools when allowlisted — [Website tools](webmcp-tools.md). AI app connections live under **Settings → AI** — [MCP connections](../01-admin/mcp-connections.md).

## Related

- [Public forms templating](../03-templating/public-forms-templating.md)
- [Client setup](../03-templating/client-setup.md)
- [Collections](../02-content/collections.md)
- [Public API](public-api.md)
- [Website integration](website-integration.md)
- [Automation overview](../05-automation/automation-overview.md)
