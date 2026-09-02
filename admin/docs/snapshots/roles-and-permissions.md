# Roles and Permissions

## Summary

Roles in Birkly define what each user can do: for example **Viewer** (see content only), **Editor** (create and edit content and media), or **Admin** (full access including settings, users, and plugins). Permissions are the specific abilities (e.g. “edit entries,” “manage users”) attached to each role. When you invite or create a user, you assign one role; use the least powerful role that fits the person’s job. Only administrators should change roles or invite users.

## Beginner

**Roles** are like job types. Each user has exactly one role, and that role decides what they can see and do in the admin.

**Default CMS roles**

Birkly seeds four roles the first time **Settings → Users & Roles** is used (stored in `settings/roles.json`):

| Role | Typical use |
|------|-------------|
| **Admin** | Full CMS access including settings, users, and plugins |
| **Editor** | Manage collections, entries, media, and plugins |
| **Author** | Create and edit content and media (no settings or user management) |
| **Viewer** | Read-only access to collections |

- **Viewer** — Can log in and **see** content and media. They **cannot** create, edit, or delete anything.  
  **Example:** A client is a Viewer so they can check the latest blog posts without being able to change anything.

- **Author** — Can **create and edit** entries and upload media, but usually **cannot** manage collections, settings, users, or plugins.  
  **Example:** A freelance writer is an Author so they can draft posts but not change collection schemas.

- **Editor** — Can **create and edit** entries and collections, and **upload and manage** media. Usually they **cannot** change settings, add users, or change roles.  
  **Example:** Your colleague is an Editor so they can publish blog posts, but they can’t invite new users.

- **Admin (Administrator)** — Can do **everything**: all content and media, plus **Settings** (project name, branding, users, roles, plugins, webhooks, updates, audit log).  
  **Example:** You are the site owner with Admin; your intern only needs to edit the blog, so they get Editor.

**Permissions** are editable per role in **Settings → Users & Roles** — tick or clear resource actions (view, create, edit, delete) for each role. You can also add custom roles. **Role pickers** throughout the admin (user invite/edit, AI connections, MCP service agents) load the same role list from the roles API, so custom roles appear everywhere once saved.

**Assigning a role**

- When you **invite** a new user (e.g. from Settings → Users → Invite), you enter their email and choose a role (Viewer, Editor, or Admin). They receive an email to set their password and then can log in with that role.
- For **existing users**, an administrator can change their role in Settings → Users (or similar). The change usually applies the next time they use the CMS (or immediately, depending on how the system works).

**Good practice**

Give people the **least powerful role** they need. If someone only needs to review content, make them a Viewer. If they need to edit content but not touch settings or users, make them an Editor. Reserve Admin for a small number of trusted people.

**Who can change roles**

Only users who have permission to manage users (typically Administrators). Editors and Viewers cannot change roles or invite users.

## Advanced Users

**Model:** Users have one role; roles have a set of permissions. Stored in DB or config (e.g. `users` table + `roles` / `permissions` config). Auth middleware checks permission for each admin action (e.g. `edit_entries`, `manage_users`); Public API is usually unauthenticated read (and optionally submit with separate auth/rate limit).

**Default roles (seeded):**

| Role    | Typical permissions |
|---------|----------------------|
| Viewer  | `collections`: view |
| Author  | `collections`, `media`: view, create, edit |
| Editor  | `collections`, `media`, `plugins`: view, create, edit, delete |
| Admin   | `*`: view, create, edit, delete |

Seeding runs via `birkly_ensure_default_roles()` when `settings/roles.json` is missing or empty. **API:** `GET /api/roles.php?action=list_roles` — used by settings, AI connections, and MCP agent modals for dynamic role pickers.

**Custom roles:** Create in **Settings → Users & Roles**; permissions use resource/action pairs. Plugins can register extra permissions; these appear in the role matrix.

**Enforcement:** Admin UI hides or disables actions the user can’t do; API returns 403 if the user lacks the required permission. Role change applies on next request or after re-login depending on whether permissions are read from DB on each request or cached in session.

**Example permission check (pseudocode):** Before saving an entry, backend checks `user.has('edit_entries')` (and optionally collection-specific rules). Public `GET /api/public/collections/...` does not use admin roles; it’s read-only and often unauthenticated.

## Related

- [Settings](settings.md)
- [Account](account.md)
- [Audit log and activity](../06-extensibility/audit-log-and-activity.md)
