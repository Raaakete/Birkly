# Phase 31 — Lead-engineer handoff (Admin responsive system & dashboard excellence)

**Status:** Approved (2026-07-21, Keven) — execute now  
**Human rule:** Execute **P31-M0→M18** autonomously. Notify human **once** at **P31-M18**.

**Master plan:** `docs/reviews/P31-admin-responsive-dashboard-plan.md`  
**Gap register:** `docs/reviews/P31-gap-register.md`  
**Matrix:** `docs/reviews/P31-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 31 — Admin responsive system & dashboard excellence.

Human approved P31 (2026-07-21). Execute P31-M0→M18 autonomously in one wave.
Notify human ONCE at P31-M18.

PAUSED: P28 (beta journeys), P26 (Studio creative). Do NOT start those.

READ FIRST:
1. docs/reviews/P31-admin-responsive-dashboard-plan.md
2. docs/reviews/P31-gap-register.md
3. docs/reviews/P31-verification-matrix.md
4. admin/templates/dashboard.html
5. admin/css/pages/dashboard.css  ← bespoke styling vs cards.css
6. admin/js/dashboard.js  ← eval(), hardcoded widget loaders
7. core/dashboard_widgets.php + api/dashboard.php?action=widgets
8. plugins/commerce/init.php → commerce_dashboard_widgets()
9. admin/css/components/cards.css  ← target design system
10. admin/css/core/variables.css  ← add breakpoint tokens

═══ P31-M0 — CLOSE P30 + SETUP ═══

P30 code is ALREADY on main (#178–#181). Your first job is docs closure only:
- Mark P30-verification-matrix.md green (breadcrumb-truncate.js, stacked field-block)
- Complete P30-phase-closure-checklist.md
- ROADMAP already updated by plan PR — verify

Do NOT re-implement P30 features.

═══ PILLAR A — WIDGET API v2 (M3–M4) ═══

Problem: Plugin widgets return raw HTML; dashboard.js uses eval() and hardcodes
commerce-stats / payments-stats loaders. Only Commerce registers a widget.
Section stays display:none when empty.

Required (P31-D8–D9):
- Extend widget schema: size (sm|md|lg|full), priority, endpoint, mount, refresh
- admin/js/dashboard-widgets.js: Birkly.Dashboard.mountWidget(el, widget, data)
- REMOVE eval(widget.javascript) — use named mount functions only
- Core widget registry (PHP) for first-party widgets alongside plugin callbacks
- Filter via birkly_filter_dashboard_widgets_for_user() (existing)

Widget contract example:
{
  id: 'events-upcoming',
  title: 'Upcoming events',
  size: 'md',
  priority: 50,
  endpoint: '/api/index.php?route=events&action=dashboard_summary',
  mount: 'BirklyDashboardWidgets.events'
}

═══ PILLAR B — DASHBOARD REBUILD (M5–M12) ═══

Problem: Dashboard looks unlike rest of admin. Four stat cards, hidden analytics
section, orphaned plugin row, inline-styled analytics HTML.

Required (P31-D5, D9–D12):
- Single widget grid in dashboard.html — remove bespoke stat-card / dashboard-section chrome
- All widgets: .card.dash-widget (reuse cards.css tokens)
- Core widgets (implement as PHP registry + endpoints):
  W-CORE-01 Quick actions (new entry, media, Studio, settings)
  W-CORE-02 Content snapshot (replaces 4 stat cards)
  W-CORE-03 Recent entries
  W-CORE-04 Recent activity (restyle existing JS)
  W-CORE-05 Drafts needing attention (unpublished >7d)
  W-CORE-06 System status (honest storage — drop fake 10GB bar)
  W-CORE-07 Security & access (2FA grace, pending invites)
  W-CORE-08 Updates (CMS + plugin updates)
  W-ADV-01 Analytics mini (replace #analytics-section + inline styles)

Plugin widgets (M9–M11):
- commerce: default enable_dashboard_widget TRUE for admin (P31-D10)
- events: upcoming events + RSVP summary endpoint
- studio: unpublished pages / last publish status
- automation: failed runs / queue depth

Layout (P31-D2):
- Grid: 3-col → 2-col @ --bp-xl → 1-col @ --bp-md
- Quick actions = full width row at top

═══ PILLAR C — RESPONSIVE SHELL (M1–M2, M13–M15) ═══

Required (P31-D1, D3, D4):
- variables.css: --bp-sm 640, --bp-md 768, --bp-lg 1024, --bp-xl 1280, --bp-2xl 1440
- main-content.css: padding scales at lg/md
- sidebar: suggest collapsed below xl (banner or one-time hint; respect localStorage)
- tables.css: entry list laptop columns
- Editor 1280px pass (P30 LAY-01 carryover)

OUT OF SCOPE: drag-and-drop widget reorder (P31-D13)

═══ MILESTONES ═══

M0:  P30 closure docs + P31 gap/matrix
M1:  Breakpoint tokens
M2:  Responsive utility migration (key files)
M3:  Widget API v2 PHP (core + api/dashboard.php)
M4:  dashboard-widgets.js mount system
M5:  dashboard.html widget grid
M6:  dashboard.css → .card.dash-widget
M7:  Core widgets 01–04
M8:  Core widgets 05–08
M9:  Commerce widget v2 + default on
M10: Events widget
M11: Studio + Automation widgets
M12: Analytics mini widget
M13: Main padding + sidebar hint
M14: Table laptop breakpoints
M15: Editor 1280px pass
M16: admin-polish-smoke + dashboard_widgets_test extend
M17: Birkly-Docs dashboard widgets guide
M18: Matrix green + single notification

DELEGATION:
cms-developer:      M0–M16
frontend-developer: M5–M6, M12 (dashboard UI polish)
design-reviewer:    M5–M12 (widget grid + card consistency)
docs-writer:        M17
qa-verifier:        M16, M18 matrix

DO NOT:
- Start P28 or P26
- Add eval() for widgets
- Add drag-and-drop personalization
- Change marketplace scope

CLOSURE:
[ ] Dashboard uses unified .card.dash-widget styling
[ ] Widget API v2 — no eval, Commerce+Events+Studio+Automation widgets
[ ] Core widgets: quick actions, snapshot, drafts, analytics mini, updates
[ ] Responsive 3→2→1 dashboard grid + breakpoint tokens
[ ] P30 matrix green, P31 matrix green
[ ] admin-polish-smoke + tests green
[ ] Single digest at M18
```

---

## Human approval checklist

- [x] P31 widget-first dashboard approved (2026-07-21)
- [x] Widget API v2 (no eval) approved (2026-07-21)
- [x] Commerce widget default on for admin approved (2026-07-21)
- [x] Plugin widgets: Commerce, Events, Studio, Automation approved (2026-07-21)
- [x] Advanced widgets: Analytics mini, Drafts, Updates approved (2026-07-21)
- [x] Fixed layout (no drag-and-drop) approved (2026-07-21)
- [x] Pause P28 until P31-M18 (2026-07-21)
- [x] M0–M18 single-wave execution approved (2026-07-21)
