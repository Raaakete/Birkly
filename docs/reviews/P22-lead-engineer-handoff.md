# Phase 22 — Lead-engineer handoff (Studio plugin)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P20 + P21 complete — `reference-projects/`, bundle v2, performance budgets, 141+ test suites  
**Human rule:** Execute **P22-M0→M12** autonomously after approval. Notify human **once** at **P22-M12** only.

**Master plan:** `docs/reviews/P22-studio-plugin-plan.md`  
**Gap register:** `docs/reviews/P22-studio-gap-register.md` (create at M0)  
**Matrix:** `docs/reviews/P22-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 22 — Studio plugin (visual site builder).

Human approved P22 (2026-07-08). Execute P22-M0→M12 autonomously in one wave.
Notify human ONCE at P22-M12 closure only (digest per docs/agent-ops/REPORTING.md).

═══════════════════════════════════════════════════════════════════════════════
READ FIRST (in order)
═══════════════════════════════════════════════════════════════════════════════

1. docs/reviews/P22-lead-engineer-handoff.md (this file)
2. docs/reviews/P22-studio-plugin-plan.md
3. docs/PROJECT_VALUES.md
4. docs/reviews/P20-js-php-parity-matrix.md
5. docs/reviews/P21-performance-excellence-plan.md
6. reference-projects/README.md
7. fieldtypes/ContentEditor/layout/ (reuse grid/flex patterns — do NOT move Studio into fieldtype)
8. plugins/commerce/ (plugin admin shell + plugin.json reference)
9. core/page_bundle_runner.php · api/page_bundle.php · birkly-client.js
10. docs/advanced/production-optimization.md (hydrate/bake OPTIONAL only)

═══════════════════════════════════════════════════════════════════════════════
MISSION
═══════════════════════════════════════════════════════════════════════════════

Ship plugins/studio — a deactivatable visual site builder inside Birkly.

PRIMARY USERS: BOTH
- Marketers: visual canvas, style kits, drag layout, bindings UI
- Developers: code tab with HTML + Birkly template tags; hand-edit compiled output

BIG BENEFIT: Projects live INSIDE the CMS at content/studio-projects/{id}/.
Immediately connected to collections. No external folder sync.
Export ZIP is supported; in-CMS copy is always source of truth.

PUBLIC SITES: SSR default (/site/{project-slug}/) for fast first paint.
ADMIN PREVIEW: live birkly-client.js using P21 bundle v2 path.

NOT Elementor: no proprietary runtime JSON on public pages, no theme-builder scope creep.
Compile to HTML + CSS + Birkly template tags.

═══════════════════════════════════════════════════════════════════════════════
LOCKED DECISIONS (P22-D1–D12) — DO NOT REOPEN WITHOUT HUMAN
═══════════════════════════════════════════════════════════════════════════════

P22-D1  Plugin-only — plugins/studio/; deactivatable
P22-D2  Storage — content/studio-projects/{project-id}/ (inside Birkly content tree)
P22-D3  Dual editor — visual canvas + code tab; round-trip without losing bindings
P22-D4  Canvas JSON = editor source; compiled HTML/CSS = publish artifact
P22-D5  Style kits = vendored static CSS — NO npm/webpack at runtime
P22-D6  v1 kits: minimal | bootstrap (vendored BS 5.3) | tailwind-lite (pre-built utilities)
P22-D7  SSR default for published Studio sites; live client for preview + optional export
P22-D8  Thin core shelf — register_public_route() + server-router.php hook (P22-M1)
P22-D9  Export ZIP — never deletes in-CMS project
P22-D10 Reuse ContentEditor layout patterns — Studio stays in plugins/studio/
P22-D11 SSR PHP renderer aligns with P20-js-php-parity-matrix; log gaps, no silent fail
P22-D12 Single human notification at P22-M12 only

═══════════════════════════════════════════════════════════════════════════════
STORAGE LAYOUT (canonical)
═══════════════════════════════════════════════════════════════════════════════

content/studio-projects/{project-id}/
├── project.json              # name, slug, style_kit, published, revision
├── styles/
│   ├── kit.css               # copied from style kit on create
│   └── custom.css            # rules from style editor
├── pages/
│   ├── index.studio.json     # canvas source
│   └── index.html            # compiled artifact
└── website/                  # export mirror
    ├── index.html
    └── styles.css

Public URL: /site/{project-slug}/{page-slug}/  (home: /site/{project-slug}/)

═══════════════════════════════════════════════════════════════════════════════
V1 FEATURE SCOPE (plugin capabilities — NOT demo content)
═══════════════════════════════════════════════════════════════════════════════

PROJECTS:     create, rename, duplicate, delete, dashboard list
PAGES:        add, reorder, delete, set home page
STYLE KITS:   pick on create — minimal, bootstrap, tailwind-lite
LAYOUT:       sections → rows → columns; grid + flex presets; element palette
              (text, image, button, link, spacer, divider, raw HTML)
STYLES:       inspector (type, spacing, color, border, display); class manager;
              custom CSS rules → styles/custom.css
BIRKLY:       insert collection loop, filtered list, single entry, site_elements,
              template function blocks
DUAL EDITOR:  visual ↔ code tab
PREVIEW:      admin iframe — birkly-client.js + data-cms-url (P21 bundle)
SSR:          StudioRenderer — page_bundle server-side + PHP template render
EXPORT:       ZIP with website/, styles, README + optional client script tag
PUBLISH:      sets published:true + revision bump (cache bust for P21 client)

OUT OF SCOPE v1: theme builder, popups, breakpoint-per-property editor, marketplace blocks,
                 realtime co-editing, replacing reference-projects/

═══════════════════════════════════════════════════════════════════════════════
MILESTONES — execute in order (parallel where noted)
═══════════════════════════════════════════════════════════════════════════════

P22-M0  Gap register (P22-studio-gap-register.md) + parity requirements for SSR
P22-M1  core/plugin_public_routes.php + server-router hook + plugins/studio shell
P22-M2  StudioProjectRepository — projects + pages CRUD
P22-M3  Style kits — vendored assets in plugins/studio/kits/
P22-M4  Visual layout editor (sections, grid, flex, elements)     } parallel after M3
P22-M5  Style & rules editor (inspector, custom.css)              }
P22-M6  Birkly bindings UI + StudioCompiler (JSON → HTML + template tags)
P22-M7  Dual editor — visual ↔ code round-trip
P22-M8  Live preview iframe (P21 client path)
P22-M9  SSR — StudioRenderer + /site/{slug}/ route                  } parallel after M6
P22-M10 Export ZIP + publish revision hook                          }
P22-M11 Docs (plugins/studio/README.md + Birkly-Docs) + V-STU-01 video
P22-M12 Matrix green, tests green, closure checklist, single notification

Branches: cursor/p22-m[N]-<slug>-030a
CI before merge: ./scripts/ci-check.sh · php tests/run.php

═══════════════════════════════════════════════════════════════════════════════
DELEGATION
═══════════════════════════════════════════════════════════════════════════════

cms-developer:       M0, M1, M2, M6, M9, M10 — PHP, SSR, storage, compiler, core shelf
frontend-developer:  M3, M4, M5, M7, M8 — canvas, inspector, preview iframe
design-reviewer:     after M4/M5 first UI pass — admin UX consistency
docs-writer:         M11 — Birkly-Docs 04-studio/getting-started.md
qa-verifier:         M12 — matrix, smoke, regression on P21 perf tests

═══════════════════════════════════════════════════════════════════════════════
CLOSURE CRITERIA (P22-M12) — ALL REQUIRED
═══════════════════════════════════════════════════════════════════════════════

[ ] docs/reviews/P22-verification-matrix.md — STU-01…STU-10, SK-01…SK-03, PR-01…PR-03 pass
[ ] tests/run.php green including studio_* test suites
[ ] End-to-end smoke (no terminal for site owner):
      Admin → create project (bootstrap kit) → 3-section layout → blog loop binding
      → live preview shows entries → publish SSR URL returns rendered HTML
      → export ZIP → deactivate plugin → SSR 503, files remain on disk
[ ] performance_budget_test.php still green (P21 regression)
[ ] Preview simple page: ≤2 cold RTTs (P21 budget)
[ ] V-STU-01 video recorded OR docs/reviews/artifacts/ evidence with reproducible steps
[ ] docs/reviews/P22-phase-closure-checklist.md complete
[ ] Values check in digest: dependency-free, UI decoupled, self-contained plugin
[ ] Human notified ONCE via digest (not per milestone)

═══════════════════════════════════════════════════════════════════════════════
ANTI-PATTERNS — STOP AND FIX INSTEAD
═══════════════════════════════════════════════════════════════════════════════

| Anti-pattern                              | Why                                      |
|-------------------------------------------|------------------------------------------|
| Studio code in core/ (beyond route shelf) | Violates self-contained plugin (P22-D1)  |
| npm/webpack in plugins/studio runtime     | Violates PROJECT_VALUES                    |
| Public site reads .studio.json at runtime | Violates P22-D4 — compile to HTML        |
| Elementor-style widget runtime on public  | Lock-in; use template tags               |
| Mandatory bake for Studio sites           | Violates P21-D1                          |
| Moving Studio into ContentEditor fieldtype| Violates P22-D10                         |
| Per-site fetch hacks in preview/SSR       | Violates platform-first — use page_bundle|

═══════════════════════════════════════════════════════════════════════════════
ESCALATION
═══════════════════════════════════════════════════════════════════════════════

SSR parity gap blocks publish → log in gap register; ship with birkly-client.js
  fallback in compiled HTML + fix renderer in M9
tailwind-lite insufficient for user → document in README; defer full JIT to P23
Core route shelf blocked → fallback static website/ + client only (escalate to human)
Scope creep toward Elementor → enforce v1 element list; defer to P23

Start with P22-M0. Commit and push each milestone. Open/update PR before M12 closure.
```

