# User plugin — operations guide

Ops reference for `plugins/user_management/` (Phase 3 P3-M8). Developer API details: `plugins/user_management/README.md`.

## What it provides

- Front-end registration, login, password reset, email verification
- User fieldtype and Password fieldtype for collections
- Groups, tiers, collection-level permissions, entry visibility
- Template functions: `{user_logged_in}`, `{user_current}`, `{user_list}`, `{user_profile}`
- Automation hooks: `user_registered`, `user_login` (when automation plugin active)

## Enable

1. **Settings → Plugins** → enable **User Management**.
2. Confirm admin routes: `?page=users`, `?page=groups`, `?page=tiers` (plugin-registered, not core Settings).
3. Configure **Settings → Security** for session timeout and password policy.

## First-time setup checklist

| Step | Action |
|------|--------|
| 1 | Enable plugin |
| 2 | Create **users** collection (or use plugin default registration collection) |
| 3 | Assign roles: `member`, `editor`, etc. — map to `plugins/user_management` permissions |
| 4 | Set collection permissions for public vs member-only content |
| 5 | Configure SMTP or mail transport for verification emails (`settings/email.json`) |
| 6 | Test register → verify email → login → create entry with User fieldtype |

## Admin pages

| Page | Path | Purpose |
|------|------|---------|
| Users | `?page=users` | List, edit, suspend users |
| Groups | `?page=groups` | Group membership and shared permissions |
| Tiers | `?page=tiers` | Subscription/tier limits (optional) |

If `?page=users` redirects to Settings, the plugin is inactive or admin registration failed — check `plugins/user_management/plugin.php` and plugin status.

## Permissions model

Writes enforce `checkPermission()` on:

- User profile updates (`api/user_settings.php`)
- Entry create/update when collection has visibility rules (`includes/EntryPermissions.php`)
- Admin user CRUD (`api/user_admin.php`)

**Roles vs tiers:** Roles = CMS capabilities. Tiers = optional limits (max entries per collection). See `ROLES_VS_TIERS_CLARIFICATION.md` in plugin folder.

## Template integration

```html
{if user_logged_in}
  <p>Hello, {user_current.name}</p>
{else}
  <a href="/login.html">Log in</a>
{endif}
```

Public registration forms POST to `api/user_registration.php` (CSRF + rate limit).

## Automation bridge

When automation plugin is active:

- `user_registered` — payload includes `user_id`, `email`
- `user_login` — payload includes `user_id`, `ip`

Register workflows in **Automation** with these trigger types.

## Backup & migration

Include in backups:

- `users/` — account JSON (excluding AI connection type rows)
- `content/collections/users/` — public profile entries if used
- Plugin settings in `settings/plugins/user_management.json` if present

Do not commit `users/` or runtime registration data to git.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| Registration 403 | Collection `entry_method`, rate limit, CSRF token |
| Email not sent | `settings/email.json`, PHP `mail()` or SMTP logs |
| User can edit forbidden collection | Collection permissions + entry visibility rules |
| Admin users page 404 | Plugin enabled; `register_admin_page` in `plugin.php` |

## Tests

```bash
php tests/user_plugin_permissions_test.php
php tests/run.php
```

## Related

- `plugins/user_management/README.md` — full API
- Birkly-Docs: `06-extensibility/user-plugin.md`
- `docs/USER_PLUGIN_DESIGN.md` — historical design doc
