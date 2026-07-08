# P20 — Verification matrix

**Date:** 2026-07-08 · **Phase:** P20 templating excellence & reference projects  
**Spec:** `docs/reviews/P20-templating-excellence-plan.md`  
**Handoff:** `docs/reviews/P20-lead-engineer-handoff.md`  
**Closure:** Target P20-M12

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P20-M0** | Gap register, parity matrix | pending | `P20-templating-gap-register.md` |
| **P20-M1** | Live-path engine | pending | `client_template_render_test.php` |
| **P20-M2** | Connection kit | pending | `install-reference-project.php` |
| **P20-M3** | User story catalog | pending | `P20-user-story-catalog.md` |
| **P20-M4** | Blog | pending | `reference-projects/blog/` |
| **P20-M5** | Portfolio | pending | `reference-projects/portfolio/` |
| **P20-M6** | Yoga / events | pending | `reference-projects/yoga/` |
| **P20-M7** | Coffee / commerce | pending | `reference-projects/coffee-shop/` |
| **P20-M8** | Social | pending | `reference-projects/social/` |
| **P20-M9** | Influencer | pending | `reference-projects/influencer/` |
| **P20-M10** | Engine sprint | pending | gap register blockers closed |
| **P20-M11** | Docs | pending | Birkly-Docs + advanced optimization doc |
| **P20-M12** | Closure | pending | `P20-phase-closure-checklist.md` |

## Live-path principle (P20-D8–D11)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| LP-01 | No reference project uses hydrate | pending | grep reference-projects |
| LP-02 | No birkly-baked meta on reference sites | pending | |
| LP-03 | Advanced optimization doc separates optional path | pending | `docs/advanced/production-optimization.md` |
| LP-04 | Getting-started docs do not require bake | pending | Birkly-Docs |

## Performance (PERF-*)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| PERF-01 | birkly-client.js only | pending | all 6 projects |
| PERF-02 | ≤ 5 API requests first load | pending | per-project README |
| PERF-03 | Admin edit → refresh, no re-bake | pending | manual + video |
| PERF-04 | Templates fully resolve | pending | |
| PERF-05 | Plugin TFs within budget | pending | yoga–influencer projects |

## Client engine

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| CE-01 | client_template_render_test.php | pending | |
| CE-02 | client_page_bundle_budget_test.php | pending | |
| CE-03 | Auto page_bundle from template scan | pending | |
| CE-04 | Stale templates.html retired | pending | |

## Reference projects

| Project | User stories pass | PERF pass | Video | Result |
|---------|-------------------|-----------|-------|--------|
| Blog | pending | pending | V-TPL-01 | pending |
| Portfolio | pending | pending | V-TPL-02 | pending |
| Yoga | pending | pending | V-TPL-03 | pending |
| Coffee | pending | pending | V-TPL-04 | pending |
| Social | pending | pending | V-TPL-05 | pending |
| Influencer | pending | pending | V-TPL-06 | pending |

## Closure (P20-M12)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| C-01 | P20 matrix all pass | pending | |
| C-02 | Birkly tests green | pending | |
| C-03 | ci-check.sh green | pending | |
| C-04 | Roadmap § P20 complete | pending | |
| C-05 | Human notified once | pending | digest + closure report |
