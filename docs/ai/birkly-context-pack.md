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

**Read (immediate):** `list_collections`, `read_collection`, `list_entries`, `read_entry`, `list_sub_entries`, `read_sub_entry`, `list_media`, `list_users`, `read_user`, `read_settings`, `list_pages`, `list_roles`, `list_plugins`

**Write (may need approval):** `create_entry`, `update_entry`, `delete_entry`, `create_sub_entry`, `update_sub_entry`, `delete_sub_entry`, `create_collection`, `update_collection`, `delete_collection`, media/settings/page/user/role/plugin mutations

**Sub-entry tool parameters:**
- `list_sub_entries`: `collection` (parent slug), `entry_id` (parent entry ID), `sub_collection` (sub-collection slug), optional `limit`
- `read_sub_entry`: `collection`, `entry_id`, `sub_collection`, `sub_entry_id`
- `create_sub_entry`: `collection`, `entry_id`, `sub_collection`, `data` (object with field values)
- `update_sub_entry`: `collection`, `entry_id`, `sub_collection`, `sub_entry_id`, `data`
- `delete_sub_entry`: `collection`, `entry_id`, `sub_collection`, `sub_entry_id`

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

Admin UI templates use `{admin_url}`, `{translate}`, `{for each}`, `{if}`, `{json var}`, `{include_script}` — different from client-site language. Do not mix syntaxes.

| Directive | Example |
|-----------|---------|
| i18n | `{translate 'admin.content'}` |
| Admin URL | `{admin_url 'entries' collection='collection.slug'}` |
| JSON bootstrap | `{json content_bootstrap}` |
| Loop | `{for each item in items} ... {endfor}` |
| Conditional | `{if user.is_admin} ... {endif}` |
| Assets | `{include_script 'content-groups.js'}` |

Admin pages load data via `getPageData()` in `api/admin_templates.php`. Templates compile through `admin/template_parser.php` and `TemplateSafeRenderer` (no `eval()`).

## Fieldtypes

Built-in fieldtypes live in `fieldtypes/` (Text, Textarea, Number, Media, Select, etc.). Plugins register fieldtypes via `register_fieldtype()`. Schema fields in `content/collections/{slug}/schema.json` reference fieldtype by `type`.

### ContentEditor (rich text / layout studio)

The **Editor** fieldtype (`content-editor`, aliases `editor`, `media`) stores **portable HTML**. All editor code lives in `fieldtypes/ContentEditor/`.

**Admin workflow (tables & grids):**

1. Open an entry with an Editor field → **Visual** tab.
2. Place caret → toolbar **Table** or **Grid** → insert at caret (not document end).
3. Click the layout block → floating **Layout panel** (gap, padding, background, border, radius; table/grid-specific controls).
4. Drag column/track resize handles; adjust grid span, flow, and fluid track presets.
5. Save entry — `ContentEditorSync` + `processValue` strip `data-ce-*` and editor chrome; inline layout styles remain.

**Table HTML pattern:**

```html
<table class="content-editor-table" style="width:100%;border-collapse:collapse">
  <colgroup><col style="width:33%"></colgroup>
  <tbody><tr><td style="padding:12px">…</td></tr></tbody>
</table>
```

**Grid HTML pattern** (responsive via fluid tracks — no `@media` in entry HTML):

```html
<div class="content-editor-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
  <div style="grid-column:span 2">…</div>
</div>
```

When creating/updating entries via AI, preserve these class names and inline styles; do not inject `<script>` or editor-only `data-ce-*` attributes.

## Plugins

- Registry: `core/plugins.php` — `load_plugins()`, `birkly_is_plugin_active()`, `register_admin_page()`, `register_api_endpoint()`
- State persisted in `settings/plugins.json`
- Automation plugin: workflows on entry events, schedules, webhooks (`plugins/automation/`)
- User Management: site users, registration, collection permissions (`plugins/user_management/`)

## API surface

| Area | Endpoint |
|------|----------|
| Content | `/api/content.php` |
| Media | `/api/media.php` |
| Search | `/api/search.php` |
| Public | `/api/public.php` |
| Automation | `/api/automation.php` (when plugin active) |
| AI chat | `/api/ai_chat.php` |
| MCP | `/api/mcp_server.php`, `/mcp` (Streamable HTTP) |
| Feedback | `/api/feedback.php` (submit); triage platform-owner only |

