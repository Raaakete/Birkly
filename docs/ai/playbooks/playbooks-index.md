# Birkly AI playbooks — master index

Read this when the user's request spans multiple areas or you are unsure which playbook to use.

## How playbooks work

Playbooks are **inspiration and practical tips** for working well with Birkly — patterns, examples, and platform behavior. They are **not** a second permission layer.

**What actually governs access:**

- **Role permissions** on the AI/MCP connection (which tools run at all)
- **MCP / built-in chat settings** (e.g. require approval before writes)

If a tool call is allowed by the role and passes approval settings, playbooks do not override that. Use playbooks to work smarter; use `tools/list`, `get_system_context`, and the user's request to decide what to do.

1. **L1 (always in system prompt):** Short catalog of playbook slugs, quality tips, tools list, templating cheat sheet, context pack summary.
2. **L2 (auto-loaded):** Full playbook markdown injected server-side when intent matches (site builds, forms, commerce, MCP setup, plugins, etc.).
3. **L3 (on demand):** `read_birkly_docs` with a topic slug, or MCP `birkly://docs/{topic}` / `resources/read` for depth.

**Tip:** Read the relevant playbook **once**, then execute. Avoid looping on discovery reads in the same turn.

**Start here for quality:** `ai-quality-guide` — principles, failure modes, checklists.

## Playbook map

| Topic slug | Use when the user asks about… |
|------------|-------------------------------|
| `ai-quality-guide` | How to produce reliable outputs; checklists; failure modes |
| `playbooks-index` | This file — routing & decision tree |
| `content-model` | Collections, entries, schema, fields, publishing, slugs |
| `fieldtypes` | Field `type` values, schema JSON examples, image/editor/email |
| `site-building` | Website, portfolio, landing page, `project/` HTML/CSS |
| `web-project-patterns` | List/detail pages, schema decisions, CMS-to-HTML wiring, common bugs |
| `templating` | Template syntax, loops, `ordered by`, dates `format`, `{from…get}`, regions, phantoms |
| `public-forms` | Contact forms, `{entry for}` (valid form scaffold), public_api, newsletter, visitor submissions |
| `sub-collections` | Nested content: comments, reviews, line items |
| `collection-groups` | Admin sidebar groups, reorder collections |
| `cms-pages` | Admin-managed pages vs `project/` site |
| `media` | Media library, uploads, folders, image/gallery fields |
| `content-editor` | Rich text / layout HTML in Editor fields |
| `agent-guide` | Agent Guide: sources, products, claims, publishing `/.well-known/` |
| `mcp-setup` | Connect Claude, ChatGPT, Cursor via MCP |
| `mcp-capabilities` | MCP tool families, plugin tools, allow_public_read, fieldtype picks |
| `ai-connections` | Built-in admin chat assistant, API keys, approvals |
| `plugins-overview` | Install, enable, marketplace, official plugins |
| `commerce` | Shop, cart, checkout, variants, subscriptions (plugin) |
| `commerce-store-kit` | End-to-end store kit: products collection, 3 ship products, tax/ship settings, checkout page (P3-M2) |
| `automation` | Workflows, triggers, webhooks, Slack/email (plugin) |
| `events` | RSVP, tickets, calendar (plugin; needs Commerce for paid) |
| `user-management` | Site registration, login, tiers, member content (plugin) |
| `settings-roles` | Roles, permissions, global settings, security |

Plugin topics (`commerce`, `automation`, etc.) also resolve from active plugin `docs/ai-context.md` when installed.

## Documentation cross-links (human admin docs)

AI playbooks distill these; load L3 when you need admin UI specifics.

**Bidirectional linking:** Admin Help topics in `docs/help-manifest.json` include an **`ai_topic`** slug pointing at the matching playbook. When viewing Help in admin, the footer shows the AI playbook id — use `read_birkly_docs` or MCP `birkly://docs/{ai_topic}`.

| Help topic id | Playbook (ai_topic) | Admin snapshot |
|---------------|---------------------|----------------|
| `entries` | `content-model` | `admin/docs/snapshots/entries.md` |
| `collection-groups` | `collection-groups` | Collection groups docs |
| `settings` | `settings-roles` | `admin/docs/snapshots/settings.md` |
| `mcp-connections` | `mcp-setup` | `admin/docs/snapshots/mcp-connections.md` |
| `user-plugin` | `user-management` | `admin/docs/snapshots/user-plugin.md` |
| `plugins` / `marketplace` | `plugins-overview` | `admin/docs/snapshots/plugins.md` |
| `public-forms` / `public-forms-templating` | `public-forms` | Public forms docs / snapshots |
| `templating-overview` / `canonical-syntax` / `loops-and-lists` | `templating` | Templating snapshots |
| `detail-pages-and-url-context` | `web-project-patterns` | Detail pages snapshot |
| `media` | `media` | `admin/docs/snapshots/media.md` |
| `content-editor` | `content-editor` | `admin/docs/snapshots/content-editor.md` |
| `ai` / `ai-connections` / `mcp-connections` | `ai-connections` / `mcp-setup` | **Settings → AI** (not Agent Guide) |
| `automation` | `automation` | `admin/docs/snapshots/automation-overview.md` |
| `help` | `playbooks-index` | This index |

