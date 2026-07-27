# Settings & roles playbook

Use for: permissions, custom roles, global settings, security, storage.

## Roles

Default roles: Admin, Editor, Author, Viewer. Defined in `settings/roles.json`.

AI connections receive a **role** → tool permissions via `core/ai_permissions.php`.

| Permission | Typical use |
|------------|-------------|
| `read` | List/read content |
| `write`, `manage_content` | Create/edit entries, collections |
| `delete` | Delete content |
| `manage_media` | Media library |
| `manage_settings` | Global settings |
| `manage_users` | Admin users (not site users) |
| `manage_plugins` | Activate plugins |
| `manage_project_site` | `project/` file tools |
| `*` | Full admin |

## AI tools

| Tool | Purpose |
|------|---------|
| `read_settings` | Read global config |
| `update_settings` | Change settings (approval) |
| `list_roles`, `read_role` | Role definitions |
| `create_role`, `update_role` | Custom roles |

## No create_user

Admin user creation: **Settings → Users → Add User**. AI must direct users there.

## Settings tabs (admin)

General, Branding, Security, Storage, Integrations, Monitoring, AI, Site delivery (redirects), etc.

Global **Save** bar — one save for many tabs.

## Security notes

- CSRF on admin/API mutations
- Session timeout, lockout in Security settings
- Plugin denylist for unsafe plugins
- Public form rate limits on collections

## Workflow: "Create editor role that can't delete"

1. `list_roles` — inspect permissions shape
2. Guide user or `create_role` with subset: read, write, manage_content — omit delete
3. Assign role to AI connection or admin users in Settings → Users

## Storage migration

Settings → Storage — path for `content/`, `media/`, `data/`. AI reads via `read_settings`; major migration is admin-operated.

## Related

- `ai-connections` — role on AI connection
- `mcp-setup` — OAuth user inherits their CMS role