---

## Execution order

1. **P22-M0** — Gap register, SSR parity map, plan artifacts
2. **P22-M1** — Core `register_public_route()` + `plugins/studio/` admin shell
3. **P22-M2** — Projects + pages CRUD + storage layout
4. **P22-M3** — Style kits (minimal, bootstrap, tailwind-lite)
5. **P22-M4** ∥ **P22-M5** — Layout editor + style editor (after M3)
6. **P22-M6** — Bindings UI + `StudioCompiler`
7. **P22-M7** — Dual visual ↔ code editor
8. **P22-M8** — Live preview iframe
9. **P22-M9** ∥ **P22-M10** — SSR public route + export ZIP (after M6)
10. **P22-M11** — Docs + video V-STU-01
11. **P22-M12** — Matrix green, closure, single notification

---

## Specialist routing

| Milestone | Primary owner |
|-----------|---------------|
| M0, M1, M2, M6, M9, M10 | cms-developer |
| M3, M4, M5, M7, M8 | frontend-developer |
| M4, M5 | design-reviewer (after first UI pass) |
| M11 | docs-writer |
| M12 | qa-verifier + lead-engineer |

---

## Closure criteria (M12)

- [ ] `P22-verification-matrix.md` — STU-01…STU-10 all **pass**
- [ ] `tests/run.php` green including studio test suites
- [ ] SSR smoke: `curl /site/{slug}/` returns HTML with collection content
- [ ] Export ZIP integrity + path traversal blocked
- [ ] Plugin deactivate → SSR 503; `content/studio-projects/` files remain
- [ ] `performance_budget_test.php` still green
- [ ] V-STU-01 evidence committed
- [ ] Human notified once via digest

---

## Human approval checklist

- [ ] P22-D1–D12 accepted
- [ ] P22-D8 core `register_public_route()` accepted
- [ ] `tailwind-lite` approach accepted (not full Tailwind JIT in v1)
- [ ] Public URL pattern `/site/{slug}/` accepted
- [ ] Execute autonomously P22-M0→M12 after approval
