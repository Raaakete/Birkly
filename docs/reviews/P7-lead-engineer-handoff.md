# Phase 7 — Plugin platform & ecosystem — lead-engineer handoff

**Status:** Approved (2026-07-06, Keven) — autonomous execution P7-M0→M6  
**Baseline:** P6.4–P6.7 complete on `main` (Commerce v2, 91+ test suites)  
**Human rule:** Execute **P7-M0 → P7-M6** autonomously. Notify human **once** at **P7-M6 closure**.

**Product spec:** `docs/reviews/P7-plugin-platform-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 7  
**Live site:** `https://birkly.kekaba.com` — human deploys via `git pull`.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 7 — Plugin platform, UX unification & ecosystem.

Human approved full P7 autonomous execution (M0→M6). Notify human ONCE at P7-M6 closure only.

Read first:
- docs/reviews/P7-lead-engineer-handoff.md (this file)
- docs/reviews/P7-plugin-platform-plan.md
- docs/roadmap/ROADMAP.md § Phase 7
- admin/templates/entries.html (layout reference)
- core/plugins/docs_shelf.php (exists — extend usage)
- plugins/commerce/admin/commerce.html (migrate away from Settings breadcrumb)
- plugins/user_management/admin/site_users.html (broken — fix M3)

## Context

P6.5–P6.7 Commerce is COMPLETE on main (fieldtype v2, fulfillment, variants, PostPurchaseRouter,
bundles, register_plugin_docs for Commerce only). P7 does NOT re-implement commerce logic.

Live issues (human screenshots):
- Plugin breadcrumbs wrong: "Settings › Commerce" — must be plugin-root only
- Duplicate plugin h1 titles; extra header padding vs content/entries pages
- User Management (Site Users) broken — tabs visible, content empty
- AI chat doesn't know Commerce on live — ensure docs shelf + MCP topic `commerce` work; register
  docs for automation, user_management, events
- payments + shopping_cart plugins still in sidebar — REMOVE

## Locked decisions (do not re-ask human)

### Breadcrumbs — tabs vs sub-pages (Keven 2026-07-06)

- **Tabs do NOT change breadcrumb.** In-tab navigation (Commerce Connections/Catalog/Orders tabs,
  Site Users General/User List tabs) keeps breadcrumb at plugin title only: `Commerce`, `Site Users`.
- **Sub-pages ONLY** extend breadcrumb — separate admin routes (e.g. `?page=user_tiers`):
  `Site Users › Tiers`, `Site Users › Groups`.
- Plugin root = plugin display name. Never Settings, never Dashboard as parent.
- No duplicate `<h1>` in plugin body — topbar breadcrumb is the title.
- Same content width / padding as entries.html (no extra page-header horizontal padding).

### Plugin platform

- M0: plugin admin shell + register_plugin_admin_meta() in core
- M1: register_plugin_docs() for automation, user_management, events (commerce done); Help
  "Plugin extensions" section; AI L1 summaries + MCP topics
- M2: DELETE plugins/payments/ and plugins/shopping_cart/ after migration + tests green
- M4: All four plugins use shell — commerce, automation, events, user_management

### Events v2 (Keven — recurring + calendar sync from day one)

- Event fieldtype on fieldtype shelf (Events plugin)
- EventIndexer → calendar, overview, list
- **Recurring events IN SCOPE v1** — not deferred. Support series (e.g. weekly yoga):
  recurrence rule on fieldtype (RRULE subset or structured: frequency, interval, until/count)
  Indexer expands occurrences for calendar views
