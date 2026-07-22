# Phase 33 — Lead-engineer handoff (Admin responsive refinement & UX polish)

**Status:** Approved (2026-07-22, Keven) — execute now  
**Human rule:** Execute **P33-M0→M20** autonomously. Notify human **once** at **P33-M20**.

**Master plan:** `docs/reviews/P33-admin-responsive-refinement-plan.md`  
**Gap register:** `docs/reviews/P33-gap-register.md`  
**Matrix:** `docs/reviews/P33-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 33 — Admin responsive refinement & UX polish.

Human approved P33 (2026-07-22). Execute P33-M0→M20 autonomously in one wave.
Notify human ONCE at P33-M20.

PAUSED: P28 (beta journeys), P26 (Studio creative). Do NOT start those.

LOCKED HUMAN DECISIONS:
- Sidebar scrolls WITH PAGE (no inner scrollbar on .sidebar-body)
- Tablet drawer breakpoint = 1280px (--bp-xl), NOT 768px
- Entry table long text fields: TRUNCATE with ellipsis, do NOT hide columns

READ FIRST:
1. docs/reviews/P33-admin-responsive-refinement-plan.md
2. docs/reviews/P33-gap-register.md
3. docs/reviews/P33-verification-matrix.md
4. admin/css/components/tables.css  ← .fields-region width: fit-content BUG
5. admin/css/components/tabs.css  ← link cloud problem
6. admin/templates/account.html  ← legacy layout
7. admin/css/layout/sidebar.css, admin/js/theme.js
8. admin/css/layout/header.css  ← sidebar-toggle breakpoint
9. lang/en.json + core/i18n.php

═══ PILLAR A — ENTRY TABLE FIX (M1–M2) CRITICAL ═══

BUG: .fields-region { width: fit-content; flex-shrink: 0 } forces full content
width. User must scroll entire window to reach edit/delete icons.

FIX (P33-D5–D6):
- .fields-region: flex 1 1 0; min-width 0; overflow hidden
- .cell-field: text-overflow ellipsis; white-space nowrap; overflow hidden
- content/excerpt/long text columns STAY VISIBLE but truncated — NOT hidden
- .table-viewport: horizontal scroll if needed (NOT whole page)
- .cell-actions: flex-shrink 0; sticky right; always visible in viewport
- M2: deduplicate title column when title also appears as field column

Test: tests/entries_table_layout_test.php

═══ PILLAR B — TAB SYSTEM V2 (M3–M4, M9–M10) ═══

Problem: Settings/commerce/plugins tabs wrap into unreadable link cloud on
1024–1279px laptop screens.

Build admin/css/components/tabs-v2.css + admin/js/tabs-v2.js:

≥1280px:     horizontal tab bar (no wrap)
1024–1279px: scrollable strip + fade edges + "More ▼" overflow menu
<1280px:     current-tab picker (select/dropdown) — NOT wrapped link cloud

Apply to: settings, commerce, plugins, help topics, entry-editor tabs,
collection-editor tabs, plugin admin pages.

═══ PILLAR C — TABLET + SIDEBAR (M6–M7) ═══

M6 — Drawer at 1280px (P33-D2):
- Change sidebar-toggle visibility from 768px to max-width: 1279px
- theme.js: matchMedia('(max-width: 1279px)') for drawer open/close
- Below 1280px: off-canvas sidebar + hamburger in topbar
- At 1280px+: current desktop sidebar (collapse toggle still works)

M7 — Sidebar scroll with page (P33-D3):
- REMOVE overflow-y: auto from .sidebar-body
- Sidebar: position sticky; top 0; height auto
- Page scrolls as one unit — no scrollbar on sidebar/content seam

═══ PILLAR D — ACCOUNT + SETTINGS (M4–M5, M14) ═══

M5 — Account settings (P33-D7):
- Migrate account.html to .settings-stack + .card (match settings.html)
- ONE sticky save bar (reuse sticky-save partial from P32)
- Remove inline style="" attributes
- i18n: Notification Preferences, Cloud Account, all hardcoded EN
- Password change: keep security flow but integrate with sticky bar UX

M4 — Settings responsive 1024–1279px:
- Tabs v2 on settings
- Users/roles tables: card stack or contained scroll without breaking layout

M14 — Trash link in entries main-actions-bar; redirects tab polish; users table

═══ PILLAR E — MEDIA + HELP (M8–M9, M11) ═══

M8 — Media lg/xl responsive:
- Below 1280px: collapsible folder sidebar or drawer
- Detail panel as bottom sheet or stacked below grid
- Toolbar wraps cleanly

M9 — Help page:
- Remove duplicate .help-page padding (main-content already pads)
- Topics nav uses tab v2 pattern
- Layout at laptop widths

M11 — Shell alignment: content top aligns with sidebar .nav-links first item

═══ PILLAR F — i18n (M12–M13) ═══

M12 — Audit lang/da,de,es,fr,hi,ja,zh.json vs en.json — add missing keys
M13 — User-menu language selector sync with account language preference

═══ PILLAR G — REGRESSION + POLISH (M15–M17) ═══

M15 — P32 regression sweep:
- Global search: keyboard / focus + result grouping
- Trash discoverability
- Field templates (removed P32 #190): re-evaluate fix or document deferral
- FAB (+) mobile overlap
- Sticky save edge cases on collection editor
- Plugin admin action bar consistency

M16 — Dark mode: tabs v2, sidebar drawer, media panels
M17 — admin-polish-smoke.sh + entries_table_layout_test.php

═══ MILESTONES ═══

M0:  Gap + matrix + audit
M1:  Smart-table truncation fix
M2:  Title column dedup
M3:  Tab v2 component
M4:  Settings tabs v2 + laptop responsive
M5:  Account redesign + sticky save
M6:  Tablet drawer @ 1280px
M7:  Sidebar scroll-with-page
M8:  Media lg/xl responsive
M9:  Help layout + topics v2
M10: Commerce/plugins/editor tabs v2
M11: Shell alignment
M12: i18n lang file audit
M13: User-menu language sync
M14: Settings sub-pages + trash link
M15: P32 regression sweep
M16: Dark mode pass
M17: Tests + smoke
M18: Birkly-Docs
M19: ux-flow-matrix
M20: Closure + single notification

DELEGATION:
cms-developer:      M1–M17
frontend-developer: M3–M11, M16 (UI)
design-reviewer:    M1–M16
docs-writer:        M18
qa-verifier:        M17, M20

DO NOT:
- Start P28 or P26
- Hide entry table columns by default (truncate only)
- Use 768px as tablet drawer breakpoint (use 1280px)
- Re-add sidebar inner scrollbar

CLOSURE:
[ ] Entry table truncates long fields; actions always reachable
[ ] Tabs v2 on all major admin tab surfaces
[ ] Drawer @ 1280px; sidebar scrolls with page
[ ] Account matches settings; one sticky save
[ ] Media + settings responsive on laptop
[ ] i18n audit complete
[ ] All gap register items addressed
[ ] Tests green; single digest at M20
```

---

## Human approval checklist

- [x] P33 full scope approved (2026-07-22)
- [x] Sidebar scroll with page (2026-07-22)
- [x] Tablet drawer breakpoint 1280px (2026-07-22)
- [x] Long text fields truncated not hidden (2026-07-22)
- [x] All additional fixes from planning review included (2026-07-22)
- [x] Pause P28 until P33-M20 (2026-07-22)
- [x] M0–M20 single-wave execution approved (2026-07-22)
