# Birkly Platform Roadmap (active slice)

This file tracks **current state** and the **active execution phase**. Full historical roadmap may live in `Raaakete/Birkly-Internal` when the public repo is scrubbed for OSS.

**Catch-up inventory:** `docs/roadmap/ROADMAP-CATCHUP-2026-07-29.md` — unphased work through 2026-07-29.

## Plan governance

| Change type | Who decides |
|-------------|-------------|
| **New phase approval** | Human + product-planner |
| **Milestone execution** | Lead-engineer autonomously within approved phase |
| **Small status updates** | Planner or lead via PR |

---

## Current state (verified 2026-07-31)

| Area | Status |
|------|--------|
| **Docker / Dokploy** | `BIRKLY_DATA=/app/data` single volume |
| **Production** | `cms.birkly.cloud` + `birkly.cloud` |
| **P43–P45** | Hosting, admin UX, templating — complete |
| **Agent Guide** | P46+P47 complete — polish + docs ongoing |
| **Content UX (P39)** | Shipped (#258–268) — catch-up doc only |
| **Marketplace CMS UI** | Large wave merged 2026-07-29 — unphased |

**Active plugins (dev tree):** automation, commerce, events, user_management, studio, sample-plugin

**Active phase:** None — **P51 complete** (2026-07-31). **P52 proposed** (awaiting human approval).

Focus delivered: shared `birkly-template-engine.js`, optional SSR for Birkly-hosted `project/` sites, admin render settings, client loading helpers, client↔engine decoupling.

---

## Proposed — Phase 52 (PHP-native SSR)

**Status:** Proposed 2026-08-01 — awaiting human approval  
**Artifacts:** `docs/reviews/P52-php-native-ssr-plan.md`, `P52-gap-register.md`, `P52-verification-matrix.md`, `P52-lead-engineer-handoff.md`

| Milestone | Focus | Status |
|-----------|--------|--------|
| P52-M1 | PHP `render()` port + JS↔PHP fixture parity | Pending approval |
| P52-M2 | Cut over `project_ssr.php` to in-process PHP (no Node) | Pending approval |
| P52-M3 | Default `render_mode=server` for Birkly hosting; drop Docker Node | Pending approval |
| P52-M4 | Delete Node SSR CLI / helpers | Pending approval |
| P52-M5 | CI, localhost PHP-only smoke, closure | Pending approval |

**Product intent:** Localhost and Birkly-hosted project sites use SSR by default; PHP is the only built-in host runtime. Browser keeps the JS engine via `birkly-client.js` (not an author/npm dependency).

---

## Completed — Phase 51 (SSR & shared template engine)

**Status:** Complete (2026-07-31)  
**Artifacts:** `docs/reviews/P51-ssr-shared-template-engine-plan.md`, `P51-gap-register.md`, `P51-verification-matrix.md`, `P51-ssr-notes.md`

| Milestone | Focus | Status |
|-----------|--------|--------|
| P51-M1 | Shared `birkly-template-engine.js` + syntax tests | Done |
| P51-M2 | Project SSR pipeline (Node CLI + cache + fallback) | Done |
| P51-M3 | Admin Project render settings + invalidate | Done |
| P51-M4 | Client loading reveal / animation holdback / skeletons | Done |
| P51-M5 | Decouple `birkly-client.js` onto shared engine | Done |
| P51-M6 | CI, design review, security/perf notes, closure | Done |

---

## Completed — Phase 50 (content model clarity)

**Status:** Complete (2026-07-29)  
**PRs:** Birkly-Docs #48 · Birkly (CMS) pending merge  
**Artifacts:** `docs/reviews/P50-content-model-clarity-plan.md`, `P50-gap-register.md`, `P50-verification-matrix.md`

| Milestone | Focus | Status |
|-----------|--------|--------|
| P50-M1 | Birkly-Docs — content-model overview, dynamic items, site-elements pattern | Done |
| P50-M2 | CMS docs sync hardening, deprecate legacy `admin/docs/*.php` | Done |
| P50-M3 | DynamicItems multilang + init fixes | Done |
| P50-M4 | Collection/sub-collection save feedback + navigation + bugs | Done |
| P50-M5 | Fieldtype audit matrix (26 types) | Done |
| P50-M6 | CI, design review, closure | Done |

---

## Completed phases

### Phase 43 — Configurable hosting URLs

**Status:** **Complete** (2026-07-25) · `docs/reviews/P43-*`

### Phase 44 — Admin UI/UX excellence

**Status:** **Complete** (2026-07-25) · `docs/reviews/P44-*` · `docs/admin-design-system.md`

### Phase 45 — Templating & multi-stack integration

**Status:** **Complete** (2026-07-26) · V-TPL green · PRs #307, Birkly-Docs #39, Birkly-Website #13

`<birkly-region>`, scoped `processTemplates`, stack docs, template validator, spec v1.0.

### Phase 46 — Agent Guide (planned as “AI Presence”)

**Status:** **Complete** (2026-07-27) — shipped as **Agent Guide** in admin UI

| Milestone | Focus | Status |
|-----------|--------|--------|
| P46-M0–M14 | Schema, admin workspace, publish, public API, preview, tests | **done** |

**User-facing:** Admin nav **Agent Guide** · `/.well-known/agent-guide.json` · `api/public/ai_presence.php`

**Beyond original P46 scope (shipped as P47 wave):** Products tab, AI Assist sidebar, sources of truth, cross-links, link health, CMS sync hints.

**Artifacts:** `docs/reviews/P46-*` · `core/ai_presence.php` · `admin/templates/ai-presence.html`

**PRs:** #330 plan · #331 feature · #334–356 UX · #353 cross-links · #336 Pro

---

## Shipped without full phase docs (see catch-up file)

| ID | What | PRs / refs |
|----|------|------------|
| **P38** | AI Project Gateway (MCP/chat `project/`) | #246, AI architecture docs |
| **P39** | Content editor + entries UX + chat context | #258–268 |
| **Marketplace** | CMS marketplace tab polish | #402–423 |
| **Admin audit** | UX fix tracks A–E | `admin-ux-fix-plan-2026-07.md` |

---

## Paused / not in scope

Admin PHP `eval` replacement · Studio P26 · P42 marketing template pack · P28 beta launch automation · Core vector DB / embeddings index

**Product note:** Vector search is **not** core CMS. Agent Guide + structured content is the primary path for machine-readable brand truth. Optional RAG/support-bot indexing could be a **future plugin**, not required for headless CMS.

---

## Task handoff format

```markdown
### Task: P[N]-M[N]-T[N] [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | lead-engineer
- **Repo(s)**: Birkly | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: ...
- **Acceptance criteria**:
  - ...
```

Branch template: `cursor/p[N]-m[N]-<slug>-030a`
