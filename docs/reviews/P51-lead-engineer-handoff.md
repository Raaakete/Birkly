# Phase 51 — Lead-engineer handoff (server-side rendering & shared template engine)

**Status:** Approved (2026-07-31, Keven) — execute P51-M1→M6 now
**Human rule:** Execute P51-M1→M6 autonomously. Notify human **once** at P51-M6 closure.

**Master plan:** `docs/reviews/P51-ssr-shared-template-engine-plan.md`  
**Gap register:** `docs/reviews/P51-gap-register.md`  
**Matrix:** `docs/reviews/P51-verification-matrix.md`

**Product lock:** Two rendering options only — optimized client-side renderer and server-side renderer. No build-time static export. No third-party frontend frameworks.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 51 — Server-side rendering & shared template engine.

Human approved P51 (2026-07-31, Keven). Execute P51-M1→M6 autonomously in ONE continuous run (or two PR waves: shared engine + server renderer, then client optimization + decoupling).
Notify human ONCE at P51-M6 with verification matrix green + design-reviewer sign-off on admin project settings UX.

GOAL:
1. Create a standalone `birkly-template-engine` module that parses and renders the Birkly template language.
2. Build a server-side renderer inside the Birkly server that uses the shared engine and serves fully rendered HTML for internal projects.
3. Add project-level render settings to the Birkly admin (client-side / server-side / auto).
4. Optimize the client-side renderer with loading and animation helpers.
5. Refactor `birkly-client.js` to use the shared template engine after the server renderer is stable.

DO NOT introduce third-party frontend frameworks.
DO NOT add a build-time static export or zip download feature.
DO NOT change the template language syntax for website authors.
DO NOT modify `birkly-client.js` until M1 and M2 are stable and tested.

READ FIRST:
1. docs/reviews/P51-ssr-shared-template-engine-plan.md
2. docs/reviews/P51-gap-register.md
3. docs/reviews/P51-verification-matrix.md
4. docs/TEMPLATE_LANGUAGE_SPEC.md
5. birkly-client.js (templating portions)
6. server-router.php
7. core/storage/ or equivalent collection/entry APIs
8. admin project settings UI location

═══ WAVE 1 — SHARED ENGINE + SERVER RENDERER (M1 + M2) ═══

P51-M1 (cms-developer, repo: Birkly):
- Audit birkly-client.js and identify the template parsing/rendering logic.
- Create birkly-template-engine.js as a standalone module with a clean API:
  BirklyTemplateEngine.render(html, data, options)
- Implement all documented syntax: loops, from…get, conditionals, filters, ordering, sub-collections, system variables.
- Build tests in tests/template-engine/ covering every syntax case and edge case.
- Matrix: V-ENG-01–10

P51-M2 (cms-developer, repo: Birkly):
- Create server-side render pipeline that uses birkly-template-engine.js.
- Add project routing: determine project from URL path or domain mapping.
- Fetch data directly from Birkly storage/collection APIs.
- Render HTML and send to browser; include fallback to client-side rendering on failure.
- Add output cache with TTL and invalidation hooks.
- Matrix: V-SSR-01–10

═══ WAVE 2 — SETTINGS + CLIENT OPTIMIZATION (M3 + M4) ═══

P51-M3 (frontend-developer + cms-developer, repo: Birkly):
- Extend admin project settings with render mode selector.
- Add hosting type (Birkly hosted / External) and disable SSR when external.
- Add cache TTL and manual invalidation controls.
- Persist settings and trigger cache invalidation on change.
- Matrix: V-SET-01–07

P51-M4 (frontend-developer, repo: Birkly):
- Add loading state helper to birkly-client.js: reveal page after birkly:templates-processed.
- Add animation holdback utility: delay animations until rendering is done.
- Add optional skeleton placeholder helper.
- All changes additive; existing client-side projects must work unchanged.
- Matrix: V-CLI-01–06

═══ WAVE 3 — CLIENT DECOUPLING + QA (M5 + M6) ═══

P51-M5 (cms-developer, repo: Birkly):
- Refactor birkly-client.js to import and use birkly-template-engine.js.
- Remove duplicated template parsing/rendering logic from the client.
- Keep DOM scanning, network fetch, hydration, and events in the client layer.
- Ensure identical output to server renderer and full feature parity.
- Matrix: V-DEC-01–08

P51-M6 (lead-engineer + qa-verifier + design-reviewer, repo: Birkly):
- Run full verification matrix.
- Performance benchmark: client-side vs server-side first paint and layout shift.
- Security review: SSR injection risks, cache invalidation, access control.
- Cross-browser test of both render modes.
- Pilot one internal project on SSR for 48 hours.
- Update documentation.
- Matrix: V-CI-01–05
```

---

## First task

Begin with **P51-M1**. Audit `birkly-client.js`, document the template grammar, and create the `birkly-template-engine` module with tests. Do not touch the client renderer until the server renderer is stable.
