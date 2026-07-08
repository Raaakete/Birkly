# P20 — JS ↔ PHP templating parity matrix

**Date:** 2026-07-08 · **Engines:** `admin/template_parser.php` (admin) · `birkly-client.js` (public)  
**Spec:** `docs/TEMPLATE_LANGUAGE_SPEC.md`

Legend: **pass** = both engines support · **partial** = works with limits · **fail** = missing or broken on client · **n/a** = admin-only

| Feature | PHP admin | JS client | Status | Notes / milestone |
|---------|-----------|-----------|--------|-------------------|
| `{title}` / `{'field'}` | pass | pass | pass | |
| `{for each entry in 'col'}` | pass | pass | pass | |
| `{for … with status = published}` | pass | pass | pass | |
| `{for … ordered by newest}` | pass | pass | pass | Client sorts in `processForLoops` |
| `{for … ordered by oldest}` | pass | pass | pass | |
| `{from 'col' get entry 'slug'}` | pass | pass | pass | |
| `{from 'tags' get item.newest}` | partial | pass | pass | |
| `{if 'col'.'entry' exist}` | pass | pass | pass | |
| `{if entry has 'field'}` | pass | pass | pass | |
| `{if entry has 'price' <= 15}` | pass | pass | pass | |
| `{count of 'col'}` | pass | pass | pass | |
| `{for each entry in 'sub' of 'parent'.'slug'}` | pass | pass | pass | M10 |
| `{entry for 'collection'}` blocks | n/a | pass | pass | Client-only live path |
| `data-birkly-prefetch` | n/a | pass | pass | Bundled in `page_bundle` |
| Template functions (plugins) | pass | pass | pass | Parallel batch, not in bundle |
| `{set var = {func args}}` | partial | pass | pass | |
| Date `format` filter | pass | pass | pass | |
| `page_bundle` prefetch | n/a | partial | partial | M1: add `get_entry` for slug pages |
| Slug detail `get_entry` | pass (API) | fail | **fail→M1** | G-01: client scans list |
| Sub-entries threaded render | pass | pass | pass | `birkly-client-sub-entries.js` |
| Admin `{admin_url …}` | pass | n/a | n/a | |
| `{json collection}` | pass | n/a | n/a | |

## Closure gate (P20-M12)

All rows marked **fail** or **partial** for client-facing features must be **pass** before human notification.
