# Plugins & marketplace playbook

Use for: installing official plugins, enabling/disabling, dependencies, plugin docs for AI.

## Architecture

- Plugins live in `plugins/` (empty on fresh install)
- **Official plugins:** Commerce, Automation, Events, User Management, Studio
- Source builds: Birkly Marketplace / Ops platform
- Each plugin: `plugin.json`, capabilities, optional `docs/ai-context.md`

## Install (typical user path)

1. Admin → **Marketplace** → browse → Install
2. Plugin appears in **Settings → Plugins**
3. Enable / Activate

## AI tools

| Tool | Purpose |
|------|---------|
| `list_plugins` | Installed plugins and status |
| `activate_plugin` | Enable plugin |
| `deactivate_plugin` | Disable (removes hooks/routes) |
| `read_birkly_docs` topic `{plugin_slug}` | Plugin ai-context when active |

## Dependency order

| Install first | Then |
|---------------|------|
| Commerce | Events (paid tickets), some automation triggers |
| User Management | Member-only sub-collections (`user_submit`) |
| — | Automation (standalone) |
| — | Studio (visual site builder) |

## Official plugin summary

| Plugin | Playbook topic | Primary capability |
|--------|----------------|-------------------|
| Commerce | `commerce` | Cart, checkout, orders, variants, subscriptions |
| Automation | `automation` | Triggers, conditions, actions, webhooks |
| Events | `events` | RSVP, iCal, paid tickets via Commerce |
| User Management | `user-management` | Registration, login, tiers, `{user_*}` templates |
| Studio | `site-building` | Visual builder, SSR `/site/{slug}/` |

## Plugin docs shelf

When active, plugin registers:
- Help docs in admin
- MCP `birkly://docs/plugin/{slug}`
- `read_birkly_docs` topic = plugin slug

## AI workflow: "Add e-commerce"

1. `read_birkly_docs` `plugins-overview` + `commerce`
2. `list_plugins` — check if Commerce installed/active
3. If not: instruct user → Marketplace → Install Commerce
4. If yes: follow `commerce` playbook (fieldtypes, catalog, templates)

## Plugin development (advanced)

Scaffold: `php scripts/scaffold-plugin.php --id=my-plugin --title="My Plugin"`

Declare capabilities, `register_plugin_docs()` for AI shelf. See admin snapshot scaffold-plugin.

## Anti-patterns

- Assuming plugins ship in OSS repo clone (Marketplace install required)
- Enabling Events before Commerce when paid tickets needed
- AI trying to configure payment secrets (admin Commerce → Connections)

## Related playbooks

Per-plugin: `commerce`, `automation`, `events`, `user-management`
