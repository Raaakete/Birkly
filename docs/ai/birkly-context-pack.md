# Birkly AI Context Pack

Curated reference for BYOAI system prompts. Loaded by `loadBirklyContextPack()` — not a full docs dump.

## Platform

- **Name:** Birkly — flat-file PHP CMS
- **Content:** Collections with JSON entries under `content/collections/{slug}/`
- **Media:** Files in `media/` with `.metadata.json`
- **Users:** Admin users in `users/`; site users via User Management plugin
- **Plugins:** Deactivatable extensions in `plugins/`

## Roles & permissions

AI connections receive a **role** (from `settings/roles.json`). Permissions are enforced at action execution via `checkPermission()`.

| Capability | Typical permissions |
|------------|---------------------|
| Read content | `read` |
| Write content | `write`, `manage_content` |
| Delete content | `delete`, `manage_content` |
| Manage media | `manage_media`, `upload` |
| Manage settings | `manage_settings` |
| Manage users | `manage_users` |
| Full access | `*` (admin) |

Write actions may require human approval when `require_approval` is enabled on the connection.

## AI actions

**Read (immediate):** `list_collections`, `read_collection`, `list_entries`, `read_entry`, `list_media`, `list_users`, `read_user`, `read_settings`, `list_pages`, `list_roles`, `list_plugins`

**Write (may need approval):** `create_entry`, `update_entry`, `delete_entry`, `create_collection`, `update_collection`, `delete_collection`, media/settings/page/user/role/plugin mutations

**Format:**
```json
{"actions":[{"type":"list_entries","data":{"collection":"blog"},"description":"List blog entries"}]}
```

There is **no** `create_user` action — direct users to Settings → Users.

## Templating (client sites)

Birkly uses a natural template language for static HTML sites:

```html
<script src="/cms-template.v2.js"></script>
{for each entry in 'blog'}
  <h2>{ title }</h2>
  <div>{ text }</div>
{endfor}
```

- Collection slug in quotes inside `{for each entry in 'slug'}`
- Field names match schema: `{ title }`, `{ text }`, etc.
- Loops close with `{endfor}`

## Collections

Entries live at `content/collections/{slug}/entries/{id}.json`. Schema fields are in `schema.json`. Use exact field names when creating entries.

## MCP (external AI tools)

Each AI connection can have an MCP API key scoped to that connection's role. External clients call `/api/mcp_server.php` with `X-MCP-API-Key` header.

## Automation

When the Automation plugin is active, workflows trigger on entry events, schedules, and webhooks. Configure via Admin → Automation.

## Security

- Never expose passwords, encrypted API keys, or MCP keys in responses
- AI users are stored as `type: ai` in `users/`
- Approval queue: owner or shared users can approve/reject pending writes
