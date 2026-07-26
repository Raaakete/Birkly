# Birkly Platform Roadmap (active slice)

This file tracks **current state** and the **active execution phase**. Full historical roadmap may live in `Raaakete/Birkly-Internal` when the public repo is scrubbed for OSS.

## Plan governance

| Change type | Who decides |
|-------------|-------------|
| **New phase approval** | Human + product-planner |
| **Milestone execution** | Lead-engineer autonomously within approved phase |
| **Small status updates** | Planner or lead via PR |

---

## Current state (verified 2026-07-26)

| Area | Status |
|------|--------|
| **Docker / Dokploy** | `BIRKLY_DATA=/app/data` single volume (#282–#288) |
| **Production** | `cms.birkly.cloud` + `birkly.cloud` on one Dokploy app |
| **P43 Hosting** | Settings-driven CMS vs public URLs — complete |
| **P44 Admin UX** | Full design remediation M1–M12 — complete |
| **P45 Templating** | Complete — birkly-region, stack docs |
| **AI Presence** | Phase 46 approved — execute M1→M14 |

**Active plugins (dev tree):** automation, commerce, events, user_management, studio, sample-plugin

---

## Completed phases

### Phase 43 — Configurable hosting URLs (CMS vs public site)

**Status:** **Complete** (2026-07-25)

| Milestone | Focus | Status |
|-----------|--------|--------|
| P43-M0–M9 | Settings URLs, routing, Hosting API/UI, nginx, tests, docs | complete |

**Artifacts:** `docs/reviews/P43-*` · `core/hosting_urls.php` · `api/hosting.php`

### Phase 44 — Admin UI/UX excellence (full design remediation)

**Status:** **Complete** (2026-07-25)

| Milestone | Focus | Status |
|-----------|--------|--------|
| P44-M0–M12 | Save model, settings IA, hosting wizard, tokens, a11y, i18n, Project tab, closure | complete |

**Artifacts:** `docs/reviews/P44-*` · `docs/admin-design-system.md`

---

## Recently completed

### Phase 45 — Templating, web components, and multi-stack integration

**Status:** **Complete** (2026-07-26) — V-TPL matrix green

**Goal:** One-line `<birkly-region>` for CMS blocks (no per-page template repetition); scoped `processTemplates`; truthful AI/MCP docs; Birkly-Docs guides for HTML, web components, SPAs, and SSR.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P45-M0** | Plan, gap register, matrix, handoff | **done** |
| **P45-M1** | AI + MCP syntax truth (remove phantom directives) | **done** |
| **P45-M2** | Scoped `processTemplates({ root })` | **done** |
| **P45-M3** | `<birkly-region>` component (entry + list) | **done** |
| **P45-M4** | Connect lifecycle + `data-birkly-region` | **done** |
| **P45-M5** | Enhance-only component helpers | **done** |
| **P45-M6** | Template language spec v1.0 | **done** |
| **P45-M7** | Project template validator | **done** |
| **P45-M8** | Birkly-Docs: stack hub + web components | **done** |
| **P45-M9** | Birkly-Docs: SPA (React/Vue) guide | **done** |
| **P45-M10** | Birkly-Docs: SSR / build-time guide | **done** |
| **P45-M11** | Tests + V-TPL matrix | **done** |
| **P45-M12** | Birkly-Website reference + closure | **done** |

**Spec:** `docs/reviews/P45-templating-and-stacks-plan.md`  
**Handoff:** `docs/reviews/P45-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P45-verification-matrix.md` (all **pass**)  
**Gaps:** `docs/reviews/P45-gap-register.md`

**PRs:** Birkly #307 · Birkly-Docs #39 · Birkly-Website #13

---

## Next active phase

### Phase 46 — AI Presence (brand context for AI systems)

**Status:** **Approved** (2026-07-26) — **M0 complete**, **M1→M14 execution ready**

**Goal:** Thin layer on CMS — brand identity, claims, evidence, media context, communication guidance; publish `/.well-known/brand-context.json` + public API on the brand's public domain. Not a chatbot. Not duplicate Content/Media.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P46-M0** | Plan, gap register, matrix, handoff | **this PR** |
| **P46-M1** | Schema + storage + `core/ai_presence.php` | pending |
| **P46-M2** | Nav + page shell + tabs | pending |
| **P46-M3** | Overview + completeness + next steps | pending |
| **P46-M4** | Skippable first-time setup | pending |
| **P46-M5** | Context tab (identity, positioning, guidance) | pending |
| **P46-M6** | Connected content pickers | pending |
| **P46-M7** | Claims & Evidence | pending |
| **P46-M8** | Media context tab | pending |
| **P46-M9** | Draft/publish + versions | pending |
| **P46-M10** | Well-known JSON + public API + JSON-LD | pending |
| **P46-M11** | Staleness + Publish tab | pending |
| **P46-M12** | Context Preview (sources visible) | pending |
| **P46-M13** | AI suggestions (approve-to-save) | pending |
| **P46-M14** | Birkly-Docs, i18n, tests, closure | pending |

**Spec:** `docs/reviews/P46-ai-presence-plan.md`  
**Handoff:** `docs/reviews/P46-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P46-verification-matrix.md`  
**Gaps:** `docs/reviews/P46-gap-register.md`

**Human decisions (locked):** P46-D1–D14 · notify once at M14

**Depends on:** P43 public site URLs · P44 admin UX patterns

**Paused / not in scope:** Admin eval replacement · Studio P26 · P42 marketing template pack

---

## Task handoff format

```markdown
### Task: P46-M[N]-T[N] [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | lead-engineer
- **Repo(s)**: Birkly | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: P46-M[N]-T[N] | none
- **Acceptance criteria**:
  - ...
```

Branch template: `cursor/p46-m[N]-<slug>-030a`
