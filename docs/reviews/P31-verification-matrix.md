# P31 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P31-M18 — pending

## P30 closure (M0)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CL-01 | P30 matrix green | pending | `P30-verification-matrix.md` |
| CL-02 | P30 closure checklist complete | pending | `P30-phase-closure-checklist.md` |
| CL-03 | ROADMAP marks P30 complete | pending | `ROADMAP.md` |

## Widget system (WS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| WS-01 | Widget API v2 documented in plan + core | pending | `core/dashboard_widgets.php` |
| WS-02 | No `eval()` for widget JS | pending | `admin/js/dashboard.js` |
| WS-03 | `.card.dash-widget` used for all widgets | pending | `dashboard.css`, `dashboard.html` |
| WS-04 | `Birkly.Dashboard.mountWidget` loads endpoints | pending | `dashboard-widgets.js` |
| WS-05 | Commerce widget default on for admin | pending | `commerce/init.php` settings default |
| WS-06 | Events widget when plugin active | pending | `plugins/events/` |
| WS-07 | Studio widget when plugin active | pending | `plugins/studio/` |
| WS-08 | Automation widget when plugin active | pending | `plugins/automation/` |

## Core widgets (CW)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CW-01 | Quick actions widget | pending | |
| CW-02 | Content snapshot widget | pending | |
| CW-03 | Recent entries widget | pending | |
| CW-04 | Recent activity widget (restyled) | pending | |
| CW-05 | Drafts needing attention | pending | |
| CW-06 | System status widget | pending | |
| CW-07 | Security & access widget | pending | |
| CW-08 | Updates widget | pending | |
| CW-09 | Analytics mini widget | pending | |

## Responsive (RS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| RS-01 | Breakpoint tokens in variables.css | pending | |
| RS-02 | Dashboard 3→2→1 col grid | pending | `dashboard.css` |
| RS-03 | Main padding scales at lg/md | pending | `main-content.css` |
| RS-04 | Sidebar collapse hint below xl | pending | `sidebar.css` / `theme.js` |
| RS-05 | Entry table usable at laptop | pending | `tables.css` |
| RS-06 | Editor usable at 1280px (LAY-01) | pending | `main-content.css`, `content.css` |

## Regression (REG)

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | P30 breadcrumb smoke still passes | pending | `admin-content-smoke.sh` |
| REG-02 | P27 `admin-polish-smoke.sh` | pending | |
| REG-03 | `dashboard_widgets_test.php` extended | pending | |
| REG-04 | `php tests/run.php` green | pending | |

## Docs (DOC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs dashboard widgets guide | pending | |
| DOC-02 | ux-flow-matrix dashboard section | pending | |
