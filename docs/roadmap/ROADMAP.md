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
| **Templating / stacks** | Web components + AI docs gaps — see **Phase 45** (proposed) |

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

## Proposed next phase (awaiting approval)

### Phase 45 — Templating, web components, and multi-stack integration

**Status:** **Proposed** (2026-07-26) — **M0 plan PR; human approval before M1**

**Goal:** One-line `<birkly-region>` for CMS blocks (no per-page template repetition); scoped `processTemplates`; truthful AI/MCP docs; Birkly-Docs guides for HTML, web components, SPAs, and SSR.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P45-M0** | Plan, gap register, matrix, handoff | **this PR** |
| **P45-M1** | AI + MCP syntax truth (remove phantom directives) | pending |
| **P45-M2** | Scoped `processTemplates({ root })` | pending |
| **P45-M3** | `<birkly-region>` component (entry + list) | pending |
| **P45-M4** | Connect lifecycle + `data-birkly-region` | pending |
| **P45-M5** | Enhance-only component helpers | pending |
| **P45-M6** | Template language spec v1.0 | pending |
| **P45-M7** | Project template validator | pending |
| **P45-M8** | Birkly-Docs: stack hub + web components | pending |
| **P45-M9** | Birkly-Docs: SPA (React/Vue) guide | pending |
| **P45-M10** | Birkly-Docs: SSR / build-time guide | pending |
| **P45-M11** | Tests + V-TPL matrix | pending |
| **P45-M12** | Birkly-Website reference + closure | pending |

**Spec:** `docs/reviews/P45-templating-and-stacks-plan.md`  
**Handoff:** `docs/reviews/P45-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P45-verification-matrix.md`  
**Gaps:** `docs/reviews/P45-gap-register.md`

**Human decisions (proposed):** P45-D1–D12 · notify once at M12

**Depends on:** P44 complete

**Paused / not in scope:** Admin eval replacement · Studio P26 · P42 marketing template pack

---

## Task handoff format

```markdown
### Task: P45-M[N]-T[N] [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | lead-engineer
- **Repo(s)**: Birkly | Birkly-Docs | Birkly-Website
- **Files**: path/to/files
- **Depends on**: P45-M[N]-T[N] | none
- **Acceptance criteria**:
  - ...
```

Branch template: `cursor/p45-m[N]-<slug>-030a`
