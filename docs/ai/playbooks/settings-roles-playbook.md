# Settings & roles playbook

Use for: CMS permissions, custom roles, global settings, security, storage, AI connection roles.

**Admin doc (human UI):** `admin/docs/snapshots/settings.md`  
**Help topic id:** `settings` → AI playbook `settings-roles`

---

## Settings vs content

| Layer | Examples | AI tools |
|-------|----------|----------|
| **Global settings** | Project name, branding, security, storage | `read_settings`, `update_settings` |
| **Collections & entries** | Blog posts, products | `create_collection`, `create_entry`, … |
| **Admin users** | Back-office accounts | `cms_create_user` / `cms_admin_action` `users.*` when role allows; else Settings → Users |

Changes in Settings apply to the **whole CMS**, not one collection.

---

## Default CMS roles

Stored in `settings/roles.json`. Seeded roles:

| Role | Typical permissions |
|------|---------------------|
| **Admin** | Full access (`*` or all manage_* ) |
| **Editor** | read, write, manage_content, manage_media |
| **Author** | read, write (own content) |
| **Viewer** | read only |

Custom roles: subset of permissions for AI connections or admin users.

### Permission reference

| Permission | Allows |
|------------|--------|
| `read` | List/read content, collections, media metadata |
| `write` | Edit entries |
| `manage_content` | Collections, entries, schema, collection groups |
| `delete` | Delete content |
| `manage_media` | Upload, folders, media library |
| `manage_settings` | Global settings tabs |
| `manage_users` | Admin user accounts |
| `manage_plugins` | Activate/deactivate plugins |
| `manage_project_site` | `project/` file tools (`write_project_file`, …) |
| `*` | Full administrator |

AI connections receive a **role** → tool allowlist via `core/ai_permissions.php`.

---

## AI tools for settings & roles

| Tool | Purpose | Notes |
|------|---------|-------|
| `read_settings` | Read global config (general, security, storage, …) | Prefer before changing URLs/paths |
| `update_settings` | Change settings | May require user approval |
| `list_roles`, `read_role` | Inspect role definitions | Use before `create_role` |
| `create_role`, `update_role` | Custom roles | Omit dangerous permissions intentionally |

### Creating admin users

- **MCP:** `cms_create_user` or `cms_admin_action` → `users.create` when the connection role includes `manage_users` (or equivalent).
- **Admin UI:** Settings → Users → Add User.

Use whichever path the connection allows; do not claim a user was created without a successful tool result.

---

## Settings tabs (admin UI map)

| Tab | What it controls | AI relevance |
|-----|------------------|--------------|
| **General** | Project name, URL, timezone, media defaults | `read_settings` for site URL |
| **Branding** | Logo, favicon | Usually admin-only uploads |
| **Users and roles** | Admin accounts, role assignment | `cms_create_user` when role allows |
| **Plugins** | Installed/active plugins | `list_plugins`, `activate_plugin` |
| **Security** | Session timeout, lockout, CSRF, password rules | Enforced at runtime |
| **Storage** | flatfile vs MySQL/PostgreSQL | Major migration = admin-operated |
| **AI & Integrations** | Outbound AI + inbound MCP | `ai-connections`, `mcp-setup` playbooks |
| **Monitoring** | Health alert email/webhook | Ops |
| **Site delivery** | Redirects, delivery mode | `get_project_info` for public URLs |

**Global Save** — one **Save settings** bar persists all tab changes together.

---

## Security settings (enforced)

| Setting | Effect |
|---------|--------|
| Session timeout | Idle admin logout |
| Max login attempts / lockout | Brute-force protection |
| Password min length / strong password | User password policy |
| CSRF protection | Required on mutating API calls |
| Plugin denylist | Block plugin access to paths/collections/spaces |

AI should not disable CSRF in production.

---

## Worked examples

### “Create editor role that can't delete”

1. `list_roles` — inspect permission shape on Editor/Author
2. `create_role` with: `read`, `write`, `manage_content`, `manage_media` — **omit** `delete`
3. Assign in Settings → Users or on AI connection

### “What is our site URL prefix?”

1. `read_settings` or `get_project_info`
2. Use `site_url_prefix` in all `write_project_file` hrefs

### “Move storage to MySQL”

1. Explain this is admin-operated: Settings → Storage → test connection → migrate
2. AI reads via `read_settings`; does not run migration without explicit ops approval

---

## AI connection roles

When configuring built-in assistant or MCP:

- Role limits which tools run (e.g. Viewer cannot `write_project_file`)
- **`ai-connections`** playbook — API keys, approvals tab
- **`mcp-setup`** — OAuth user inherits their CMS role

Workflow: `read_role` for connection's role before promising write access.

---

## Public form rate limits

Collection-level `api_settings.rate_limit` on `public_api` collections — related to security, configured via `update_collection`. See **`public-forms`**.

---

## Common failure modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| AI can't write project files | Role lacks `manage_project_site` | Upgrade role or use Admin connection |
| Settings change not applied | Forgot global Save | User clicks Save settings in admin |
| Wrong site URLs in HTML | Didn't read prefix | `get_project_info` → `site_url_prefix` |
| User asks AI to add admin user | No tool | Settings → Users |

---

## Related playbooks

- **`ai-connections`** — built-in chat, roles on connections
- **`mcp-setup`** — inbound MCP, OAuth inherits role
- **`plugins-overview`** — plugin activation permissions
- **`public-forms`** — rate limits on public collections

## Admin cross-reference

| Topic | Location |
|-------|----------|
| All settings tabs | Admin → Settings |
| Admin users | Settings → Users |
| Roles JSON | `settings/roles.json` |
| Human docs | `admin/docs/snapshots/settings.md` |
