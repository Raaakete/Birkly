# Admin plugin extension (P83 shelf)

How plugin authors extend the **Birkly admin** without editing core PHP or JS. P83 mirrors the P82 storefront shelf: core aggregates manifests and registries; plugins own presentation.

**Public storefront shelf:** [Project sites and plugins](../03-templating/project-sites-and-plugins.md) (`storefront.scripts[]`).

## Summary

Declare **`admin.scripts[]`** in `plugin.json` for admin JavaScript. Register **dashboard mount handlers** in plugin JS (`BirklyDashboardWidgets[mountName]`). Register **notification formatters** and **broadcast actors** in PHP. Register **WebMCP runtime packs** for visitor tools. Subscribe to **`birkly_entry_saved`** (and other hooks) instead of expecting core to call plugin functions directly.

Core must never hardcode official plugin IDs in `admin/js/dashboard.js`, `birkly-webmcp.js`, `api/notifications.php`, or `admin/index.php`. CI enforces this via `admin_coupling_guard_test.php`.

## Admin asset shelf (`admin.scripts[]`)

Active plugins declare scripts in `plugin.json`:

```json
{
  "admin": {
    "scripts": [
      {
        "path": "admin/dashboard-widget.js",
        "provides": "BirklyMyPluginDashboard",
        "load": "dashboard"
      },
      {
        "path": "admin/shared.js",
        "provides": "BirklyMyPluginShared",
        "load": "always"
      }
    ]
  }
}
```

| Field | Purpose |
|-------|---------|
| `path` | File under `plugins/{id}/` |
| `provides` | Global symbol used for dedupe (first active plugin wins) |
| `load` | `dashboard` (admin home only), `always`, or omit for all admin pages |

Core injects tags via `birkly_render_admin_script_tags()` in the admin bootstrap. After scripts load, the shelf dispatches **`birkly:admin-plugins-ready`** so dashboard code can mount widgets safely.

**Do not** add `<script src="/plugins/…">` manually in core admin templates.

## Dashboard widget mounts

Widget **data** comes from PHP (`dashboard_widgets_callback` on the plugin). Widget **UI** lives in plugin admin JS.

```javascript
(function () {
  'use strict';
  var UI = window.Birkly && window.Birkly.DashboardMount;
  var widgets = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
  if (!UI) return;

  widgets.myPluginStats = function (element, widget, data) {
    UI.setContent(element, UI.statGrid([
      UI.statTile(data.count || 0, 'Items', 'my_plugin'),
    ]));
  };
})();
```

The mount name (`myPluginStats`) must match the `mount` field in the widget definition returned by your PHP callback. Core `admin/js/dashboard.js` only calls registered handlers — it does not embed Commerce/Events/Studio/Automation renderers.

See also [Admin responsive dashboard](../01-admin/admin-responsive-dashboard.md).

## Notification platform shelf

Plugins register formatters and broadcast scope during bootstrap:

```php
require_once __DIR__ . '/includes/notification_shelf.php';

register_broadcast_actor('my_plugin', ['my_plugin'], ['my_plugin_']);

register_notification_formatter('my_plugin', 'my_plugin_event', static function (array $entry, string $currentUserEmail): array {
    return [
        'message' => 'Something happened',
        'icon' => '🔔',
        'color' => 'blue',
        'category' => 'my_plugin',
        'link' => '/admin/?page=my_plugin',
    ];
});
```

**Primary trigger API:** `birkly_plugin_activity_emit()` for bell + sidebar attention. Formatters cover legacy `security.log` events and activity-bus presentation.

Settings `activity_notify` and `activity_nav_dot` are auto-merged into every plugin `settings_schema`.

Implementation: `core/plugins/notification_shelf.php`.

## WebMCP runtime packs

PHP catalog tools via `birkly_webmcp_register_tools()`. Browser execution uses a **runtime pack**:

```php
// includes/webmcp_runtime.php
birkly_webmcp_register_runtime_pack('my_plugin', [
    'label' => 'My Plugin',
    'script' => 'assets/webmcp-pack.js',
    'global' => 'BirklyMyPlugin',
    'surface' => '[data-my-plugin]',
]);
```

```javascript
// assets/webmcp-pack.js — registers on window.BirklyWebMcp._packs.my_plugin
```

Core `birkly-webmcp.js` loops `config.runtime_packs` — no hardcoded `registerCommercePack()`. Tool labels in admin Discovery come from the catalog API (`admin/js/ai-presence.js`).

See [Website tools (WebMCP)](../04-integration/webmcp-tools.md).

## Hooks instead of core coupling

| Old pattern (forbidden) | Replacement |
|-------------------------|-------------|
| Core calls `enqueue_automation()` on entry save | Core `do_action('birkly_entry_saved', $payload)`; Automation plugin subscribes |
| Core `admin/index.php` special-cases `automation` | `register_plugin_admin_meta()` only |
| Core `api/studio_view.php` requires Studio files | Filter `birkly_studio_view_request`; Studio plugin handles request |
| Core `birkly_health_studio()` with plugin paths | Filter `birkly_health_checks`; Studio adds `studio` block |

Declare subscribed hooks in `plugin.json` `hooks[]` for marketplace preflight.

## Admin navigation shelf

Sidebar pages use existing APIs (unchanged by P83):

- `register_plugin_admin_meta()` — title, permissions, sidebar icon/order
- `register_admin_page()` — HTML template path
- `plugin.json` `navigation` — legacy manifest entry

P83 does not add new nav APIs; it removes core exceptions so nav is shelf-only.

## MCP tool ownership

Inbound admin MCP tools register via `register_plugin_mcp_tools()` with explicit `plugin_id`. Core `birkly_ai_tool_plugin_id()` reads the MCP shelf — not regex on tool names.

## Checklist for plugin authors

1. Add `admin.scripts[]` if you ship dashboard mounts or admin JS.
2. Register mount handlers on `window.BirklyDashboardWidgets`.
3. Emit activity with `birkly_plugin_activity_emit()`; register formatters for bell copy.
4. Register WebMCP runtime pack if you ship visitor tools.
5. Subscribe to domain hooks (`birkly_entry_saved`, …) — do not patch core.
6. Run `php tests/admin_coupling_guard_test.php` before opening a core PR (plugin repos should not trigger it).

## Related

- [Plugins overview](plugins.md)
- [Plugin development](../06-plugin-economy/plugin-development.md)
- [Admin responsive dashboard](../01-admin/admin-responsive-dashboard.md)
- [Website tools (WebMCP)](../04-integration/webmcp-tools.md)
- [Project sites and plugins](../03-templating/project-sites-and-plugins.md) (storefront shelf)
