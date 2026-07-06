# Phase 7 — Plugin platform, UX unification & ecosystem

**Status:** Proposed (2026-07-06) — pending human approval  
**Repo:** Birkly (primary) · Birkly-Docs  
**Baseline:** P6.4–P6.7 complete on `main` (Commerce v2, bundles, import, 91 tests) · **docs_shelf shipped** — only Commerce registers docs today  
**Trigger:** Live feedback — wrong plugin breadcrumbs, User plugin broken, AI still lacks Commerce in chat (deploy/topic gap), legacy plugins in sidebar

---

## Current status (verified 2026-07-06)

| Item | State |
|------|--------|
| **P6.5–P6.7 Commerce** | **Shipped** — fieldtype v2 (`fulfillment` + `purchase_options[]`), variants, PostPurchaseRouter, bundles, import profiles |
| **docs_shelf + register_plugin_docs** | **Core shipped** — Commerce registers; Automation, User, Events **do not** |
| **Help plugin section** | **Partial** — help.js supports sections; only Commerce topics appear |
| **payments + shopping_cart** | Still in repo + sidebar — remove in P7 |
| **Plugin breadcrumbs** | Still wrong — `Settings › Commerce` (see screenshot) |
| **User Management** | **Broken on live** — tabs, no content |
| **Events** | Legacy list UI — no event fieldtype / calendar |
| **Automation** | Form editor — no node canvas |
| **Tests** | 91 suites green |

---

## Phase goal

One **plugin platform layer** in core + unified admin experience across all plugins, remove legacy commerce plugins, fix User Management, evolve Events & Automation.

**P6.5 Commerce Wave is complete** — P7 does not re-implement commerce fieldtype/checkout.

**Design reference:** `admin/templates/entries.html`, `entry-editor.html`, `content.html` — breadcrumbs only, `main-actions-bar`, smart-table patterns, no duplicate titles.

---

## Architecture

```mermaid
flowchart TB
  subgraph core [Core — P7-M0]
    SHELL[Plugin admin shell partial]
    BC[register_plugin_breadcrumb API]
    DOCS[register_plugin_docs shelf]
  end

  subgraph plugins [Plugins migrate]
    COM[commerce]
    AUTO[automation]
    USER[user_management]
    EVT[events]
  end

  subgraph remove [Remove]
    PAY[payments shim]
    CART[shopping_cart]
  end

  SHELL --> COM & AUTO & USER & EVT
  DOCS --> Help & AI & Birkly-Docs
  COM --> PAY & CART
```

---

## Locked decisions (proposed)

| ID | Decision |
|----|----------|
| P1 | Plugin breadcrumb root = **plugin name**, never Settings or Dashboard |
| P2 | Sub-pages: `Commerce › Orders`, `Site Users › Tiers` — max depth 3 |
| P3 | **No duplicate `<h1>`** in plugin body — topbar breadcrumb is the title |
| P4 | Remove horizontal padding mismatch — plugin pages use same content width as entries |
| P5 | **Remove** `plugins/payments/` and `plugins/shopping_cart/` after migration + test pass |
| P6 | `register_plugin_docs()` — **all active plugins** ship Help + AI docs before phase closure |
| P7 | Events: **event fieldtype** + indexer (parallel to Commerce catalog pattern) |
| P8 | Automation node editor — **new UI**; keep form editor as fallback export/import |
| P9 | P6.5 **complete** — P7 migrates Commerce **admin UI only** (shell), no commerce logic redo |

---

## P7-M0 — Plugin admin shell (core)

**Goal:** One layout contract for every plugin admin page.

| Task | Deliverable |
|------|-------------|
| M0-T1 | `admin/partials/plugin_page_shell.php` or documented HTML contract matching entries layout |
| M0-T2 | `register_plugin_admin_meta($plugin_id, ['title', 'tabs', 'breadcrumb_root'])` in `core/plugins.php` |
| M0-T3 | Breadcrumb hoisting: plugin root from meta; sub-tabs update breadcrumb segment via JS |
| M0-T4 | CSS: `.plugin-page` uses content-area padding; drop redundant `.page-header` side padding |
| M0-T5 | `docs/PLUGIN_ADMIN_UI_GUIDE.md` — mandatory for plugin authors |
| M0-T6 | `tests/plugin_admin_shell_test.php` |

