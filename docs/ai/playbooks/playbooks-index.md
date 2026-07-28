# Birkly AI playbooks — master index

Read this when the user's request spans multiple areas or you are unsure which playbook to use.

## How playbooks work

1. **L1 (always in system prompt):** Short catalog of playbook slugs, quality rules, tools list, templating cheat sheet, context pack summary.
2. **L2 (auto-loaded):** Full playbook markdown injected server-side when intent matches (site builds, forms, commerce, MCP setup, plugins, etc.).
3. **L3 (on demand):** `read_birkly_docs` with a topic slug, or MCP `birkly://docs/{topic}` / `resources/read` for depth.

**Rule:** Read the relevant playbook **once**, then execute. Do not loop on discovery reads in the same turn.

**Start here for quality:** `ai-quality-guide` — golden rules, failure modes, checklists.

## Playbook map

| Topic slug | Use when the user asks about… |
|------------|-------------------------------|
| `ai-quality-guide` | How to produce reliable outputs; checklists; failure modes |
| `playbooks-index` | This file — routing & decision tree |
| `content-model` | Collections, entries, schema, fields, publishing, slugs |
| `fieldtypes` | Field `type` values, schema JSON examples, image/editor/email |
| `site-building` | Website, portfolio, landing page, `project/` HTML/CSS |
| `web-project-patterns` | List/detail pages, schema decisions, CMS-to-HTML wiring, common bugs |
| `templating` | Template syntax, loops, `{from…get}`, client rendering |
| `public-forms` | Contact forms, `{entry for}`, public_api, newsletter, visitor submissions |
| `sub-collections` | Nested content: comments, reviews, line items |
| `collection-groups` | Admin sidebar groups, reorder collections |
| `cms-pages` | Admin-managed pages vs `project/` site |
| `media` | Media library, uploads, folders, image/gallery fields |
| `content-editor` | Rich text / layout HTML in Editor fields |
| `agent-guide` | Agent Guide: sources, products, claims, publishing `/.well-known/` |
| `mcp-setup` | Connect Claude, ChatGPT, Cursor via MCP |
| `ai-connections` | Built-in admin chat assistant, API keys, approvals |
| `plugins-overview` | Install, enable, marketplace, official plugins |
| `commerce` | Shop, cart, checkout, variants, subscriptions (plugin) |
| `automation` | Workflows, triggers, webhooks, Slack/email (plugin) |
| `events` | RSVP, tickets, calendar (plugin; needs Commerce for paid) |
| `user-management` | Site registration, login, tiers, member content (plugin) |
| `settings-roles` | Roles, permissions, global settings, security |

Plugin topics (`commerce`, `automation`, etc.) also resolve from active plugin `docs/ai-context.md` when installed.

## Documentation cross-links (human admin docs)

AI playbooks distill these; load L3 when you need admin UI specifics:

| Topic | Playbook | Admin / platform doc |
|-------|----------|----------------------|
| Field types | `fieldtypes` | `admin/docs/fieldtypes.php` |
| Public API forms | `public-forms` | `admin/docs/public-api-forms.php` |
| Website + client JS | `web-project-patterns`, `templating` | `admin/docs/website-integration.php`, `admin/docs/client-library.php` |
| MCP connections | `mcp-setup` | `admin/docs/snapshots/mcp-connections.md` |
| Media | `media` | `admin/docs/snapshots/media.md` |
| Content editor | `content-editor` | `admin/docs/snapshots/content-editor.md` |
| Marketplace | `plugins-overview` | `admin/docs/snapshots/marketplace.md` |
| Commerce | `commerce` | `admin/docs/snapshots/commerce-*.md` |
| User plugin | `user-management` | `admin/docs/snapshots/user-plugin.md` |
| Template language (full) | `templating` | `docs/TEMPLATE_LANGUAGE_SPEC.md` |
| Platform overview | `admin` (context pack) | `docs/ai/birkly-context-pack.md` |

## Decision tree

```
Any task → ai-quality-guide (rules) if unsure

Build a public website?
├─ Custom HTML/CSS → web-project-patterns + site-building + fieldtypes
├─ Simple admin pages only → cms-pages
└─ Forms on the site → public-forms (+ content-model + fieldtypes)

Structured content?
├─ Flat entries → content-model + fieldtypes
├─ Child rows per parent → sub-collections (+ public-forms if visitors submit)
├─ Admin sidebar organization → collection-groups
└─ Rich article body → content-editor

Sell / automate / members?
├─ Products & checkout → plugins-overview → commerce
├─ When X then Y → automation
├─ Events & RSVP → events (+ commerce if paid)
└─ Login & registration → user-management

AI connectivity?
├─ Chat inside admin → ai-connections
├─ Claude/Cursor/ChatGPT → mcp-setup
└─ Brand facts for external AI → agent-guide
```

## Official plugins (marketplace)

| Plugin | Depends on | Playbook |
|--------|------------|----------|
| Commerce | — | `commerce` |
| Automation | — | `automation` |
| Events | Commerce (paid tickets) | `events` |
| User Management | — | `user-management` |
| Studio | — | `site-building` (visual builder; `/site/{slug}/` SSR) |

Install flow: `plugins-overview` → `list_plugins` → admin Marketplace or `activate_plugin`.

## What AI can configure via tools

- Collection `entry_method: public_api`, sub-collection submission rights
- Collection groups (`list_collection_groups` / `save_collection_groups`)
- Full collection schema, entries, project files, media (with permissions)

## What still requires admin UI

- Agent Guide write/publish (AI Presence page)
- Automation workflow CRUD (Automation admin)
- Payment provider credentials (Commerce → Connections)
- Admin user creation (Settings → Users — no `create_user` tool)

## Anti-patterns (all playbooks)

- Re-listing collections/files every message after first successful read
- Using `create_page` when user wanted `project/` custom site
- Wrong field types or names — read `fieldtypes` + `read_collection`
- Wrong templating syntax (`{% for %}`, `{{ }}`) — use Birkly tags
- Detail page loops entire collection — use URL slug pattern
- Template tags without `birkly-client.js`
- Form field names ≠ schema slugs — causes "Validation failed"
- Telling non-technical users to manually fix what AI tools can do