All URLs must use `birkly_url()` / `Birkly.url()` on subdirectory installs (e.g. `/Birkly-0.1/`).

## Collections

Entries live at `content/collections/{slug}/entries/{id}.json`. Schema fields are in `schema.json`. Use exact field names when creating entries.

### Slug rules

- Collection and entry slugs use **lowercase letters, digits, and hyphens only** (`[a-z0-9-]`)
- Use hyphens, not underscores (`blog-posts`, not `blog_posts`)
- Slugs are normalized at write time: underscores become hyphens; invalid characters are stripped

## Sub-collections & sub-entries

A **sub-collection** is a named set of child entries owned by a parent entry (e.g. `comments` on `blog`). Sub-collections are configured on the parent collection; sub-entries are stored under the parent entry's directory.

### Data model — `collection.json` `sub_collections` field

```json
{
  "slug": "blog",
  "name": "Blog",
  "sub_collections": [
    {
      "slug": "comments",
      "name": "Comments",
      "entry_method": "public_submit",
      "fields": [
        {"name": "author", "type": "text", "label": "Author"},
        {"name": "content", "type": "textarea", "label": "Content"}
      ]
    }
  ]
}
```

`entry_method` values: `admin_only` (default) | `public_submit` | `user_submit`

### Disk layout

```
content/collections/
  {parent-slug}/
    collection.json          ← has sub_collections[]
    schema.json
    entries/
      {entry-id}.json        ← parent entry
      {entry-id}/
        sub_entries/
          {sub-slug}/
            {sub-entry-id}.json   ← sub-entry file
            .order.json           ← custom drag-drop order (optional)
```

Example for a `comments` sub-collection on `blog` entry `hello-world`:
```
content/collections/blog/entries/hello-world/sub_entries/comments/comment-abc123.json
```

### Admin URL params

| Action | URL |
|--------|-----|
| List parent entries | `/admin/?page=entries&collection={parent-slug}` |
| View entry + sub-entries | `/admin/?page=entry-detail&collection={parent-slug}&entry={entry-id}` |
| Add new sub-entry | `/admin/?page=entry-editor&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}` |
| Edit sub-entry | `/admin/?page=entry-editor&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}&sub_entry={sub-entry-id}` |
| View sub-entry detail | `/admin/?page=entry-detail&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}&sub_entry={sub-entry-id}` |
| Edit sub-collection schema | `/admin/?page=collection-editor&collection={parent-slug}&sub_collection={sub-slug}` |
| Add nested sub-collection | `/admin/?page=collection-editor&collection={parent-slug}&parent_sub_collection={existing-sub-slug}` |

### Admin workflow matrix

**Goal: Add a `comments` sub-collection to `blog`, then create a comment on "Hello World"**

| Step | Action | URL / navigation |
|------|--------|-----------------|
| 1 | Open collection editor for `blog` | `/admin/?page=collection-editor&collection=blog` |
| 2 | Click "Add sub-collection" → set slug `comments`, name "Comments", fields: author (text), content (textarea) | same page, form |
| 3 | Save → sub-collection stored in `blog/collection.json` | POST saves config |
| 4 | Go to Blog entry list | `/admin/?page=entries&collection=blog` |
| 5 | Click into "Hello World" entry | `/admin/?page=entry-detail&collection=blog&entry={hello-world-id}` |
| 6 | Scroll to Comments panel → click "Add comment" | `/admin/?page=entry-editor&collection=blog&entry={hello-world-id}&sub_collection=comments` |
| 7 | Fill Author and Content → Save | POST to `/api/sub_entries.php` |
| 8 | View comment list back on entry-detail | `/admin/?page=entry-detail&collection=blog&entry={hello-world-id}` |
| 9 | Edit a comment | `/admin/?page=entry-editor&collection=blog&entry={hello-world-id}&sub_collection=comments&sub_entry={comment-id}` |
| 10 | Delete a comment | delete button on entry-detail page |

**Key rule:** Sub-entries are always scoped to `collection` + `entry` + `sub_collection`. You need all three to create, read, update, or delete a sub-entry.

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
