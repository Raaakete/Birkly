# Activity feed

## Summary

The **Activity** page and dashboard **Recent activity** widget show an auditable timeline of CMS events — content changes, users, security, project files, git sync, and plugin bus events (P61).

## Beginner

Open **Activity** from the admin sidebar. Use category and severity filters to narrow the list. Each row shows who did what, a severity badge, target context, and timestamp. Click **Details** to expand JSON detail, or **Open** when a deep link is available (entry editor, project settings, plugin admin).

**Settings → Project → Recent activity** uses the same row component with filters for **Files**, **Git**, and **Settings** events.

## Advanced Users

**Sources:** Primary feed reads `logs/security.log` via `core/activity_format.php`. Plugin bus items from `core/plugin_activity.php` are merged on the Activity API. Project activity API filters security log events for project/git/file actions.

**Schema v2 fields:** `id`, `severity`, `actor`, `target`, `summary`, `detail`, `href`, `source`, `correlation_id`, plus legacy `message`, `category`, `icon`.

## Related

- [Dashboard](dashboard.md)
- [Settings](settings.md)
- [Project Git sync](project-git-sync.md)
