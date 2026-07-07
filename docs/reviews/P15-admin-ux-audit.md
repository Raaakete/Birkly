# P15 — Admin UX audit (top 10 flows)

**Date:** 2026-07-07 · **Scope:** P15-M1 dark mode + responsive pass

## Flows to audit

| # | Flow | Primary templates / JS |
|---|------|------------------------|
| 1 | Dashboard | `admin/templates/dashboard.html` |
| 2 | Entry list | `admin/templates/entries.html`, `entries.js` |
| 3 | Entry editor | `admin/templates/entry-editor.html` |
| 4 | Settings (all tabs) | `settings.html`, `settings-page.js` |
| 5 | Commerce admin | `plugins/commerce/admin/*` |
| 6 | Marketplace / Plugins | `plugins.html`, `plugins-page.js` |
| 7 | Media library | `media.html` |
| 8 | Help | `help.html`, `help.js` |
| 9 | Onboarding | `onboarding.html` |
| 10 | Login | `login.php` |

## Dark mode checklist

- [ ] Theme toggle in admin header (`Birkly.Theme`)
- [ ] CSS variables readable in `[data-theme="dark"]`
- [ ] Commerce tables, badges, wizard panels
- [ ] Settings stacks and denylist panels
- [ ] ContentEditor toolbar contrast

## Responsive checklist (768px)

- [ ] Sidebar collapses or stacks
- [ ] Entry list table scrolls horizontally
- [ ] Commerce admin tabs wrap
- [ ] Settings tabs usable on narrow viewport
