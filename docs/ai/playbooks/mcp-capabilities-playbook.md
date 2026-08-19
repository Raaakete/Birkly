# MCP capabilities playbook

Use when connected via MCP and you need to know what Birkly can do, which tool to call, or how plugin extensions expose extra tools.

## Three AI surfaces

| Surface | Who | Entry | Typical use |
|---------|-----|-------|-------------|
| **Inbound MCP** | External AI (Cursor, Claude, ChatGPT) | `POST /mcp` OAuth | Build sites, manage CMS, commerce ops |
| **Admin chat** | Human in admin UI | Settings → AI | Same tool registry; Birkly approval queue for writes |
| **WebMCP** | Visitor browser agent | `birkly-webmcp.js` on live site | Cart, forms, RSVP — not CMS admin |

This playbook covers **inbound MCP** + admin chat tools.

## Core tool families

| Family | Examples | Playbook |
|--------|----------|----------|
| Collections & entries | `list_collections`, `create_collection`, `update_collection`, `create_entry` | `content-model`, `fieldtypes` |
| Public site | `write_project_file`, `get_project_info`, `list_project_files` | `site-building`, `web-project-patterns` |
| Public read/write | `allow_public_read`, `entry_method: public_api` | `public-forms`, `content-model` |
| Media | `list_media`, `upload_media` | `media`, `fieldtypes` |
| Docs | `read_birkly_docs`, `birkly://docs/{topic}` | `playbooks-index` |
| Connections | `connections_health`, `connections_configure` | `ai-connections` |
| Agent Guide | `get_agent_guide_context`, `birkly://brand-context` | `agent-guide` |

**Rule:** Call `tools/list` (or reconnect MCP) after upgrades — clients cache schemas.

## Plugin MCP tools

When a plugin is **installed and active**, it injects its own tools into `tools/list`. Tools live in the plugin repo and disappear when the plugin is deactivated.

| Plugin | Tool prefix | Docs |
|--------|-------------|------|
| Commerce | `commerce_*` | `read_birkly_docs` topic `commerce`, `birkly://docs/plugin/commerce` |
| Automation | `automation_*` | `read_birkly_docs` topic `automation`, `birkly://docs/plugin/automation` |

1. `list_plugins` — confirm plugin is active before calling plugin tools.
2. Plugin tools require plugin permissions (e.g. `manage_commerce` for Commerce writes).
3. Prefer plugin MCP tools over guessing REST payloads or editing plugin data files directly.

## Collection config cheatsheet

| Goal | Tool | Key fields |
|------|------|------------|
| Visitor contact form | `update_collection` | `entry_method: "public_api"`, form `fields[]` |
| Public blog/gallery loops | `update_collection` | `allow_public_read: true` |
| Both form + public list | `update_collection` | `entry_method: "public_api"` **and** `allow_public_read: true` (uncommon) |

`allow_public_read` is a **top-level collection boolean** on `create_collection` / `update_collection` — not inside `api_settings`. Verify with `read_collection`.

## Fieldtype selection (critical)

| Need | Use | Avoid |
|------|-----|-------|
| Featured image, hero, product photo | `image` or `media` | `text` / `url` with hardcoded paths |
| Photo gallery, portfolio grid | `gallery` | Multiple `url` fields |
| PDF / download | `media` | `url` string |
| External link (not in media library) | `url` | `image` |
| Rich article body | `editor` | `textarea` for HTML |
| Plain multi-line | `textarea` | `editor` |

Workflow for CMS images: `list_media` or `upload_media` → set field on `create_entry` / `update_entry` → template outputs the field (`{'featured_image'}`).

## Composing fieldtypes (advanced patterns)

- **Repeater-like content** — use `sub_collections` (comments, variants, specs) instead of JSON blobs.
- **Structured metadata** — `select` / `multiselect` / `tags` before reaching for `json`.
- **Commerce sellable** — parent entry for marketing (`editor`, `gallery`) + `commerce` fieldtype (or variants sub-collection with per-variant commerce field). See `commerce` playbook.
- **Conditional admin UI** — fieldtype-specific options live on the field definition; read `read_collection` after schema changes.

## Recommended MCP session start

1. `get_project_info` + `list_collections` (once per session)
2. `read_birkly_docs` topic `playbooks-index` if task is unfamiliar
3. `list_plugins` when user mentions shop, workflows, events, or members
4. Execute — avoid re-asking for permission on a plan the user already approved

## Anti-patterns

- Using `url`/`text` for images that belong in the media library
- Building `{for each entry}` loops without `allow_public_read: true`
- Calling Commerce cart/checkout HTTP APIs from MCP when `commerce_*` tools exist
- Assuming plugin tools exist without `list_plugins`

## Related

- `mcp-setup` — connector OAuth setup
- `fieldtypes` — full type reference
- `public-forms` — forms + public_api
- `plugins-overview` — install/activate plugins
