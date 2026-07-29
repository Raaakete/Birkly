# Admin responsive dashboard

## Summary

The P31 dashboard is a responsive grid of cards that brings shortcuts, content status, activity, and available plugin information together on the admin home page. This guide is prepared for the P31 dashboard release; use it with a Birkly version that includes the P31 widget system.

## Beginner

After you sign in, the Dashboard helps you choose your next task without opening several admin sections first.

**What you will see**

- **Quick actions** at the top for common destinations such as creating content, uploading media, opening Studio, or changing settings.
- A **content snapshot** with collection, entry, published, and draft counts.
- Recent entries and activity, plus useful status cards such as drafts, system health, security, and updates when those apply to your role.
- Extra cards from active plugins. Commerce, Events, Studio, and Automation can add their own dashboard information.

The layout changes with the available screen width: it shows three columns on wide desktop screens, two columns on laptop-sized screens, and one column on smaller screens. You do not need to configure this layout. If the sidebar leaves too little room on a laptop, use its collapse control; Birkly keeps your saved sidebar preference.

Some cards are limited to administrators. Plugin cards appear only when their plugin is active and enabled. The Commerce card is enabled by default for administrators, but an administrator can disable it in Commerce settings.

## Advanced Users

The P31 dashboard renders one `#dashboard-widget-grid` of `.card.dash-widget` elements. Widget definitions are returned by `GET /api/dashboard.php?action=widgets`, where core widgets are merged with widgets registered by active plugins and filtered by role and plugin settings.

The Widget API v2 fields are:

| Field | Purpose |
|-------|---------|
| `id` | Stable widget identifier |
| `title` | Card heading |
| `size` | Grid hint: `sm`, `md`, `lg`, or `full` |
| `priority` | Ascending display order |
| `mount` | Named client-side mount handler |
| `endpoint` | Optional API URL for widget data |
| `refresh` | Optional refresh interval in seconds; `0` disables refresh |
| `roles` | Allowed CMS roles |
| `plugin_id` | Owning plugin, used for its opt-in settings |

Use a named mount handler registered with `Birkly.Dashboard`; do not supply executable JavaScript strings. The client fetches an optional endpoint and calls `Birkly.Dashboard.mountWidget(el, widget, data)`. This avoids `eval()` and keeps rendering behavior inspectable.

Grid behavior is three columns above 1280px, two columns at and below 1280px, and one column at and below 768px. `lg` spans two columns on desktop and `full` spans all available columns.

Core widgets cover quick actions, content snapshot, analytics, recent collections and entries, recent activity, drafts, system status, security, and updates. Active Commerce, Events, Studio, and Automation plugins can contribute their own widgets through the same contract.

## Related

- [Dashboard](dashboard.md)
- [Admin appearance](admin-appearance.md)
- [Plugins](../06-extensibility/plugins.md)
- [Commerce analytics](../06-extensibility/commerce-analytics.md)
