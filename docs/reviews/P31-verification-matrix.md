# P31 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P31-M18 — pending

## P30 closure (M0)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CL-01 | P30 matrix green | pass | `P30-verification-matrix.md` |
| CL-02 | P30 closure checklist complete | pass | `P30-phase-closure-checklist.md` |
| CL-03 | ROADMAP records P30 complete and P31 active | pass | `ROADMAP.md` |

## Widget system

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| WS-01 | Widget API v2 documented in plan and core | pending | `core/dashboard_widgets.php` |
| WS-02 | No `eval()` for widget JS | pending | `admin/js/dashboard.js` |
| WS-03 | All widgets use `.card.dash-widget` | pending | `dashboard.css`, `dashboard.html` |
| WS-04 | `Birkly.Dashboard.mountWidget()` loads endpoints | pending | `dashboard-widgets.js` |
| WS-05 | Commerce widget defaults on for admin | pending | Commerce settings default |
| WS-06 | Events widget when active | pending | `plugins/events/` |
| WS-07 | Studio widget when active | pending | `plugins/studio/` |
| WS-08 | Automation widget when active | pending | `plugins/automation/` |

## Core widgets

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CW-01 | Quick actions | pending | |
| CW-02 | Content snapshot | pending | |
| CW-03 | Recent entries | pending | |
| CW-04 | Recent activity restyle | pending | |
| CW-05 | Drafts needing attention | pending | |
| CW-06 | System status | pending | |
| CW-07 | Security and access | pending | |
| CW-08 | Updates | pending | |
| CW-09 | Analytics mini | pending | |

## Responsive

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| RS-01 | Breakpoint tokens in `variables.css` | pending | |
| RS-02 | Dashboard 3 → 2 → 1 columns | pending | `dashboard.css` |
| RS-03 | Main padding scales at lg/md | pending | `main-content.css` |
| RS-04 | Sidebar collapse hint below xl | pending | `sidebar.css` / `theme.js` |
| RS-05 | Entry table usable at laptop width | pending | `tables.css` |
| RS-06 | Editor usable at 1280px | pending | `main-content.css`, `content.css` |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | P30 breadcrumb smoke still passes | pending | `admin-content-smoke.sh` |
| REG-02 | `admin-polish-smoke.sh` | pending | |
| REG-03 | `dashboard_widgets_test.php` extended | pending | |
| REG-04 | `php tests/run.php` green | pending | |

## Docs

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs dashboard widgets guide prepared | prepared | `Birkly-Docs/01-admin/admin-responsive-dashboard.md` |
| DOC-02 | Dashboard UX-flow coverage prepared | prepared | `docs/reviews/ux-flow-matrix.md` |
