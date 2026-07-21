# P31 — Verification matrix

**Date:** 2026-07-21 · **Closure:** P31-M18 — complete

## P30 closure (M0)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CL-01 | P30 matrix green | pass | `P30-verification-matrix.md` |
| CL-02 | P30 closure checklist complete | pass | `P30-phase-closure-checklist.md` |
| CL-03 | ROADMAP records P30 complete and P31 active | pass | `ROADMAP.md` |

## Widget system

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| WS-01 | Widget API v2 documented in plan and core | pass | `core/dashboard_widgets.php`, `dashboard_widget_api_test.php` |
| WS-02 | No `eval()` for widget JS | pass | `dashboard_lifecycle_contract_test.php` |
| WS-03 | All widgets use `.card.dash-widget` | pass | `dashboard.css`, `dashboard.html` |
| WS-04 | `Birkly.Dashboard.mountWidget()` loads endpoints | pass | `dashboard-widgets.js`, `dashboard_lifecycle_contract_test.php` |
| WS-05 | Commerce widget defaults on for admin | pass | `plugins/commerce/init.php`, widget tests |
| WS-06 | Events widget when active | pass | `plugins/events/`, widget tests |
| WS-07 | Studio widget when active | pass | `plugins/studio/`, widget tests |
| WS-08 | Automation widget when active | pass | `plugins/automation/`, widget tests |

## Core widgets

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| CW-01 | Quick actions | pass | core registry, browser walkthrough |
| CW-02 | Content snapshot | pass | core registry, browser walkthrough |
| CW-03 | Recent entries | pass | core registry, browser walkthrough |
| CW-04 | Recent activity restyle | pass | core registry, browser walkthrough |
| CW-05 | Drafts needing attention | pass | core registry, browser walkthrough |
| CW-06 | System status | pass | core registry, browser walkthrough |
| CW-07 | Security and access | pass | core registry, browser walkthrough |
| CW-08 | Updates | pass | core registry, browser walkthrough |
| CW-09 | Analytics mini | pass | core registry, browser walkthrough |

## Responsive

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| RS-01 | Breakpoint tokens in `variables.css` | pass | `variables.css` |
| RS-02 | Dashboard 3 → 2 → 1 columns | pass | `dashboard.css`, browser walkthrough |
| RS-03 | Main padding scales at lg/md | pass | `main-content.css` |
| RS-04 | Sidebar collapse hint below xl | pass | `sidebar.css` / `theme.js` |
| RS-05 | Entry table usable at laptop width | pass | `tables.css` |
| RS-06 | Editor usable at 1280px | pass | `main-content.css`, `content.css` |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| REG-01 | P30 breadcrumb smoke still passes | pass | `admin-content-smoke.sh` |
| REG-02 | `admin-polish-smoke.sh` | pass | extended P31 test set |
| REG-03 | `dashboard_widgets_test.php` extended | pass | widget API and lifecycle tests |
| REG-04 | `php tests/run.php` green | pass | 162 suites |

## Docs

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| DOC-01 | Birkly-Docs dashboard widgets guide prepared | pass | `Birkly-Docs/01-admin/admin-responsive-dashboard.md` |
| DOC-02 | Dashboard UX-flow coverage prepared | pass | `docs/reviews/ux-flow-matrix.md` |
