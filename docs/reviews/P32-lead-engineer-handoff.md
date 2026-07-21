# Phase 32 — Lead-engineer handoff (Admin chrome polish & core platform UX)

**Status:** Approved (2026-07-21, Keven) — execute now  
**Human rule:** Execute **P32-M0→M24** autonomously. Notify human **once** at **P32-M24**.

**Master plan:** `docs/reviews/P32-admin-chrome-core-ux-plan.md`  
**Gap register:** `docs/reviews/P32-gap-register.md`  
**Matrix:** `docs/reviews/P32-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 32 — Admin chrome polish & core platform UX.

Human approved P32 (2026-07-21). Execute P32-M0→M24 autonomously in one wave.
Notify human ONCE at P32-M24.

PAUSED: P28 (beta journeys), P26 (Studio creative). Do NOT start those.

READ FIRST:
1. docs/reviews/P32-admin-chrome-core-ux-plan.md
2. docs/reviews/P32-gap-register.md
3. docs/reviews/P32-verification-matrix.md
4. admin/css/layout/main-content.css, header.css, sidebar.css
5. admin/templates/components/smart-table.html
6. admin/templates/entries.html  ← good main-actions-bar pattern
7. admin/templates/dashboard.html, settings.html, media.html  ← wrong action placement
8. admin/css/components/buttons.css  ← extend btn-icon variants
9. fieldtypes/FieldRegistry.php

═══ PILLAR A — ADMIN SHELL (M1–M2) ═══

Problem: Main content starts ~128px LOWER than sidebar Dashboard nav link.
Stacked: main-content padding + topbar 64px + page-header 64px (redundant after hoist).

Required (P32-D2–D3):
- CSS var --admin-shell-offset-top aligned to sidebar nav-links top
- Tighten .admin-topbar margin-bottom
- When breadcrumbs hoisted: .page-header--hoisted-empty on ALL admin pages
- Content first row aligns with first .nav-links card on left

═══ PILLAR B — ACTION CHROME (M3–M9) ═══

Problem: Primary actions on RIGHT (page-header-actions). Dashboard Customize,
Settings Save wrong. Entry list uses word buttons for edit/delete.

Required (P32-D4–D7):
- Canonical zones: topbar-right = theme/account ONLY
- main-actions-bar: PRIMARY actions LEFT (btn-primary first)
- Deprecate page-header-actions for primary CTAs

Icon row actions (M7):
- smart-table.html/php: .btn-icon--edit (pencil SVG), .btn-icon--danger (trash)
- aria-label + title; no visible text

Sticky save bar (M5–M6):
- New partial: admin/partials/sticky-save-bar.php
- position: sticky; top: below topbar; z-index; shadow on scroll
- Settings: move Save into sticky bar (toast stays in bar)
- entry-editor.html + collection-editor.html: Save / Save & close / Publish in sticky bar
- Plugin admin pages with save: adopt same pattern where applicable

Page migrations (M8–M9):
- dashboard.html: Customize → main-actions-bar left (icon + label OK)
- settings.html: Save → sticky bar
- media.html: Upload + New folder → main-actions-bar left
- Audit: content, plugins, help, webhooks, updates, roles, entry-detail, plugin pages

═══ PILLAR C — MEDIA LIBRARY (M10–M12) ═══

Required (P32-D8):
- main-actions-bar + unified toolbar (search, type filter, view toggle)
- Detail panel: filename, size, dimensions, alt text, focal point, copy URL
- Bulk select, move folder, delete
- Empty state illustration + drag-drop upload on grid
- Grid / list view toggle
- Dark mode pass on cards

═══ PILLAR D — FIELDTYPES (M13–M16) ═══

Self-contained under fieldtypes/{Name}/ per PROJECT_VALUES.md.

M13: Phone — tel input, validation
M14: Toggle — switch UI for boolean
M15: Markdown — editor + preview (dependency-free JS or minimal)
M16: OEmbed/Video (URL → embed) + Code/JSON (syntax highlight, JSON validate)

Register via FieldRegistry auto-discovery. Collection editor picker updated.
Tests per fieldtype.

═══ PILLAR E — CORE PLATFORM (M17–M23) ═══

M17: Entry duplicate
- API: POST duplicate entry (slug suffix -copy or -copy-N)
- UI: icon in smart-table row actions + entry editor
- Permission: same as create entry

M18: Global admin search
- Extend topbar search: entries, collections, media, admin routes
- API: api/search.php or extend existing
- Keyboard / to focus

M19: Soft delete / trash
- core/trash.php — move deleted entries/collections to trash store
- Trash admin page or filter; restore + permanent delete
- Update delete flows (smart-table, bulk delete) to soft-delete by default

M20: Bulk field edit
- From entries list bulk toolbar: "Edit field…" modal
- Pick one field, set value for all selected entries

M21: Collection field templates
- When creating collection: optional template (Blog post, Product, Landing, Empty)
- Seeds field definitions

M22: Entry revision history
- core/revisions.php — snapshot on save (keep last N per entry)
- Entry editor sidebar tab or modal: list revisions, diff, restore

M23: Redirect manager + workflow polish
- core/redirects.php + settings tab or admin page: from → to, 301/302
- Runtime: server-router.php or core hook resolves redirects
- Pending-review inbox: dashboard widget or entries filter prominence + optional webhook

═══ MILESTONES ═══

M0:  Gap + matrix + page action audit spreadsheet
M1:  Shell alignment CSS
M2:  Hoisted page-header everywhere
M3:  btn-icon variants (edit, delete, customize, duplicate)
M4:  main-actions-bar partial/pattern
M5:  Sticky save bar + settings
M6:  Sticky save entry + collection editors
M7:  Smart-table icon actions
M8:  Dashboard + settings action migration
M9:  Full admin page audit
M10: Media action bar + toolbar
M11: Media detail panel (alt, focal)
M12: Media bulk + empty + drag-drop + list view
M13: Phone fieldtype
M14: Toggle fieldtype
M15: Markdown fieldtype
M16: oEmbed + Code fieldtypes
M17: Entry duplicate
M18: Global admin search
M19: Trash + restore
M20: Bulk field edit
M21: Collection field templates
M22: Entry revisions
M23: Redirects + workflow polish
M24: Smoke tests, Birkly-Docs, matrix green, single notification

DELEGATION:
cms-developer:      M1–M23 (primary)
frontend-developer: M5–M12, M7 (admin UI)
design-reviewer:    M1–M12 (chrome consistency)
docs-writer:        M24 docs
qa-verifier:        M24 matrix + regression

DO NOT:
- Start P28 or P26
- Fork CMS logic into client sites
- Add heavy JS dependencies to fieldtypes (keep dependency-free core)

CLOSURE:
[ ] Content aligns with sidebar nav top
[ ] Primary actions left; sticky save on editors/settings
[ ] Icon edit/delete on entry tables
[ ] Media library polished
[ ] 5 new fieldtypes registered
[ ] All 8 core features shipped with tests
[ ] admin-polish-smoke + tests green
[ ] Single digest at M24
```

---

## Human approval checklist

- [x] Full P32 scope approved — chrome + media + all fieldtypes + all core features (2026-07-21)
- [x] Sticky save bars for settings, entry, collection editors (2026-07-21)
- [x] Icon buttons for entry list actions (2026-07-21)
- [x] Shell alignment + action bar left standard (2026-07-21)
- [x] Pause P28 until P32-M24 (2026-07-21)
- [x] M0–M24 single-wave execution approved (2026-07-21)