**Breadcrumb examples after M0:**

| Page | Breadcrumb |
|------|------------|
| Commerce → Connections | `Commerce` |
| Commerce → Orders tab | `Commerce › Orders` |
| Site Users → Tiers | `Site Users › Tiers` |
| Automation | `Automation` |
| Events → Calendar | `Events › Calendar` |

---

## P7-M1 — Plugin docs shelf (fixes AI gap)

**Goal:** Connected AI and Help know every active plugin (screenshot issue).

| Task | Deliverable |
|------|-------------|
| M1-T1 | `core/plugins/docs_shelf.php` + `register_plugin_docs()` |
| M1-T2 | `api/help_manifest.php` — merge core + plugin topics |
| M1-T3 | `help.js` — **Platform** vs **Plugin extensions** nav sections |
| M1-T4 | Each plugin ships `docs/ai-context.md` + `docs/help-*.md` |
| M1-T5 | `generateSystemContext()` + MCP dynamic topics |
| M1-T6 | `tests/plugin_docs_shelf_test.php` |

**Plugins required to register in M1:** automation, user_management, events (commerce **done**); verify AI topic `commerce` resolves on live

---

## P7-M2 — Remove legacy commerce plugins

| Task | Deliverable |
|------|-------------|
| M2-T1 | Audit `payments` shim — ensure 100% forward to commerce |
| M2-T2 | Migrate any `settings/plugins.json` state; activation UI shows Commerce only |
| M2-T3 | Remove `plugins/payments/` folder (or empty stub with deactivate message one release — prefer clean remove) |
| M2-T4 | Remove `plugins/shopping_cart/` — cart lives in Commerce |
| M2-T5 | Update tests: delete `shopping_cart_*` or redirect to commerce; shelf alias tests remain |
| M2-T6 | Sidebar nav: drop Payments, Shopping Cart entries |
| M2-T7 | Birkly-Docs: legacy plugins deprecated notice |

**Acceptance:** `php tests/run.php` green; no active site requires shopping_cart or payments plugin id.

---

## P7-M3 — User Management repair & shell migration

**Goal:** Fix broken Site Users page; align with platform UX.

| Task | Deliverable |
|------|-------------|
| M3-T1 | **Root cause** — CSS not loaded / tab pane hidden / JS init on hoisted breadcrumb layout |
| M3-T2 | Split `site_users.html` (2000+ lines) into partials or tab lazy-load |
| M3-T3 | Migrate to plugin shell; breadcrumb `Site Users › {tab}` |
| M3-T4 | Remove inline styles; use `btn`, `main-actions-bar`, `smart-table` |
| M3-T5 | Goals review doc: registration, tiers, collection permissions still match P3/P5 values |
| M3-T6 | `tests/user_plugin_admin_test.php` — HTTP smoke tab content renders |

---

## P7-M4 — Plugin UI migration (Commerce, Automation, Events, User)

| Task | Deliverable |
|------|-------------|
| M4-T1 | Commerce admin → plugin shell; remove Settings breadcrumb + duplicate h1 |
| M4-T2 | Automation admin → plugin shell |
| M4-T3 | Events admin → plugin shell |
| M4-T4 | User Management → plugin shell (coordinate with M3) |
| M4-T5 | Shared tab component (match entries/content tab styling) |
| M4-T6 | design-reviewer pass on all plugin pages |

---

## P7-M5 — Events plugin v2

**Goal:** Content-first events with fieldtype + plugin dashboard (not separate events collection only).

### Event fieldtype

Register `event` fieldtype (Events plugin) on entries in **any** collection:

| Field group | Purpose |
|-------------|---------|
| Schedule | `starts_at`, `ends_at`, timezone, recurrence optional (defer) |
| Location | `venue`, `virtual_url`, hybrid flag |
| Capacity | `quantity_available` or link to Commerce `fulfillment: register` |
| Commerce link | Optional relation to commerce field on same entry OR paid via Commerce field on entry |

**Indexer:** `EventIndexer` — all entries with event fieldtype → `plugins/events/data/events_index.json`

### Events admin (plugin page)