- **Calendar export/sync IN SCOPE v1:**
  - Per-event and calendar-wide iCal (.ics) feed URLs
  - Subscribe link for external calendars (Google/Apple via webcal://)
  - Optional: export attendee list per occurrence
- Attendees: Commerce EventRegistrationStore + legacy events data
- Attendee messages: Automation trigger events_message_attendees
- Commerce: paid = commerce fulfillment register on same entry; free = event fieldtype RSVP
- Do NOT delete Events plugin — evolve it

### Automation M6

- Node/canvas editor over EXISTING automation JSON — runtime unchanged
- Form editor remains as fallback toggle
- Palette: Commerce, Events, User, webhook blocks

### Out of scope P7

- Marketplace, tax/refund/subscription lifecycle UI
- Seating maps
- Re-implementing Commerce checkout/fieldtype

## Deliverables

### P7-M0 — Plugin admin shell
- admin/partials/plugin_page_shell.php or documented contract
- register_plugin_admin_meta() — title, optional sub_pages (not tabs)
- Breadcrumb: plugin name; sub-pages append segment; tabs unchanged
- docs/PLUGIN_ADMIN_UI_GUIDE.md
- tests/plugin_admin_shell_test.php

### P7-M1 — Plugin docs (all plugins)
- automation, user_management, events: docs/ai-context.md + help-*.md + register_plugin_docs()
- Verify read_birkly_docs topic commerce + automation + user_management + events
- help.js Platform vs Plugin extensions (commerce already works)

### P7-M2 — Remove legacy plugins
- Remove plugins/payments/, plugins/shopping_cart/
- Sidebar, settings migration, tests updated
- Birkly-Docs deprecation note

### P7-M3 — User Management repair
- Fix broken Site Users (root cause: CSS/JS/layout)
- Split site_users.html; smart-table; shell migration
- Breadcrumb: `Site Users` on main page; `Site Users › Tiers` on tiers.html sub-page only
- tests/user_plugin_admin_test.php

### P7-M4 — Plugin UI migration
- Commerce, Automation, Events, User → shell; remove Settings breadcrumb + duplicate h1
- Shared tab styling matching core admin
- design-reviewer pass

### P7-M5 — Events v2
- Event fieldtype + EventIndexer
- Admin: Overview, Calendar (month/week), Events list, Attendees, Messages tabs
- Recurring series on fieldtype + indexer occurrence expansion
- iCal export + webcal subscribe (per event + calendar feed)
- Template functions (extend upcoming_events)
- register_plugin_docs for events
- tests/events_v2_test.php

### P7-M6 — Automation node editor + closure
- Canvas editor, serialize to existing schema
- docs/reviews/P7-verification-matrix.md
- php tests/run.php green
- Roadmap P7 complete; daily report; notify human ONCE

## Execution order

M0 → M1 (parallel M3 start) → M2 → M3/M4 → M5 → M6

Branches: cursor/p7-m[N]-<slug>-24b5

## Delegate

- cms-developer: M0–M5
- design-reviewer: shell, all plugin pages, Events calendar, Automation canvas
- docs-writer: PLUGIN_ADMIN_UI_GUIDE, Birkly-Docs plugin pages, verification matrix
- qa-verifier: User plugin smoke, docs shelf AI topics, legacy plugin removal regression

Run php tests/run.php before each PR merge. Merge PRs yourself.
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Plugin admin shell | Breadcrumb `Commerce` on all Commerce tabs; `Site Users › Tiers` only on tiers sub-page |
| **M1** | All plugins document | AI topic + Help for automation, user, events, commerce |
| **M2** | Legacy removed | No payments/shopping_cart in repo or sidebar |
| **M3** | User plugin fixed | Tab panes render; save works |
| **M4** | UI unified | Matches entries layout; no Settings breadcrumb |
| **M5** | Events v2 | Recurring + iCal/webcal; calendar; attendees |
| **M6** | Automation nodes + closure | Matrix green; human notified once |

---

## Verification matrix (summary)

| Flow | Target |
|------|--------|
| Commerce tabs → breadcrumb stays "Commerce" | pass |
| user_tiers sub-page → "Site Users › Tiers" | pass |
| All plugins in Help Plugin extensions + AI topics | pass |
| payments + shopping_cart removed | pass |
| Site Users content renders | pass |
| Recurring event appears on calendar | pass |
| iCal feed validates | pass |
| Automation node editor saves + runs | pass |
| php tests/run.php green | pass |

---

## Branch naming

`cursor/p7-m[N]-<slug>-24b5`
