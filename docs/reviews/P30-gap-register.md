# P30 — Admin form & navigation gap register

**Date:** 2026-07-21 · **Reporter:** Keven (live admin on laptop)

## Breadcrumbs

| ID | Gap | Current | P30 target |
|----|-----|---------|------------|
| G1-01 | Long breadcrumb overflows topbar on laptop | `text-overflow: ellipsis` on whole nav — truncates **right** (loses entry name) | Collapse **from left**: `… / Collection / Entry` |
| G1-02 | No full-path access when collapsed | — | `title` tooltip + `aria-label` with full trail |
| G1-03 | Breadcrumb font large in topbar context | `.breadcrumbs` up to 1.5rem | Smaller scale in `#admin-topbar-breadcrumbs` |

## Form layout

| ID | Gap | Current | P30 target |
|----|-----|---------|------------|
| G2-01 | Entry/collection field labels left of control (250px column) | `.field-block { flex-direction: row }` | Label **above** control (column) |
| G2-02 | ContentEditor squeezed on laptop | Horizontal layout until 768px only | Full-width fieldtypes at all breakpoints |
| G2-03 | `.field-block` error states inconsistent | `.form-group--error` exists but not on field-block | Unified error/success on `.field-block` |
| G2-04 | API validation errors not field-scoped in UI | Toast-only in some paths | Inline message under field |

## Layout

| ID | Gap | Current | P30 target |
|----|-----|---------|------------|
| G3-01 | Editor grid tight at ~1280px with nav + sidebar | `editor-layout` 3fr/1fr at 1200px | Tune min-widths / breakpoint |
| G3-02 | Empty `page-header` row after breadcrumb hoist | Hidden via JS | CSS cleanup — no dead spacing |

## Paused

| ID | Item |
|----|------|
| — | P28 journey automation (resume after P30-M18) |
| — | P26 Studio creative |
