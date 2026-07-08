# P22 — Verification matrix (Studio plugin)

**Date:** 2026-07-08 · **Phase:** P22 Studio plugin  
**Plan:** `docs/reviews/P22-studio-plugin-plan.md`  
**Handoff:** `docs/reviews/P22-lead-engineer-handoff.md`  
**Closure:** P22-M12 — single human notification — **pending approval**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P22-M0** | Gap register + decisions | pending | `P22-studio-gap-register.md` |
| **P22-M1** | Core route shelf + plugin shell | pending | `core/plugin_public_routes.php`, `plugins/studio/` |
| **P22-M2** | Projects + pages CRUD | pending | `StudioProjectRepository`, admin UI |
| **P22-M3** | Style kits | pending | `plugins/studio/kits/` |
| **P22-M4** | Layout editor | pending | canvas JSON schema, element palette |
| **P22-M5** | Style & rules editor | pending | `styles/custom.css`, inspector |
| **P22-M6** | Bindings + compiler | pending | `StudioCompiler`, binding blocks |
| **P22-M7** | Dual editor | pending | code tab round-trip tests |
| **P22-M8** | Live preview | pending | iframe + P21 bundle |
| **P22-M9** | SSR public route | pending | `StudioRenderer`, `/site/{slug}/` |
| **P22-M10** | Export ZIP | pending | export API + tests |
| **P22-M11** | Docs + video | pending | Birkly-Docs, V-STU-01 |
| **P22-M12** | Closure | pending | This matrix + closure checklist |

## Feature stories

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-01 | Create project with style kit | Bootstrap kit applied | pending | |
| STU-02 | Add/reorder/delete pages | Page list works | pending | |
| STU-03 | Build layout visually | Sections, grid, flex | pending | |
| STU-04 | Edit styles and CSS rules | custom.css updated | pending | |
| STU-05 | Insert blog collection loop | `{for each entry in 'blog'}` in HTML | pending | |
| STU-06 | Code tab edit survives compile | Bindings intact | pending | |
| STU-07 | Live preview shows CMS data | P21 bundle ≤2 RTTs simple page | pending | |
| STU-08 | Publish SSR URL | HTML contains entry titles | pending | |
| STU-09 | Export ZIP | website/ + styles + README | pending | |
| STU-10 | Deactivate plugin | SSR 503, files remain | pending | |

## Style kits

| ID | Kit | Vendored | No CDN required | Result | Evidence |
|----|-----|----------|-----------------|--------|----------|
| SK-01 | minimal | yes | yes | pending | |
| SK-02 | bootstrap | yes | yes | pending | |
| SK-03 | tailwind-lite | yes | yes | pending | |

## Performance & regression

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| PR-01 | P21 budgets unchanged on reference-projects | pass | pending | `performance_budget_test.php` |
| PR-02 | SSR first response | No client JS required for static content | pending | curl smoke |
| PR-03 | Preview iframe | ≤2 cold RTTs simple Studio page | pending | DevTools |

## Video evidence

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-STU-01 | Create → layout → bind → preview → SSR → export | pending | `plugins/studio/VIDEO.md` |

## Values check

| Value | Result | Notes |
|-------|--------|-------|
| Dependency-free | pending | Vendored kits only |
| UI decoupled | pending | Admin vs public HTML |
| Self-contained plugin | pending | Deactivatable |
| Platform-first | pending | Reuses page_bundle |
