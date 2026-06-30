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

Birkly client sites use a **plain-English template language** in static HTML, loaded via:

```html
<script src="/cms-template.v2.js"></script>
```

Full spec: `docs/TEMPLATE_LANGUAGE_SPEC.md` (platform); public docs on Birkly-Docs when synced.

### Core rules

- **Collections** are always in **single quotes**: `'blog'`, `'services'`
- **System fields** are bare: `{title}`, `{created_at}`, `{status}`
- **User-defined fields** use quoted names: `{'content'}`, `{'price'}`
- Loops close with `{endfor}`

### Collection loop

```html
{for each entry in 'blog'}
  <h2>{title}</h2>
  <div>{'content'}</div>
{endfor}
```

### Single entry (no loop)

```html
{entry 'blog'.'my-post-slug'}
  <h1>{title}</h1>
  <article>{'content'}</article>
{endentry}
```

### Conditionals

```html
{if entry 'blog'.'featured-post' exists}
  <section class="featured">...</section>
{endif}

{if {'price'} is not empty}
  <span class="price">{{'price'}}</span>
{endif}
```

### Nested / sub-collection loops

```html
{for each entry in 'blog'}
  {title}
  {for each entry in 'comments'}
    {'author'}: {'content'}
  {endfor}
{endfor}
```

Sub-collection scoped to one parent entry:

```html
{for each entry in 'comments' of 'blog'.'hello-world'}
```

### Filters

```html
{created_at format "Y-m-d H:i"}
{title truncate 80}
{'content' strip_tags}
```

Common format codes: `Y` year, `m` month, `d` day, `H` hour, `i` minute, `F` full month name.

### Admin templating (PHP templates)

Admin UI templates use `{admin_url}`, `{translate}`, `{for each}`, `{if}` — different from client-site language. Do not mix syntaxes.

## Collections

Entries live at `content/collections/{slug}/entries/{id}.json`. Schema fields are in `schema.json`. Use exact field names when creating entries.

### Slug rules

- Collection and entry slugs use **lowercase letters, digits, and hyphens only** (`[a-z0-9-]`)
- Use hyphens, not underscores (`blog-posts`, not `blog_posts`)
- Slugs are normalized at write time: underscores become hyphens; invalid characters are stripped

## MCP (external AI tools)

Each AI connection can have an MCP API key scoped to that connection's role. External clients call `/api/mcp_server.php` with `X-MCP-API-Key` header.

## Automation

When the Automation plugin is active, workflows trigger on entry events, schedules, and webhooks. Configure via Admin → Automation.

## Security

- Never expose passwords, encrypted API keys, or MCP keys in responses
- AI users are stored as `type: ai` in `users/`
- Approval queue: owner or shared users can approve/reject pending writes

## Context pack maintenance

This file is the **canonical AI briefing** for in-product chat and MCP `birkly://documentation`. When platform behavior changes, update this pack in the same PR as the code change. Run `tests/ai_context_pack_test.php`. Optional deeper reference: `docs/TEMPLATE_LANGUAGE_SPEC.md`, `docs/ai/BYOAI-ARCHITECTURE.md`.
