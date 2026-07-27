# Birkly AI playbooks — master index

Read this when the user's request spans multiple areas or you are unsure which playbook to use.

## How playbooks work

1. **L1 (always in system prompt):** Short catalog of playbook slugs and triggers.
2. **L2 (on demand):** `read_birkly_docs` with a topic slug loads the full step-by-step playbook.
3. **L3 (reference):** `templating`, `admin` (context pack), plugin topics when active.

**Rule:** Read the relevant playbook **once**, then execute. Do not loop on discovery reads.

## Playbook map

| Topic slug | Use when the user asks about… |
|------------|-------------------------------|
| `content-model` | Collections, entries, schema, fields, publishing, slugs |
| `site-building` | Website, portfolio, landing page, `project/` HTML/CSS |
| `templating` | Template syntax, loops, `{from…get}`, client rendering |
| `public-forms` | Contact forms, newsletter signup, external submission, `{entry for}` |
| `sub-collections` | Nested content: comments, reviews, line items, nested sub-collections |
| `cms-pages` | Admin-managed pages vs `project/` site |
| `media` | Media library, uploads, folders, referencing images in entries |
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

## Decision tree

```
Build a public website?
├─ Custom HTML/CSS → site-building (+ templating if dynamic)
├─ Simple admin pages only → cms-pages
└─ Forms on the site → public-forms (+ content-model for schema)

Structured content?
├─ Flat entries → content-model
├─ Child rows per parent → sub-collections (+ public-forms if visitors submit)
└─ Rich article body → content-editor

Sell / automate / members?
├─ Products & checkout → commerce (enable plugin first)
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

## What AI cannot configure (direct to admin UI)

- Public API keys for collections (Settings tab on collection editor)
- Agent Guide write/publish (admin AI Presence page)
- Automation workflow CRUD (Automation admin)
- Payment provider credentials (Commerce → Connections)
- Admin user creation (Settings → Users — no `create_user` tool)

## Anti-patterns (all playbooks)

- Re-listing collections/files every message after first successful read
- Using `create_page` when user wanted `project/` custom site
- Using `create_entry` fallback on "yes/proceed" during site builds
- Wrong templating syntax (`{% for %}`, `{{ }}`) — use Birkly tags (`{for each entry in 'slug'}`)
- Confusing `entry_method` values — runtime uses `public_api` for public submission (not `public_submit`)
