# Roles and permissions

## Summary

**Roles** group CMS permissions for admin users. Each user has one role (for example Admin, Editor, Author, Viewer). Roles define which resources and actions are allowed — collections, media, settings, plugins, and more. **P69** adds per-plugin access on each role so you can hide Commerce, Automation, or other plugin admin surfaces for specific roles.

## Beginner

**Where to manage roles**

Open **Settings → Roles** (or the dedicated Roles page). You can create custom roles, edit permission checkboxes, and assign roles when inviting or editing users.

**Plugin access (P69)**

In the role editor, the **Plugins** section lists active plugins with allow/deny toggles:

- **Default (opt-out):** New plugins are visible until you deny them for a role.
- **Deny** hides the plugin from the sidebar and blocks direct navigation to that plugin admin page.
- **Allow-only mode** is available when configuring a role with an explicit allow list.

Denied plugins also omit MCP tools for users with that role.

## Advanced Users

**Storage:** `settings/roles.json` — array of role objects with `id`, `name`, `permissions`, and optional `plugin_access`:

```json
{
  "id": "editor",
  "permissions": { "collections": ["view", "edit", "create"] },
  "plugin_access": {
    "mode": "all_except",
    "deny": ["commerce"]
  }
}
```

**Enforcement:** `birkly_role_plugin_allowed()` filters plugin sidebar navigation, gates `admin/index.php` plugin pages, and filters AI/MCP tool registration per role.

## Related

- [Settings](settings.md)
- [Account](account.md)
- [Plugin security denylist](plugin-denylist.md)
- [Plugins](../06-extensibility/plugins.md)
