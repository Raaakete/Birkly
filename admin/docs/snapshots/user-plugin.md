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
- APIs: `api/user_auth.php`, `api/user_registration.php`, `api/user_member_signup.php`, `api/user_members.php`, `api/user_account.php`, `api/user_admin.php`, `api/user_graph.php`, `api/user_graph_admin.php`, `api/collection_permissions.php`
- Fieldtypes: `user_account*` (member marker), `user` (UGC reference), `password`, `user_relationship` (virtual graph panel on profile entries)
- Permissions: `includes/EntryPermissions.php`, `includes/UserPermissions.php`

**Template functions**

| Function | Returns |
|----------|---------|
| `{user_logged_in}` | Boolean |
| `{user_current}` | Current user object (id, name, email, …) |
| `{user_list}` | Filtered user list (admin/config dependent) |
| `{user_profile "id"}` | Public profile entry if collection enabled |
| `{user_is_following from to}` | Boolean |
| `{user_is_friend a b}` | Boolean |
| `{user_is_connected a b}` | Mutual connection |
| `{user_graph_follows user_id}` | User ID list |
| `{user_graph_followers user_id}` | User ID list |
| `{user_graph_connected user_id}` | User ID list |
| `{user_can_view entry}` | Boolean visibility |

See [UGC ownership and social graph](user-ugc-ownership.md) for owner fields, `user_graph` API, `user_relationship` fieldtype, and likes sub-entries.

**Automation hooks**

- `user_registered` — after successful signup
- `user_login` — after successful login

**Member-as-entry (P77)**

New sites model members as **collection entries** with a `user_account*` fieldtype — not separate User List rows. **Fieldtype profiles** (User → Fieldtypes) define signup requirements, social gates, and Commerce payment flags per tier. Collection-scoped signup uses `user_member_signup`; the **Members** tab is index-backed. See [User fieldtype profiles](user-fieldtype-profiles.md) and [Member migration](user-member-migration.md).

**Signup flows and membership (P74 + P77)**

Paid memberships use **signup flows** and **membership products** in User admin — not Commerce purchase mappings. Presets include `free_register`, `pay_then_activate`, `register_then_pay`, and `activate_only`. Public API: `user_signup` (`start`, `step`, `status`) for flow presets; **P77** adds `user_member_signup` for collection-scoped member registration. See [User signup and membership](user-signup-membership.md).

**Ops guide (maintainers)**

See platform repo `docs/user-plugin-ops.md` for checklist, backup scope, and troubleshooting. Plugin source of truth: [Birkly-Other-Things/Birkly-Plugin-User](https://github.com/Birkly-Other-Things/Birkly-Plugin-User).

**Follow-up — public list/search permission filtering**

The User plugin registers `birkly_public_entries_filter` and `birkly_public_entry_filter` hooks (Hub 1.0.38+). Core `entry_ownership.php` stamps owner from session on public create. Ensure production CMS runs current Birkly core with `core/public_read.php` hooks active.

## Related

- [Plugins](plugins.md)
- [User fieldtype profiles](user-fieldtype-profiles.md)
- [Member migration](user-member-migration.md)
- [User-generated content and ownership](user-ugc-ownership.md)
- [User signup and membership](user-signup-membership.md)
- [Roles and permissions](roles-and-permissions.md)
- [Public forms](../04-integration/public-forms.md)
- [Closed alpha guide](../00-overview/closed-alpha-guide.md)
