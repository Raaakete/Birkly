# P20 — Verification matrix

**Date:** 2026-07-08 · **Phase:** P20 templating excellence & reference projects  
**Spec:** `docs/reviews/P20-templating-excellence-plan.md`  
**Handoff:** `docs/reviews/P20-lead-engineer-handoff.md`  
**Closure:** P20-M12 — single human notification — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P20-M0** | Gap register + parity matrix | pass | `P20-templating-gap-register.md`, `P20-js-php-parity-matrix.md` |
| **P20-M1** | Live-path engine + tests | pass | `page_bundle_test.php`, `client_bundle_fixture_test.php`, `birkly-client.js` |
| **P20-M2** | reference-projects kit | pass | `reference-projects/`, `install-reference-project.sh` |
| **P20-M3** | User story catalog | pass | `docs/reference-projects/user-stories.md` |
| **P20-M4** | Blog reference | pass | `reference-projects/blog/` |
| **P20-M5** | Portfolio reference | pass | `reference-projects/portfolio/` |
| **P20-M6** | Yoga (events) | pass | `reference-projects/yoga/` |
| **P20-M7** | Coffee (commerce) | pass | `reference-projects/coffee/` |
| **P20-M8** | Social (user+commerce) | pass | `reference-projects/social/` |
| **P20-M9** | Influencer (all plugins) | pass | `reference-projects/influencer/` |
| **P20-M10** | Engine sprint | pass | `client_template_parity_test.php`, parity matrix |
| **P20-M11** | Docs | pass | Birkly-Docs templating pages + `production-optimization.md` |
| **P20-M12** | Closure | pass | This matrix + `P20-phase-closure-checklist.md` |

## Live client path (P20-D8–D11)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| LC-01 | Reference sites use canonical script tag | pass | pass | All `reference-projects/*/website/*.html` |
| LC-02 | No `birkly-baked` in reference-projects | pass | pass | grep audit |
| LC-03 | No `birkly-hydrate.js` in reference-projects | pass | pass | grep audit |
| LC-04 | No custom fetch() in reference-projects | pass | pass | grep audit (content text only) |
| LC-05 | Slug pages use bundle `get_entry` | pass | pass | `collectBundleRequests` + `getEntryBySlug` |
| LC-06 | ≤5 RTTs on index (3 collections) | pass | pass | `docs/benchmarks/kekaba-index-roundtrips.md` model |

## Video story stubs

| ID | Project | Result | Evidence |
|----|---------|--------|----------|
| V-TPL-01 | Blog | pass | `reference-projects/blog/VIDEO.md` |
| V-TPL-02 | Portfolio | pass | `reference-projects/portfolio/VIDEO.md` |
| V-TPL-03 | Yoga | pass | `reference-projects/yoga/VIDEO.md` |
| V-TPL-04 | Coffee | pass | `reference-projects/coffee/VIDEO.md` |
| V-TPL-05 | Social | pass | `reference-projects/social/VIDEO.md` |
| V-TPL-06 | Influencer | pass | `reference-projects/influencer/VIDEO.md` |
