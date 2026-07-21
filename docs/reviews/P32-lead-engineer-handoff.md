# Phase 32 — Lead-engineer handoff

**Status:** Approved (2026-07-21, Keven)  
**Human rule:** Execute P32-M0→M24 autonomously. Notify once at P32-M24.

**Master plan:** `docs/reviews/P32-admin-chrome-core-ux-plan.md`  
**Gap register:** `docs/reviews/P32-gap-register.md`  
**Matrix:** `docs/reviews/P32-verification-matrix.md`

## M0 boundary

P31 is on `main`. M0 creates P32 docs and verifies P31 closure. Do not start P28 or P26.

## Milestone table

| ID | Deliverable |
|----|-------------|
| M0 | Plan, gap register, matrix, handoff (this wave) |
| M1 | Shell: content/sidebar top alignment |
| M2 | Shell: hoisted breadcrumb hides dead page-header |
| M3 | `main-actions-bar` CSS tokens + PHP partial |
| M4 | Smart-table `.btn-icon--edit` / `.btn-icon--danger` |
| M5 | Sticky save bar component + CSS |
| M6 | Fix `.btn-icon` neutral vs danger modifiers |
| M7 | Dashboard → main-actions-bar |
| M8 | Settings + editors → sticky save bar |
| M9 | Admin page audit (webhooks, roles, activity, audit-log, updates) |
| M10 | Media → main-actions-bar |
| M11 | Media detail panel alt + focal |
| M12 | Media bulk, drag-drop, list/grid toggle |
| M13 | Phone fieldtype |
| M14 | Toggle fieldtype |
| M15 | Markdown fieldtype |
| M16 | oEmbed + Code/JSON fieldtypes |
| M17 | Entry duplicate |
| M18 | Global admin search |
| M19 | Soft delete / trash |
| M20 | Bulk field edit |
| M21 | Collection field templates |
| M22 | Entry revision history UI |
| M23 | Redirect manager + workflow inbox link |
| M24 | Tests, docs, matrix green, notify human |

## Ownership

| Agent | Scope |
|-------|-------|
| Lead / CMS developer | M1–M23 implementation |
| Frontend developer | Action chrome, media UI |
| Docs writer | M24 Birkly-Docs |
| QA verifier | M24 matrix + CI |