| Tab | Feature |
|-----|---------|
| **Overview** | Upcoming/past counts, revenue from Commerce orders (filter register) |
| **Calendar** | Month/week view from index |
| **Events list** | Filterable table → drill to entry editor |
| **Attendees** | Registrations from Commerce `EventRegistrationStore` (P6.5) + legacy events plugin data |
| **Messages** | Bulk email / attendee notes (v1: export + Automation trigger) |

### Commerce connection

| Mode | Behavior |
|------|----------|
| **Free RSVP** | Event fieldtype only; register without Commerce |
| **Paid ticket** | Same entry has Commerce field `fulfillment: register` — Commerce owns money |
| **Legacy** | Old `events` collection + Events plugin registrations — migration wizard (M6-T7) |

### What else makes sense (included / deferred)

| Feature | Phase |
|---------|--------|
| Check-in QR / door list | P7-M6 v1 export |
| Waitlist when full | P7-M6 v1 |
| iCal feed per event | P7-M6 |
| Recurring events series | Deferred |
| Seating maps | Deferred |
| Event-specific email templates | Automation recipes |
| Public calendar template functions | `upcoming_events` extend to index |

| Task | Deliverable |
|------|-------------|
| M5-T1 | Event fieldtype + shelf registration |
| M5-T2 | EventIndexer + admin Calendar/Overview |
| M5-T3 | Attendees tab (Commerce + legacy merge view) |
| M5-T4 | Attendee messages → Automation trigger `events_message_attendees` |
| M5-T5 | Template functions update |
| M5-T6 | Tests + plugin docs |
| M5-T7 | Coexistence + migration note from legacy events collection |

---

## P7-M6 — Automation node editor

**Goal:** Visual node-based workflow editor (comprehensive, not replacement of runtime engine).

| Task | Deliverable |
|------|-------------|
| M6-T1 | Canvas UI (SVG or DOM nodes) — triggers, conditions, actions as nodes |
| M6-T2 | Edge connections; validate acyclic flow |
| M6-T3 | Serialize to existing automation JSON schema (backward compatible) |
| M6-T4 | Import/export; form view toggle for power users |
| M6-T5 | Node palette: Commerce triggers, Events triggers, User triggers, webhooks |
| M6-T6 | Tests + docs |

**Lean guardrail:** Runtime engine unchanged — editor is a new view over same JSON.

**Scope note:** M6 is the largest milestone — may split to P7.5 if needed; minimum viable: linear node chain before branching.

---

## Execution order (recommended)

```text
P7-M0 Shell ──┬── P7-M1 Docs shelf
              ├── P7-M3 User fix (parallel)
              └── P7-M4 UI migration
P7-M2 Remove legacy (after Commerce regression pass)
P7-M5 Events v2
P7-M6 Automation nodes
```

**Human notify:** once at P7 closure (after M6 or agreed subset).

---

## Out of scope (P7)

- Birkly Marketplace
- Full tax/refund/subscription lifecycle (P6.5/P6.11 backlog)
- Removing Events plugin entirely (evolve, don't delete in P7)
- Third-party plugin marketplace UI

---

## Success criteria

1. All plugin pages: breadcrumb root = plugin name; no Settings parent; no duplicate h1.
2. AI chat answers Commerce questions via `register_plugin_docs` without `list_plugins` guesswork.
3. Help → Plugin extensions lists Commerce, Automation, User Management, Events.
4. payments + shopping_cart removed; tests green.
5. Site Users tabs render and save.
6. Events calendar + attendees from indexed fieldtype entries.
7. Automation node editor saves workflows readable by existing engine.

---

## Relationship to existing docs

| Doc | Role |
|-----|------|
| `P6.5-lead-engineer-handoff.md` | Commerce Wave — **complete on main** |
| `P6-commerce-scenarios-and-model.md` | Commerce content model |
| `P6.5-commerce-polish-ai-plan.md` | Docs shelf spec — **partial (Commerce only)** |
| `PLUGIN_CSS_GUIDE.md` | Superseded in part by **PLUGIN_ADMIN_UI_GUIDE** |

---

## Handoff

When approved: `docs/reviews/P7-lead-engineer-handoff.md`  
Branches: `cursor/p7-m[N]-<slug>-24b5`