| Topic | Playbook | Human doc (Birkly-Docs SSOT) |
|-------|----------|------------------------------|
| Content model | `content-model` | https://docs.birkly.com/02-content/content-model-overview.html |
| Field types | `fieldtypes` | https://docs.birkly.com/02-content/field-types.html |
| Public API forms | `public-forms` | https://docs.birkly.com/04-integration/public-forms.html · https://docs.birkly.com/03-templating/public-forms-templating.html |
| Website + client JS | `web-project-patterns`, `templating` | https://docs.birkly.com/03-templating/templating-overview.html · https://docs.birkly.com/03-templating/client-setup.html |
| MCP connections | `mcp-setup` | https://docs.birkly.com/01-admin/mcp-connections.html (**Settings → AI**) |
| Media | `media` | https://docs.birkly.com/01-admin/media-library.html |
| Content editor | `content-editor` | https://docs.birkly.com/02-content/content-editor.html |
| Marketplace | `plugins-overview` | https://docs.birkly.com/06-extensibility/marketplace-go-live.html |
| Commerce | `commerce`, `commerce-store-kit` | https://docs.birkly.com/06-extensibility/plugins/commerce.html |
| User plugin | `user-management` | https://docs.birkly.com/06-extensibility/user-plugin.html |
| Template language | `templating` | Playbook + https://docs.birkly.com/03-templating/canonical-syntax.html ; full engine: `docs/TEMPLATE_LANGUAGE_SPEC.md` (`template-language-spec`) |
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
├─ Seed a starter shop (collection + 3 products + checkout) → commerce-store-kit (+ commerce)
├─ Products & checkout (concepts / variants / subscriptions) → plugins-overview → commerce
├─ When X then Y → automation
├─ Events & RSVP → events (+ commerce if paid)
└─ Login & registration → user-management

AI connectivity?
├─ Chat inside admin → ai-connections (Settings → AI)
├─ Claude/Cursor/ChatGPT → mcp-setup (Settings → AI)
└─ Brand facts for external AI → agent-guide (Guide · Tools · Publish only)
```

## Official plugins (marketplace)

| Plugin | Depends on | Playbook |
|--------|------------|----------|
| Commerce | — | `commerce` (reference); `commerce-store-kit` (starter build) |
| Automation | — | `automation` |
| Events | Commerce (paid tickets) | `events` |
| User Management | — | `user-management` |
| Studio | — | `site-building` (visual builder; `/site/{slug}/` SSR) |

Install flow: `plugins-overview` → `list_plugins` → admin Marketplace or `activate_plugin`.

## Tool coverage vs admin UI

Many workflows exist in **both** the admin UI and MCP tools (when the connection role includes the right permissions). Examples:

| Workflow | MCP tools (when permitted) | Admin UI |
|----------|---------------------------|----------|
| Agent Guide draft / publish | `cms_admin_action` → `agent_guide.*` | Agent Guide (AI Presence) |
| Automation workflows | `automation_admin_action`, named `automation_*` tools | Automation admin |
| Human users | `cms_create_user`, `users.*` via `cms_admin_action` | Settings → Users |
| Payment provider secrets | — (secrets stay in admin) | Commerce → Connections |

Payment credentials and other secret boxes are typically admin-only by design — not a playbook restriction.

## Common pitfalls (tips — not policy)

These help avoid broken sites and wasted turns. They do **not** block tool use when the role allows it.

- Re-listing collections/files every message after first successful read
- Using `create_page` when user wanted `project/` custom site
- Wrong field types or names — read `fieldtypes` + `read_collection`
- Wrong templating syntax (`{% for %}`, `{{ }}`) — use Birkly tags (`templating`)
- Detail page loops entire collection — use URL slug pattern + `of 'coll'.current` for subs
- Template tags without `birkly-client.js`
- Phantom `{entry 'x'.'y'}…{endentry}` — use `{from…get}`; forms use `{entry for}` (valid)
- Form field names ≠ schema slugs — causes "Validation failed"
- Telling non-technical users to manually fix what AI tools can do
- Re-adding Connections to Agent Guide — Connections live under **Settings → AI** only (IA note)
