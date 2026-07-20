# P29 — Collections & entries gap register

**Date:** 2026-07-20 · **Reporter:** Keven (live admin)

## Critical — editor sidebar empty

| ID | Gap | Root cause | P29 target |
|----|-----|------------|------------|
| G1-01 | Entry editor right sidebar tabs show labels but **no content** | `admin/css/components/tabs.css` sets `.tab-content { display: none }`; editors use `.tab-pane.active` inside `.tab-content` without `.active` on wrapper | Scope CSS + override in `content.css` |
| G1-02 | Collection editor sidebar same bug | Same CSS conflict | Same fix |
| G1-03 | Tab JS uses global `document.querySelectorAll('.tab-pane')` | Can break unrelated tabs; fragile | Scope to `.sidebar-tabs` |

## Slug & field placement

| ID | Gap | Current | P29 target |
|----|-----|---------|------------|
| G2-01 | Slug appears in main column (when schema has slug field) | System slug in sidebar (hidden) + schema field in main | Reserved fields only in sidebar |
| G2-02 | Slug auto-fill targets `#field_title` only | Breaks when title field id differs | Resolve title field generically |
| G2-03 | No title → slug empty on create | API generates slug from title string only | Fallback: title = ID, slug = ID |
| G2-04 | No “sync slug with title” toggle | Manual slug edit only | Checkbox **checked by default**; uncheck = custom slug mode |

## Core UX gaps

| ID | Gap | Current | P29 target |
|----|-----|---------|------------|
| G3-01 | Entry/collection editor responsive layout inconsistent | `collection-editor` grid vs generic `editor-layout` | Unified editor chrome |
| G3-02 | Templating tab untested after sidebar fix | Hidden | Verify copy buttons + examples |
| G3-03 | `ux-flow-matrix.md` stale for content flows | P4 era | Refresh from P29 matrix |
| G3-04 | No CI smoke for content editors | — | `admin-content-smoke.sh` |

## Paused (not P29)

| ID | Item |
|----|------|
| — | P28 journey automation (resume after P29-M15) |
| — | P26 Studio creative suite |
| — | Human beta go-live (DNS, Stripe, legal) |
