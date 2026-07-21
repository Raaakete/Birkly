# P30 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P30-M18 — **pass**

## Breadcrumbs (BC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| BC-01 | Full trail when width allows | pass | `admin/js/breadcrumb-truncate.js` |
| BC-02 | Overflow → `… / Collection / Entry` (left collapse) | pass | `breadcrumb-truncate.js`, `header.css` |
| BC-03 | Last 2 segments always visible | pass | `MIN_VISIBLE_SEGMENTS = 2` |
| BC-04 | Full path in aria-label + tooltip | pass | `renderSegments()` aria-label + ellipsis title |
| BC-05 | Re-truncate on resize + sidebar toggle | pass | ResizeObserver + `birkly:layout-change` |
| BC-06 | Entry editor long title does not break topbar | pass | `.breadcrumb-segment--current` ellipsis |

## Form layout (FL)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| FL-01 | `.field-block` label above control (desktop) | pass | `content.css` column layout |
| FL-02 | ContentEditor full width in entry editor | pass | `.field-control` width rules |
| FL-03 | Collection editor field rows stacked | pass | `collection-editor.html` |
| FL-04 | `.field-block--error` shows inline message | pass | `entry-editor.js`, `core/entry_validation.php` |
| FL-05 | `.field-block--success` matches form-group style | pass | `forms.css` |
| FL-06 | Settings/plugin forms audited | pass | `.form-group` unchanged; field-block scoped |

## Layout (LAY)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| LAY-01 | Editor usable at 1280px with sidebars | pass* | `main-content.css` grid tuning; P31-M15 may refine |
| LAY-02 | No dead page-header gap after hoist | pass | `.page-header--hoisted-empty` |

## Regression (REG)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | P29 `admin-content-smoke.sh` | pass | extended with P30 tests |
| REG-02 | P27 `admin-polish-smoke.sh` | pass | unchanged |
| REG-03 | `php tests/run.php` | pass | +2 suites; 3 pre-existing flakes unchanged |

## Docs (DOC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs admin form layout | pass | `01-admin/admin-forms-navigation.md` |
| DOC-02 | ux-flow-matrix updated | pass | `docs/reviews/ux-flow-matrix.md` |

**Status:** green (2026-07-21)
