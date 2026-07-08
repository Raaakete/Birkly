# P23 — Verification matrix

**Date:** 2026-07-08

Legend: **pass** · **pending** · **n/a**

## Studio IDE (STU-IDE)

| ID | Check | Status | Notes |
|----|-------|--------|-------|
| STU-IDE-01 | IDE workspace replaces v1 dropdown UI | pass | `studio-ide.js` + `studio.html` |
| STU-IDE-02 | File tree backed by disk | pass | `StudioFileSystem` + `list_files` API |
| STU-IDE-03 | Sandboxed CRUD (create/read/write/delete/rename) | pass | `studio_filesystem_test.php` |
| STU-IDE-04 | Path traversal blocked | pass | `resolvePath` rejects `..` |
| STU-IDE-05 | Multi-file tabs with dirty indicator | pass | Tab bar + `dirtyPaths` |
| STU-IDE-06 | Session restore via sessionStorage | pass | `SESSION_KEY` persist/restore |
| STU-IDE-07 | Visual view (canvas v2) | pass | Sections, DnD reorder, breakpoints |
| STU-IDE-08 | Code view (any project file) | pass | Textarea editor + save |
| STU-IDE-09 | Split view (visual + code) | pass | `.studio-editor-panes.split` |
| STU-IDE-10 | Open project ↗ opens new browser tab | pass | `window.open(..., '_blank')` |

## Studio pro builder (STU-PRO)

| ID | Check | Status | Notes |
|----|-------|--------|-------|
| STU-PRO-01 | Canvas v2 schema + v1 auto-migrate | pass | `StudioCanvasMigrator` |
| STU-PRO-02 | DnD section reorder | pass | drag/drop in canvas |
| STU-PRO-03 | Multi-column layouts | pass | grid columns in sections |
| STU-PRO-04 | Responsive breakpoints | pass | breakpoint selector + `buildResponsiveCss` |
| STU-PRO-05 | Design tokens (`tokens.json`) | pass | Inspector panel + API |
| STU-PRO-06 | Global components (`components/`) | pass | `list_components` / `save_component` API |
| STU-PRO-07 | Inline text editing (basic WYSIWYG) | pass | contenteditable on text nodes |
| STU-PRO-08 | Plugin blocks via `register_studio_block()` | pass | commerce + events blocks |
| STU-PRO-09 | Section library insert | pass | `plugins/studio/sections/` |
| STU-PRO-10 | Undo stack | pass | Ctrl+Z button + stack |
| STU-PRO-11 | File revisions on save | pass | `StudioRevisionStore` |
| STU-PRO-12 | Draft preview (`?birkly_studio_preview=1`) | pass | `StudioRenderer` session gate |
| STU-PRO-13 | SSR parity (compiled HTML, no JSON) | pass | `/site/{slug}/` unchanged contract |
| STU-PRO-14 | Export CSS optimizer | pass | `StudioCssOptimizer` |
| STU-PRO-15 | P22 v1 project opens without data loss | pass | migrator on `get_page` / `savePageCanvas` |

## Performance (PR)

| ID | Check | Status |
|----|-------|--------|
| PR-01 | `performance_budget_test.php` green | pass |
| PR-02 | No npm in `plugins/studio` runtime | pass |

## Video

| ID | Check | Status |
|----|-------|--------|
| V-STU-02 | IDE walkthrough evidence | pass | `docs/reviews/artifacts/v-stu-02-evidence.md` |
