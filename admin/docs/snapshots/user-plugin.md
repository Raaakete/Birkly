# User plugin

The **User Management** plugin adds front-end accounts, registration, groups, tiers, and collection-level permissions. Enable it when your site needs logged-in visitors or members who create their own content.

## Summary

Install and enable the plugin under **Settings → Plugins**. Admin pages **Users**, **Groups**, and **Tiers** appear in the sidebar. Configure which collections new users may read or write, use the User fieldtype to reference accounts in entries, and expose `{user_logged_in}` / `{user_current}` in templates. Registration and password reset use the plugin APIs with CSRF and rate limiting.

## Beginner

**Enable the plugin**

1. **Settings → Plugins** → find **User Management** → Enable.
2. Open **Users** in the sidebar to see registered accounts.
3. Under **Groups** and **Tiers**, organize members if you need shared permissions or limits.

**Common tasks**

| Task | Where |
|------|-------|
| See who registered | **Users** |
| Block a user | **Users** → edit → suspend |
| Let members post in a collection | Collection settings → permissions (plugin section) |
| Show login state on website | Templates: `{if user_logged_in}…{endif}` |
| Password reset | Public `reset-password` route (plugin) |

**Registration flow**

1. Visitor submits registration form (your template or plugin default).
2. Plugin creates user record and sends verification email (if configured).
3. User verifies email, logs in, and receives role/tier per your settings.
4. Optional: automation **user_registered** trigger fires if Automation plugin is on.

**Security defaults**

- Writes check permissions — users cannot edit collections they are not allowed to.
- Admin user management requires `manage_users`.
- Use HTTPS in production for session cookies.

## Advanced Users

**Key paths**

- Plugin root: `plugins/user_management/`
- APIs: `api/user_auth.php`, `api/user_registration.php`, `api/user_admin.php`, `api/collection_permissions.php`
- Permissions: `includes/EntryPermissions.php`, `includes/UserPermissions.php`

**Template functions**

| Function | Returns |
|----------|---------|
| `{user_logged_in}` | Boolean |
| `{user_current}` | Current user object (id, name, email, …) |
| `{user_list}` | Filtered user list (admin/config dependent) |
| `{user_profile "id"}` | Public profile entry if collection enabled |

**Automation hooks**

- `user_registered` — after successful signup
- `user_login` — after successful login

**Ops guide (maintainers)**

See platform repo `docs/user-plugin-ops.md` for checklist, backup scope, and troubleshooting.

## Related

- [Plugins](plugins.md)
- [Roles and permissions](roles-and-permissions.md)
- [Public forms](../04-integration/public-forms.md)
- [Closed alpha guide](../00-overview/closed-alpha-guide.md)
