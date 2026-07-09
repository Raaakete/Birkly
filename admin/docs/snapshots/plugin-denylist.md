# Plugin security denylist

Block plugins from accessing specific collections, entries, admin **spaces**, or path patterns regardless of granted capabilities.

## Summary

The **plugin security denylist** is a global hard deny list in **Settings → Security**. Resources on the denylist cannot be read or modified by any plugin hook, UI injection, or service call — even if the plugin declared and was granted the relevant capability. **Deny spaces** block plugin UI and hooks in specific admin spaces (for example `commerce`, `automation`). Storage: `settings/plugin_denylist.json`.

## Beginner

**Why use a denylist**

Some collections hold sensitive data (HR, payroll, legal). You may grant a plugin broad `read_entries` access for most content but want specific collections permanently off-limits. The denylist enforces that boundary.

**Add a protected resource**

1. Open **Settings → Security**.
2. Find **Plugin security denylist**.
3. Click **Add resource**.
4. Enter an identifier:
   - Collection slug: `payroll`
   - Entry id: `entry:blog:confidential-post`
   - Wildcard pattern: `payroll/*`
5. Save — the resource appears in the list immediately.

**Remove a resource**

Click **Remove** next to the entry. Plugins regain access subject to their normal capability grants.

**Deny admin spaces (P14)**

Some plugins inject sidebar panels or hooks tied to an admin **space** id (for example the Commerce admin area). To block a plugin from a space without denying entire collections:

1. In **Settings → Security → Plugin denylist**, open **Denied spaces**.
2. Add a space id (for example `commerce`, `content`, `settings`).
3. Save — plugin sidebar injections and space-scoped hooks for that space are skipped for denylisted plugins.

Use spaces when a plugin should run elsewhere but must not appear in one admin section.

**What gets blocked**

When a collection slug, entry id, or **space** matches the denylist:

- Plugin hooks that read or write entries are denied.
- Capability checks return false before permission lookup.
- UI injections targeting denied scopes or spaces are skipped.

This does not affect **admin users** or **core CMS** — only plugin code paths that use the capability system.

## Advanced Users

**Storage format**

`settings/plugin_denylist.json`:

```json
{
  "resources": ["payroll", "legal/*", "entry:settings:secrets"],
  "deny_collections": ["payroll"],
  "deny_spaces": ["commerce"],
  "updated_at": "2026-07-07T12:00:00+00:00"
}
```

**Enforcement**

`has_plugin_capability($plugin_id, $capability, $scope)` calls `is_resource_protected($scope)` first (denylist-first model). `is_space_denied($space_id)` checks `deny_spaces` before space-scoped plugin hooks and admin injections. Wildcard patterns use `*` glob semantics converted to regex.

**API** (`/api/plugins_permissions.php`, requires `manage_plugins`):

| Action | Body |
|--------|------|
| `get_denylist` | — |
| `add_to_denylist` | `{ resource }` or `{ resource, as_space: true }` |
| `remove_from_denylist` | `{ resource }` |
| `set_denylist` | `{ resources: [], deny_spaces: [] }` |

**Resource identifier conventions**

| Pattern | Matches |
|---------|---------|
| `blog` | Collection slug `blog` |
| `payroll/*` | Any resource starting with `payroll/` |
| `entry:products:sku-123` | Specific entry scope |
| `commerce` (in `deny_spaces`) | Commerce admin space hooks and panels |

**Audit**

Permission changes log to the plugin permissions audit trail. Test denylist behavior with `plugin_denylist_test.php` and `plugin_denylist_spaces_test.php` in the Birkly test suite.

## Related

- [Settings](../01-admin/settings.md)
- [Plugins](plugins.md)
- [Security](../07-operations/security.md)
