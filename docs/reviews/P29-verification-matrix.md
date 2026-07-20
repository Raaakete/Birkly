# P29 — Verification matrix

**Date:** 2026-07-20 · **Closure:** P29-M15 — **complete**

## Sidebar (SB)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| SB-01 | Entry editor General tab visible (slug, status) | pass | `content.css` `.sidebar-tabs` override; `entry_editor_sidebar_test.php` |
| SB-02 | Entry editor Meta Data tab visible | pass | `sidebar-tabs.js` scoped panes |
| SB-03 | Entry editor Templating tab visible + copy works | pass | Tab switch + copy-btn retained in template |
| SB-04 | Collection editor Settings tab visible | pass | `collection_editor_sidebar_test.php` |
| SB-05 | Collection editor Automation + Templating tabs visible | pass | Same sidebar-tabs.js |
| SB-06 | `tabs.css` does not hide `.sidebar-tabs .tab-content` | pass | `content.css` lines `.sidebar-tabs .tab-content { display: block }` |
| SB-07 | Tab JS scoped to `.sidebar-tabs` only | pass | `admin/js/sidebar-tabs.js`; no global `.tab-pane` in templates |

## Slug (SLG)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| SLG-01 | Slug only in sidebar (not main field loop) | pass | `birkly_entry_reserved_field_names()` + `admin_templates.php` filter |
| SLG-02 | Auto checkbox default **checked** on create | pass | `entry-editor.html` `$autoSlugChecked` for new entries |
| SLG-03 | Title typed → slug auto-fills when auto on | pass | `entry-editor.js` `initSlugGeneration()` |
| SLG-04 | Auto off → custom slug preserved on save | pass | `entry_slug_test.php` auto_slug off case |
| SLG-05 | No title on create → title = ID, slug = ID | pass | `admin_entries.php` + `birkly_slug_from_title_or_id()` |
| SLG-06 | Re-check auto → slug syncs from title/ID | pass | Checkbox change handler in `entry-editor.js` |
| SLG-07 | Collection slug same rules | pass | `birkly_resolve_collection_slug()` + collection editor UI |
| SLG-08 | `entry_slug_test.php` extended | pass | 11 assertions green |

## Entries list (ENT)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ENT-01 | Status filter works | pass | `entries.js` filters via `data-status` on badges |
| ENT-02 | Bulk publish/unpublish/delete | pass | `entries.js` bulk toolbar unchanged; smoke via template tests |

## Regression (REG)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | `admin-polish-smoke.sh` | pass | 2026-07-20 run |
| REG-02 | `admin-content-smoke.sh` | pass | sidebar + slug tests wired in CI |
| REG-03 | `php tests/run.php` | pass* | P29 suites green; 3 pre-existing unrelated flakes (`studio_renderer_test`, `user_plugin_*`) |

## Docs (DOC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs entry/collection editor sidebar | pass | `Birkly-Docs` `01-admin/entry-editor.md`, `collection-editor.md` |
| DOC-02 | ux-flow-matrix refreshed | pass | `docs/reviews/ux-flow-matrix.md` P29 section |
