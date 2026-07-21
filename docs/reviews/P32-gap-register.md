# P32 — Admin chrome & core UX gap register

**Date:** 2026-07-21 · **Reporter:** Keven (live admin review)

## Admin chrome

| ID | Gap | Current | P32 target |
|----|-----|---------|------------|
| G1-01 | Content starts lower than sidebar nav | Stacked topbar + page-header + padding | Align via `--admin-shell-offset-top` |
| G1-02 | Dead `page-header` row after breadcrumb hoist | 64px + margin on many pages | Zero height when hoisted |
| G1-03 | Primary actions in `page-header-actions` (right) | Dashboard Customize, Settings Save | `main-actions-bar` left |
| G1-04 | Entry list Edit/Delete are word buttons | `smart-table.html` text links | Icon buttons + aria-label |
| G1-05 | No sticky save on long forms | Settings save in header only | Sticky save bar (settings, entry, collection editors) |
| G1-06 | Inconsistent action placement across pages | Mixed patterns | Full page audit + standard |

## Media library

| ID | Gap | Current | P32 target |
|----|-----|---------|------------|
| G2-01 | Upload actions not in standard action bar | `.media-header` below empty page-header | `main-actions-bar` |
| G2-02 | No detail/metadata panel | Grid only | Sidebar panel: alt, focal, dimensions, URL |
| G2-03 | No bulk operations | Single-item actions | Multi-select delete/move |
| G2-04 | Weak empty/loading states | Basic loading text | Empty state + drag-drop zone |
| G2-05 | Grid-only view | No list toggle | Grid + list view |

## Fieldtypes

| ID | Gap | Current | P32 target |
|----|-----|---------|------------|
| G3-01 | No Phone fieldtype | Workaround: Text | `fieldtypes/Phone/` |
| G3-02 | Boolean UX clunky | Checkbox only | `fieldtypes/Toggle/` |
| G3-03 | No lightweight rich text | ContentEditor only | `fieldtypes/Markdown/` |
| G3-04 | No video embed field | Raw HTML in ContentEditor | `fieldtypes/OEmbed/` |
| G3-05 | No structured code field | Textarea | `fieldtypes/Code/` (JSON mode) |

## Core platform

| ID | Gap | Current | P32 target |
|----|-----|---------|------------|
| G4-01 | No entry duplicate | Manual re-create | Duplicate API + row action |
| G4-02 | Limited admin search | Topbar stub or narrow | Global search across entities |
| G4-03 | Hard delete only | Permanent removal | Trash + restore |
| G4-04 | No bulk field update | Bulk status only | Bulk edit one field for N entries |
| G4-05 | Collection setup from scratch | Empty field list | Field templates/presets |
| G4-06 | No entry revisions | Studio has revisions | Per-entry snapshot history |
| G4-07 | No redirect manager | Server/htaccess manual | Admin CRUD + runtime resolve |
| G4-08 | Workflow incomplete | `pending_review` filter exists | Inbox widget + notifications |

## Paused

| ID | Item |
|----|------|
| — | P28 journey automation (resume after P32-M24) |
| — | P26 Studio creative |
