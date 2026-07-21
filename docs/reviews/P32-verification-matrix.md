# P32 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P32-M24 — pending

## Shell (SH)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| SH-01 | Content top aligns with sidebar nav | pending | `main-content.css`, `sidebar.css` |
| SH-02 | Hoisted pages have no dead page-header gap | pending | `header.css`, templates |
| SH-03 | Topbar right = chrome only | pending | page audit |

## Actions (AC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| AC-01 | `main-actions-bar` primary left pattern documented | pending | partial + docs |
| AC-02 | Dashboard Customize on left action bar | pending | `dashboard.html` |
| AC-03 | Settings save in sticky bar | pending | `settings.html`, `sticky-save-bar` |
| AC-04 | Entry/collection editors sticky save | pending | editor templates |
| AC-05 | Smart-table edit/delete icons | pending | `smart-table.html` |
| AC-06 | Full page action audit pass | pending | audit checklist |

## Media (MD)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| MD-01 | Media action bar + toolbar | pending | `media.html` |
| MD-02 | Detail panel alt + focal point | pending | `media-page.js` |
| MD-03 | Bulk select/move/delete | pending | |
| MD-04 | Empty state + drag-drop upload | pending | |
| MD-05 | Grid/list view toggle | pending | |

## Fieldtypes (FT)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| FT-01 | Phone fieldtype | pending | `fieldtypes/Phone/` |
| FT-02 | Toggle fieldtype | pending | `fieldtypes/Toggle/` |
| FT-03 | Markdown fieldtype | pending | `fieldtypes/Markdown/` |
| FT-04 | oEmbed/Video fieldtype | pending | `fieldtypes/OEmbed/` |
| FT-05 | Code/JSON fieldtype | pending | `fieldtypes/Code/` |

## Core (CR)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CR-01 | Entry duplicate | pending | API + UI |
| CR-02 | Global admin search | pending | `api/search.php` |
| CR-03 | Soft delete + trash + restore | pending | `core/trash.php` |
| CR-04 | Bulk field edit | pending | entries UI + API |
| CR-05 | Collection field templates | pending | collection editor |
| CR-06 | Entry revision history | pending | `core/revisions.php` |
| CR-07 | Redirect manager | pending | `core/redirects.php` |
| CR-08 | Pending-review workflow polish | pending | widget + filter |

## Regression (REG)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | P31 dashboard widgets still work | pending | |
| REG-02 | P30 breadcrumbs + forms | pending | |
| REG-03 | `admin-polish-smoke.sh` extended | pending | |
| REG-04 | `php tests/run.php` green | pending | |

## Docs (DOC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs admin chrome + new fieldtypes | pending | |
| DOC-02 | ux-flow-matrix P32 section | pending | |
