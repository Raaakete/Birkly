# P31 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P31-M18 — **pass**

## Widget API (WA)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| WA-01 | Core + plugin widgets merged in API | pass | `api/dashboard.php?action=widgets`, `core/dashboard_widgets.php` |
| WA-02 | Schema v2 fields normalized | pass | `birkly_normalize_dashboard_widget()` |
| WA-03 | No `eval()` for widget JS | pass | `admin/js/dashboard-widgets.js` |
| WA-04 | `Birkly.Dashboard.mountWidget()` mounts by name | pass | `dashboard-widgets.js` |
| WA-05 | Role filter still applies | pass | `birkly_filter_dashboard_widgets_for_user()` |

## Dashboard grid (DG)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DG-01 | Single `.dash-widget-grid` container | pass | `dashboard.html` |
| DG-02 | All widgets `.card.dash-widget` | pass | `dashboard.css`, mount render |
| DG-03 | 3-col → 2-col @ xl → 1-col @ md | pass | `dashboard.css` media queries |
| DG-04 | Core widgets: stats, activity, health, analytics | pass | mount handlers |
| DG-05 | Plugin widgets: Commerce default on | pass | `commerce/init.php` |

## Responsive shell (RS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| RS-01 | Breakpoint tokens in variables.css | pass | `--breakpoint-*` |
| RS-02 | Main padding scales at xl/lg | pass | `main-content.css` |
| RS-03 | Sidebar collapse hint below xl | pass | `sidebar.css` |
| RS-04 | Entry table laptop columns | pass | `tables.css` |
| RS-05 | Editor grid @ 1280px | pass | `main-content.css` |

## Regression (REG)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | `admin-content-smoke.sh` | pass | extended |
| REG-02 | `dashboard_widgets_test.php` | pass | extended |
| REG-03 | `admin_dashboard_paths_test.php` | pass | widget grid selector |

## Docs (DOC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs responsive dashboard | pass | `01-admin/admin-responsive-dashboard.md` |
| DOC-02 | ux-flow-matrix + ROADMAP | pass | `docs/reviews/ux-flow-matrix.md`, `ROADMAP.md` |
