# Phase 32 — Admin chrome polish & core platform UX

**Status:** Approved (2026-07-21, Keven)  
**Execution:** Autonomous M0→M24 single wave. Notify human once at M24.

## Goal

Unify admin action chrome (where CTAs live), polish the shell alignment, upgrade media UX, add essential fieldtypes, and deliver core content-management features editors expect.

## Pillars

### A — Shell (M1–M2)

- Align main content top edge with sidebar nav start (no visual gap drift).
- When breadcrumbs are hoisted to topbar, hide in-page `page-header` (zero dead header band).
- Reference: `admin/css/layout/main-content.css`, `sidebar.css`, `header.css`.

### B — Action chrome (M3–M9)

- **main-actions-bar:** primary CTAs on the **left**; filters/secondary on the right.
- **topbar-right:** theme switcher, search, notifications, account only — no page Save/Customize/Upload.
- Icon row actions on smart-table: `.btn-icon--edit`, `.btn-icon--danger`.
- **Sticky save bar** on settings, entry editor, collection editor.
- Migrate dashboard Customize, settings Save, media Upload into correct zones.
- Full admin page audit — every list/editor page follows entries.html pattern.

### C — Media (M10–M12)

- Standard `main-actions-bar` (upload, new folder, view toggle).
- Detail panel: alt text, focal point.
- Bulk select/delete/move; drag-drop upload; list/grid toggle.

### D — Fieldtypes (M13–M16)

| Type | Slug | Notes |
|------|------|-------|
| Phone | `phone` | E.164-ish validation, tel input |
| Toggle | `toggle` | Boolean on/off switch |
| Markdown | `markdown` | Textarea + preview tab |
| oEmbed / Video | `oembed` | URL embed (YouTube, Vimeo, etc.) |
| Code / JSON | `code` | Monospace editor, JSON validate option |

Auto-discovered via `fieldtypes/` directory scan.

### E — Core platform (M17–M23)

| ID | Feature |
|----|---------|
| M17 | Entry duplicate (API + entries list action) |
| M18 | Global admin search (wire topbar search modal) |
| M19 | Soft delete / trash + restore |
| M20 | Bulk field edit (selected entries, one field) |
| M21 | Collection field templates (save/load field sets) |
| M22 | Entry revision history UI (build on `saveVersionHistory`) |
| M23 | Redirect manager (CRUD + 301 storage) |
| M24 | Workflow polish — pending-review inbox link from dashboard |

### M24 — Closure

- `scripts/agent-smoke-test.sh` + new chrome assertions.
- Birkly-Docs admin chrome guide.
- Verification matrix green.
- Single human notification (digest/email).

## Out of scope

- P28 beta journeys
- P26 Studio creative
- Marketplace / commerce scope changes

## Branch convention

`cursor/p32-m[N]-<slug>-8350`
