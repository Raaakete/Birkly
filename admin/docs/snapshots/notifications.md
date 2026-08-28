# Admin notifications

## Summary

The topbar **notification bell** shows platform events: content publishes, plugin activity (P61 bus), security events, and more. **P69** adds a **seen** state distinct from **read**.

## Beginner

**Unread → Seen → Read**

1. **Unread** — Accent badge count; unread rows show a dot and stronger styling.
2. **Seen** — Opening the dropdown marks visible items as seen. The badge turns **grey** even if you did not click an item.
3. **Read** — Clicking a notification or using **Mark all read** sets `read_at` and persists read state.

Seen state is per user and stored alongside read IDs in the notifications store.

## Advanced Users

**API:** `POST /api/notifications.php` with `action=mark_seen` and visible notification IDs when the dropdown opens. Responses include `badge_state`: `unread`, `seen`, or `none`, and `pending_count` for accent display.

**Plugin activity:** Commerce and other plugins can emit to the P61 plugin activity bus; those items appear in the bell when `activity_notify` is enabled for the plugin.

## Related

- [Dashboard](dashboard.md)
- [Activity](activity.md)
- [Settings](settings.md)
