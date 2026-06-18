# Admin template inline-JS audit (M3-T4)

Audit of `admin/templates/*.html` for UI/logic mixing. Goal: templates = markup + `{include_script}`; behavior in `admin/js/`.

## Reference implementation

| Page | Template | JS | Notes |
|------|----------|-----|-------|
| Activity | `activity.html` | `activity.js` | **Canonical** — no inline `<script>`; CSRF via layout meta |

## Pages with inline bootstrap scripts (acceptable short-term)

These pass data via `window.*Data` + DOMContentLoaded init. Migrate to meta-tag CSRF + self-init JS when touched:

| Template | Inline script | External JS |
|----------|---------------|-------------|
| `audit-log.html` | bootstrap | `audit-log.js` (missing — uses settings tab code) |
| `roles.html` | JSON data + init | `roles.js` |
| `webhooks.html` | bootstrap | webhooks |
| `automation.html` | bootstrap | `automation.js` |
| `dashboard.html` | bootstrap | `dashboard.js` |
| `entries.html` | bootstrap | `entries.js` |
| `entry-editor.html` | bootstrap | `entry-editor.js` |
| `entry-detail.html` | multiple blocks | mixed |
| `media.html` | large inline block | partial |
| `settings.html` | very large inline | `settings.js` + embedded audit log |
| `collection-editor.html` | inline | — |
| `updates.html` | inline | — |

## Pages with raw PHP in templates (separate debt)

| Template | Issue |
|----------|-------|
| `roles.html` | `<?php … ?>` user list filter in template |

## Next migrations (post-M3)

1. Extract `settings.html` audit-log block → `audit-log.js` (shared with `audit-log.html`)
2. Remove `<?php` from `roles.html` — precompute `role.users` in `admin/index.php`
3. Split `media.html` inline JS to `media-page.js`

## Values check

✅ M3 establishes pattern; full migration is incremental and does not block M3 acceptance.
