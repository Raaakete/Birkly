# P31 — Admin responsive system & dashboard excellence

**Status:** Approved (2026-07-21, Keven) — execute now  
**Baseline:** P30 complete on `main` · P28 **paused** until P31-M18 · P26 **paused**  
**Human rule:** Execute P31-M0→M18 autonomously. Notify human **once** at **P31-M18**.

**Gap register:** `docs/reviews/P31-gap-register.md`  
**Handoff:** `docs/reviews/P31-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P31-verification-matrix.md`

---

## Vision

Make the admin feel intentional at **every viewport** (mobile → laptop → wide desktop), with the **dashboard** rebuilt as a **widget-first** surface that matches the rest of the admin design system (P27/P30).

Human-reported issues:

1. **Dashboard styling** does not match unified `.card` / admin chrome elsewhere.
2. **Widgets do not work well** — plugin section hidden, Commerce opt-in off by default, `eval()` for JS, inline-styled analytics.
3. **Responsive gaps** at laptop widths (1024–1280px) — dashboard 3-column grid, inconsistent breakpoints.
4. **Need plugin widgets** and richer core widgets for daily admin use.

**Scope:** `admin/css/`, `admin/js/`, `admin/templates/`, `api/dashboard.php`, `core/dashboard_widgets.php`, active plugins (commerce, events, studio, automation), tests, Birkly-Docs.

**Out of scope:** P28 journey runner, P26 Studio creative features, marketplace commerce, kekaba live-path, drag-and-drop widget personalization (defer to P31+).

---

## Locked decisions (approved 2026-07-21)

