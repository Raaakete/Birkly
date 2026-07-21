# P31 — Admin responsive system & dashboard excellence

**Status:** Approved (2026-07-21, Keven) — execute now  
**Baseline:** P30 complete · P28 **paused** · P26 **paused**  
**Human rule:** Execute P31-M0→M18 autonomously. Notify human **once** at **P31-M18**.

**Gap register:** `docs/reviews/P31-gap-register.md`  
**Matrix:** `docs/reviews/P31-verification-matrix.md`

---

## Vision

Unify the admin dashboard into a **responsive widget grid** with a safe Widget API v2 (no `eval`), and tighten the **responsive shell** for laptop viewports after P30 navigation/form polish.

**Scope:** `admin/css/`, `admin/js/`, `admin/templates/`, `core/dashboard_widgets.php`, `api/dashboard.php`, plugin widget callbacks, tests, Birkly-Docs.

**Out of scope:** Drag-and-drop widget reorder, P28, P26.

---

## Locked decisions

| ID | Decision |
|----|----------|
| **P31-D1** | Widget schema v2: `size`, `priority`, `endpoint`, `mount`, `refresh` |
| **P31-D2** | `Birkly.Dashboard.mountWidget()` in `admin/js/dashboard-widgets.js` — **no `eval()`** |
| **P31-D3** | Core widget registry in `core/dashboard_widgets.php` merged with plugin widgets |
| **P31-D4** | Single `.dash-widget-grid`; all widgets use `.card.dash-widget` |
| **P31-D5** | Grid: 3-col → 2-col @ xl (1280px) → 1-col @ md (768px) |
| **P31-D6** | Breakpoint tokens in `variables.css`; main padding scales down |
| **P31-D7** | Sidebar collapse hint visible below xl |
| **P31-D8** | Commerce dashboard widget **default on** for admin |
| **P31-D9** | Single notification at **P31-M18** |

---

## Milestones (18)

| ID | Focus |
|----|--------|
| **P31-M0** | Gap register + matrix + P30 closure docs |
| **P31-M1** | Breakpoint tokens in `variables.css` |
| **P31-M2** | Main padding + sidebar collapse hint @ xl |
| **P31-M3** | Core widget registry + normalize schema |
| **P31-M4** | `dashboard-widgets.js` + remove `eval()` |
| **P31-M5** | `dashboard.html` unified widget grid |
| **P31-M6** | `dashboard.css` widget grid + `.dash-widget` styles |
| **P31-M7** | Core widget: quick actions |
| **P31-M8** | Core widget: content snapshot (stats) |
| **P31-M9** | Core widgets: recent collections / entries / activity |
| **P31-M10** | Core widgets: drafts, system status, security, updates |
| **P31-M11** | Core widget: analytics mini |
| **P31-M12** | Plugin widgets: Commerce, Events, Studio, Automation |
| **P31-M13** | Entry table laptop column pass |
| **P31-M14** | Editor 1280px responsive tuning |
| **P31-M15** | Extend `admin-content-smoke.sh` |
| **P31-M16** | Birkly-Docs responsive dashboard page |
| **P31-M17** | `ux-flow-matrix.md` + ROADMAP |
| **P31-M18** | Closure checklist + single human digest |
