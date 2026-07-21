# P31 — Admin responsive system & dashboard excellence

**Status:** Approved (2026-07-21, Keven) — execution ready  
**Baseline:** P30 complete on `main` · P28 paused until P31-M18 · P26 paused  
**Human rule:** Execute P31-M0→M18 autonomously. Notify once at P31-M18.

**Gap register:** `docs/reviews/P31-gap-register.md`  
**Handoff:** `docs/reviews/P31-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P31-verification-matrix.md`

---

## Vision

Make the admin intentional at every viewport (mobile through wide desktop), with a widget-first dashboard that uses the shared admin card system.

**Scope:** `admin/css/`, `admin/js/`, `admin/templates/`, `api/dashboard.php`, `core/dashboard_widgets.php`, active plugins, tests, and Birkly-Docs.

**Out of scope:** P28 journey runner, P26 Studio creative features, marketplace commerce, kekaba live path, and dashboard drag-and-drop personalization.

## Locked decisions

| ID | Decision |
|----|----------|
| P31-D1 | Canonical breakpoints: `--bp-sm 640`, `--bp-md 768`, `--bp-lg 1024`, `--bp-xl 1280`, `--bp-2xl 1440`; migrate incrementally |
| P31-D2 | Dashboard grid progresses 3 columns → 2 at xl → 1 at md |
| P31-D3 | Below xl, suggest sidebar collapse when space is tight; an existing user preference still wins |
| P31-D4 | Main-content padding scales down at lg and md |
| P31-D5 | Quick actions stay above the fold; empty analytics is demoted; redundant stats merge into a content snapshot |
| P31-D6 | P28 remains paused until P31-M18 |
| P31-D7 | P30 formal closure is P31-M0; its code is already on `main` (#178–#181) |
| P31-D8 | Widget API v2 uses `size`, `priority`, `endpoint`, and named `mount`; never `eval()` |
| P31-D9 | Every widget renders as `.card.dash-widget` with shared tokens |
| P31-D10 | Commerce dashboard widget is enabled by default for the `admin` role, while remaining configurable |
| P31-D11 | Plugin widgets v1: Commerce, Events, Studio, Automation |
| P31-D12 | Advanced core widgets v1: analytics mini, drafts needing attention, updates |
| P31-D13 | v1 uses a fixed layout; defer drag-and-drop personalization |
| P31-D14 | Send a single notification at P31-M18 |

## Widget API v2

Plugins register through `dashboard_widgets_callback` on `$birkly_plugins` or `haru_hooks['dashboard_widgets']`.

```php
[
  'id' => 'events-upcoming',
  'title' => 'Upcoming events',
  'plugin_id' => 'events',
  'size' => 'md',
  'priority' => 50,
  'roles' => ['admin', 'editor'],
  'enabled' => true,
  'refresh' => 60,
  'endpoint' => '/api/index.php?route=events&action=dashboard_summary',
  'mount' => 'BirklyDashboardWidgets.events',
]
```

Core loads widgets through `api/dashboard.php?action=widgets` with the built-in registry. `Birkly.Dashboard.mountWidget(el, widget, data)` fetches endpoint data and calls a named mount handler. Widgets use CSS grid spans and `.card.dash-widget`; analytics replaces the separate hidden analytics section.

## Widget catalog

| Tier | Widgets |
|------|---------|
| Core | Quick actions; content snapshot; recent entries; recent activity; stale drafts; system status; security and access; updates |
| Plugin | Commerce stats; upcoming Events; Studio publishing; Automation runs |
| Advanced core | Analytics mini; stale drafts; updates |

## Milestones

| ID | Focus |
|----|-------|
| P31-M0 | P30 closure docs + P31 gap register + matrix shell |
| P31-M1 | Breakpoint tokens in `variables.css` |
| P31-M2 | Responsive utilities and key breakpoint migration |
| P31-M3 | Widget API v2 in `core/dashboard_widgets.php` and `api/dashboard.php` |
| P31-M4 | `admin/js/dashboard-widgets.js`: `Birkly.Dashboard` mount, no `eval()` |
| P31-M5 | Dashboard template rebuilt as one widget grid |
| P31-M6 | `.card.dash-widget` dashboard CSS |
| P31-M7 | Core widgets: quick actions, snapshot, entries, activity |
| P31-M8 | Core widgets: drafts, system, security, updates |
| P31-M9 | Commerce widget v2, enabled by default for admin |
| P31-M10 | Events widget and summary endpoint |
| P31-M11 | Studio and Automation widgets |
| P31-M12 | Analytics mini; remove inline styling and orphaned section |
| P31-M13 | Responsive shell: content padding and sidebar hint |
| P31-M14 | Entry/settings-table laptop breakpoints |
| P31-M15 | Final editor layout pass at 1280px |
| P31-M16 | Extend `admin-polish-smoke.sh` and `tests/dashboard_widgets_test.php` |
| P31-M17 | Birkly-Docs dashboard widgets guide + `ux-flow-matrix` |
| P31-M18 | Matrix green, closure, ROADMAP, single notification |

**Branches:** `cursor/p31-m[N]-<slug>-030a`

## Primary files

| Area | Files |
|------|-------|
| Widget core | `core/dashboard_widgets.php`, `api/dashboard.php`, `admin/js/dashboard-widgets.js`, `admin/js/dashboard.js` |
| Dashboard UI | `admin/templates/dashboard.html`, `admin/css/pages/dashboard.css` |
| Design system | `admin/css/core/variables.css`, `admin/css/components/cards.css` |
| Layout shell | `admin/css/layout/main-content.css`, `sidebar.css`, `header.css` |
| Plugins | Commerce, Events, Studio, Automation |
| Tests | `tests/dashboard_widgets_test.php`, `scripts/admin-polish-smoke.sh` |

## After P31

Resume P28 beta-launch automation.