| ID | Decision |
|----|----------|
| **P31-D1** | **Canonical breakpoints** in `variables.css`: `--bp-sm 640`, `--bp-md 768`, `--bp-lg 1024`, `--bp-xl 1280`, `--bp-2xl 1440` — migrate admin CSS incrementally |
| **P31-D2** | **Dashboard grid progression:** 3-col → 2-col at `xl` → 1-col at `md` |
| **P31-D3** | **Laptop sidebar:** suggest collapsed sidebar below `xl` when viewport tight; user `localStorage` preference still wins |
| **P31-D4** | **Main content padding** scales down at `lg` / `md` (not only 768/480) |
| **P31-D5** | **Dashboard IA:** quick actions above the fold; demote empty analytics; merge redundant stat cards into content snapshot |
| **P31-D6** | **Pause P28** until P31-M18 |
| **P31-D7** | **P30 formal closure** as P31-M0 (matrix + roadmap) — code already on `main` (#178–#181) |
| **P31-D8** | **Widget API v2** — `size`, `priority`, `endpoint`, named `mount` (no `eval()`) |
| **P31-D9** | All widgets render as `.card.dash-widget` using shared design tokens |
| **P31-D10** | **Commerce dashboard widget enabled by default** for `admin` role (still configurable in Commerce settings) |
| **P31-D11** | **Plugin widgets v1:** Commerce, Events, Studio, Automation |
| **P31-D12** | **Advanced core widgets v1:** Analytics mini, Drafts needing attention, CMS/plugin Updates |
| **P31-D13** | **Fixed layout** for v1 — no drag-and-drop personalization |
| **P31-D14** | Single notification at **P31-M18** |

---

## Widget API v2 (canonical)

Plugins register via `dashboard_widgets_callback` on `$birkly_plugins` or `haru_hooks['dashboard_widgets']`.

```php
[
  'id' => 'events-upcoming',           // unique per plugin
  'title' => 'Upcoming events',
  'plugin_id' => 'events',             // set by core if omitted
  'size' => 'md',                      // sm | md | lg | full
  'priority' => 50,                    // lower = higher on page
  'roles' => ['admin', 'editor'],      // optional; filtered by core
  'enabled' => true,                   // plugin setting may override
  'refresh' => 60,                     // optional seconds; 0 = no auto-refresh
  'endpoint' => '/api/index.php?route=events&action=dashboard_summary', // preferred
  'mount' => 'BirklyDashboardWidgets.events', // optional named JS renderer
  // Legacy (discouraged):
  'html' => '<div>...</div>',
]
```

**Rendering rules:**

1. Core loads widgets from `api/dashboard.php?action=widgets` + built-in core widget registry.
2. `Birkly.Dashboard.mountWidget(el, widget, data)` handles endpoint fetch + mount — **no `eval()`**.
3. Widget grid uses CSS grid with `size` column spans; all widgets use `.card.dash-widget`.
4. Remove separate hidden `#analytics-section` — analytics becomes a widget.

---

## Widget catalog

### Tier 1 — Core (platform)

| ID | Widget | Notes |
|----|--------|-------|
| W-CORE-01 | Quick actions | New entry, upload media, open Studio, settings |
| W-CORE-02 | Content snapshot | Collections / published / drafts — replaces four stat cards |
| W-CORE-03 | Recent entries | Last 5–8 with status + collection |
| W-CORE-04 | Recent activity | Existing filterable feed — restyle only |
| W-CORE-05 | Drafts needing attention | Unpublished >7d or stale drafts |
| W-CORE-06 | System status | Storage, cache, API health — honest metrics |
| W-CORE-07 | Security & access | 2FA grace, pending invites |
| W-CORE-08 | Updates | CMS + plugin updates available |

### Tier 2 — Plugin (when active)

| Plugin | Widget ID | Content |
|--------|-----------|---------|
| commerce | `commerce-stats` | Revenue 7d, orders, link to commerce admin |
| events | `events-upcoming` | Next events, RSVP counts |
| studio | `studio-publish` | Unpublished pages, last publish status |
| automation | `automation-runs` | Failed runs, queue depth |

### Tier 3 — Advanced core (v1)

| ID | Widget |
|----|--------|
| W-ADV-01 | Analytics mini — sparkline or 7d/30d summary when domains + tracking enabled; setup CTA otherwise |
| W-ADV-02 | Drafts needing attention (listed above) |
| W-ADV-03 | Updates available (listed above) |

---

## Layout concept

```
┌─────────────────────────────────────────────────────────┐
│  Quick actions (full)                                     │
├──────────────────────┬──────────────────────────────────┤
│  Content snapshot    │  System status + Security          │
├──────────────────────┼──────────────────┬───────────────┤
│  Recent entries      │  Recent activity │ Plugin widgets│
├──────────────────────┴──────────────────┴───────────────┤
│  Analytics mini | Drafts | Updates (when applicable)    │
└─────────────────────────────────────────────────────────┘
```

---

## Milestones (18 — one wave)

| ID | Focus |
|----|--------|
| **P31-M0** | P30 closure docs + P31 gap register + matrix shell |
| **P31-M1** | Breakpoint tokens in `variables.css` |
| **P31-M2** | Responsive utilities / migrate key admin breakpoints |
| **P31-M3** | Widget API v2 spec in `core/dashboard_widgets.php` + `api/dashboard.php` |
| **P31-M4** | `admin/js/dashboard-widgets.js` — `Birkly.Dashboard` mount, no eval |
| **P31-M5** | Dashboard template rebuild — single widget grid |
| **P31-M6** | Dashboard CSS on `.card.dash-widget` — remove bespoke stat-card chrome |
| **P31-M7** | Core widgets W-CORE-01–04 (quick actions, snapshot, entries, activity) |
| **P31-M8** | Core widgets W-CORE-05–08 (drafts, system, security, updates) |
| **P31-M9** | Commerce widget — default on for admin, API v2, restyle |
| **P31-M10** | Events plugin dashboard widget + summary endpoint |
| **P31-M11** | Studio + Automation plugin dashboard widgets |
| **P31-M12** | Analytics mini widget (remove inline styles / orphaned section) |
| **P31-M13** | Responsive shell — main padding, sidebar collapse hint |
| **P31-M14** | Entry/settings table laptop breakpoints |
| **P31-M15** | Editor layout 1280px final pass (P30 LAY-01 carryover) |
| **P31-M16** | `admin-polish-smoke.sh` + `tests/dashboard_widgets_test.php` extend |
| **P31-M17** | Birkly-Docs dashboard widgets guide + `ux-flow-matrix` |
| **P31-M18** | Matrix green, closure, ROADMAP, single notification |

**Branches:** `cursor/p31-m[N]-<slug>-030a`

---

## Files (primary)

| Area | Files |
|------|--------|
| Widget core | `core/dashboard_widgets.php`, `api/dashboard.php`, `admin/js/dashboard-widgets.js`, `admin/js/dashboard.js` |
| Dashboard UI | `admin/templates/dashboard.html`, `admin/css/pages/dashboard.css` |
| Design system | `admin/css/core/variables.css`, `admin/css/components/cards.css` |
| Layout shell | `admin/css/layout/main-content.css`, `sidebar.css`, `header.css` |
| Plugins | `plugins/commerce/init.php`, `plugins/events/`, `plugins/studio/`, `plugins/automation/` |
| Tests | `tests/dashboard_widgets_test.php`, `scripts/admin-polish-smoke.sh` |

---

## After P31

Resume **P28** beta launch automation (journeys + videos). Operations widget hooks naturally into P28-M14.
