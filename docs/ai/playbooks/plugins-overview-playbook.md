# Plugins & marketplace playbook

Use for: installing official plugins, enabling/disabling, dependencies, plugin AI docs, capability boundaries.

**Admin doc (human UI):** `admin/docs/snapshots/plugins.md`, `admin/docs/snapshots/marketplace.md`  
**Help topic ids:** `plugins`, `marketplace` → AI playbook `plugins-overview`

---

## Architecture (know this first)

- Plugins live in `plugins/` — **empty on fresh OSS clone**
- **Official plugins:** Commerce, Automation, Events, User Management, Studio
- Installed via **Admin → Marketplace** (or marketplace.birkly.io), not bundled in core repo
- Each plugin: `plugin.json`, capabilities, optional `docs/ai-context.md`
- When active: registers admin help, MCP `birkly://docs/plugin/{slug}`, `read_birkly_docs` topic = plugin slug

AI must **`list_plugins`** before assuming Commerce/Events/etc. exist.

---

## Install flow (typical user path)

1. Admin → **Marketplace** → browse → **Install**
2. **Settings → Plugins** → **Enable / Activate**
3. Plugin sidebar items appear (Commerce, Automation, …)
4. Configure secrets in plugin admin (payment keys — **not** via AI tools)

### AI-assisted install workflow

```
read_birkly_docs "plugins-overview"
  → list_plugins
  → if missing: instruct user Marketplace → Install → Enable
  → if present: read_birkly_docs "{plugin-slug}" + matching playbook
  → activate_plugin (if permitted and downloaded)
```

---

## AI tools

| Tool | Purpose |
|------|---------|
| `list_plugins` | Installed plugins, active state, versions |
| `activate_plugin` | Enable plugin (requires `manage_plugins`) |
| `deactivate_plugin` | Disable hooks/routes |
| `read_birkly_docs` topic `{slug}` | Plugin `docs/ai-context.md` when active |

AI **cannot**: download from Marketplace, enter payment API secrets, or bypass denylist.

---

## Dependency order

| Install first | Then | Why |
|---------------|------|-----|
| **Commerce** | Events (paid tickets) | Ticket purchase + register fulfillment |
| **Commerce** | Some automation commerce triggers | Order-paid actions |
| **User Management** | Member `user_submit` sub-collections | Site auth |
| — | **Automation** | Standalone workflows |
| — | **Events** (free RSVP) | No Commerce required |
| — | **Studio** | Visual builder; optional vs `project/` |

### Official plugin map

| Plugin | Playbook topic | Primary capability | Marketplace slug |
|--------|----------------|------------------|------------------|
| Commerce | `commerce` | Cart, checkout, orders, variants | birkly-commerce |
| Automation | `automation` | Triggers, conditions, actions | birkly-automation |
| Events | `events` | RSVP, calendar, paid w/ Commerce | birkly-events |
| User Management | `user-management` | Registration, `{user_*}` templates | birkly-user-management |
| Studio | `site-building` | Visual builder, `/site/{slug}/` SSR | birkly-studio |

---

## Plugin docs shelf (when active)

Each plugin may register:

- Admin help snapshots
- **`read_birkly_docs`** topic matching plugin id
- MCP resource `birkly://docs/plugin/{slug}`

Always read plugin ai-context **after** confirming plugin is active — fieldtypes and APIs differ by version.

---

## Worked examples

### “Add e-commerce to my site”

1. `read_birkly_docs` `plugins-overview` + `commerce-store-kit` (starter build) / `commerce` (depth)
2. `list_plugins` — Commerce installed and active?
3. If no: **Marketplace → birkly-commerce → Install → Enable**
4. If yes: follow **`commerce-store-kit`** for collection + 3 products + checkout; use `commerce` for variants/subscriptions
5. Payment connection: user completes **Commerce → Connections** in admin

### “Enable automation for Slack on new orders”

1. `list_plugins` — Automation + Commerce?
2. `read_birkly_docs` `automation`
3. Workflow CRUD is **Automation admin UI** — AI guides user; no workflow write tool

### “Add member login”

1. `list_plugins` — User Management?
2. Marketplace install if missing
3. `read_birkly_docs` `user-management`

---

## Plugin development (advanced)

Scaffold: `php scripts/scaffold-plugin.php --id=my-plugin --title="My Plugin"`

- Declare capabilities in `plugin.json`
- `register_plugin_docs()` for AI shelf
- `register_plugin_mcp_tools()` for inbound MCP tool injection (definitions + handlers in plugin, e.g. `docs/mcp_tools.php`)
- See `admin/docs/snapshots/scaffold-plugin.md`

Out of scope for casual site builds — don't scaffold unless user asks.

---

## Anti-patterns

| Mistake | Fix |
|---------|-----|
| Assuming plugins ship in git clone | Marketplace install |
| Events before Commerce for **paid** tickets | Install Commerce first |
| AI configures Stripe/PayPal secrets | User → Commerce → Connections |
| `activate_plugin` without files on disk | Install from Marketplace first |
| Ignoring plugin denylist | Security tab may block plugin paths |

---

## Common failure modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| Plugin not in list | Not installed to `plugins/` | Marketplace install |
| Activate fails | Missing dependency or denylist | Check Settings → Plugins error |
| AI docs topic empty | Plugin inactive | `activate_plugin` |
| Sidebar item missing | Deactivated | Enable in Settings → Plugins |

---

## Related playbooks

Per-plugin depth:

- **`commerce`** — shop, checkout, fulfillment
- **`automation`** — workflows, webhooks
- **`events`** — RSVP, tickets
- **`user-management`** — site auth, member content
- **`site-building`** — Studio vs project/
- **`settings-roles`** — `manage_plugins` permission

## Admin cross-reference

| Topic | Location |
|-------|----------|
| Plugin list | Settings → Plugins |
| Marketplace | Plugins → Marketplace |
| Denylist | Settings → Security |
| Human docs | `admin/docs/snapshots/plugins.md`, `marketplace.md` |
