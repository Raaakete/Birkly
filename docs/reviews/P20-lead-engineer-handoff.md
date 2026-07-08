# Phase 20 — Lead-engineer handoff

**Status:** Approved (2026-07-08, Keven) — autonomous execution P20-M0→M12  
**Baseline:** P16–P19 complete — Birkly **138 test suites** · Marketplace **17 test suites**  
**Human rule:** Execute all P20 milestones autonomously. Notify human **once** at **P20-M12 closure** only.

**Master plan:** `docs/reviews/P20-templating-excellence-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 20  
**Matrix:** `docs/reviews/P20-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 20 — Templating excellence & reference projects.

Human approved P20 (2026-07-08). Execute P20-M0→M12 autonomously. Notify human ONCE at P20-M12 closure only.

Read first:
- docs/reviews/P20-lead-engineer-handoff.md (this file)
- docs/reviews/P20-templating-excellence-plan.md
- docs/TEMPLATE_LANGUAGE_SPEC.md
- Birkly-Docs/03-templating/canonical-syntax.md
- birkly-client.js · birkly-client-modules/
- core/plugins/template_functions.php · api/public.php · api/page_bundle.php
- docs/benchmarks/kekaba-index-roundtrips.md (understand what we are fixing — live path must be fast WITHOUT hydrate)
- docs/PROJECT_VALUES.md

## Context

P16–P19 COMPLETE. Human explicitly rejected beta launch as next phase.

P20 GOAL: Templating language + CMS↔project connection must be fast, flexible, robust OUT OF THE BOX.

CRITICAL — LOCKED BY HUMAN (P20-D8–D11):
- DEFAULT = live birkly-client.js ONLY. No hydrate, no snapshots, no bake on reference projects.
- Hydrate/snapshots/bake are OPTIONAL advanced production tools — NOT default, NOT in getting-started.
- kekaba added hydrate/snapshots as workarounds for slow client — FIX THE CORE, do not require those workarounds.
- If a user story only works with hydrate/snapshots → file as BUG and fix client/templating.

Canonical connection (every reference project):
  <script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>

FORBIDDEN on reference projects:
  birkly-hydrate.js, meta birkly-baked, page_snapshot dependency, site-specific loader route tables

PERFORMANCE BUDGET (PERF-*):
  ≤ 5 API round-trips first paint (bundle + TF meta + batched calls)
  0 serial per-collection list_entries (auto page_bundle)
  Admin edit → browser refresh shows change without re-bake
  < 2s TTI local fixture data

## Milestones

P20-M0: Gap register, JS↔PHP parity matrix, audit hydrate vs live defaults
P20-M1: Live-path engine — auto page_bundle from template scan, client fixture tests, perf budget tests, doc cleanup
P20-M2: reference-projects/ kit + install-reference-project.php + connection checklist
P20-M3: User story catalog (all 36+ stories + PERF-01–05)
P20-M4: reference-projects/blog/ + video V-TPL-01
P20-M5: reference-projects/portfolio/ + video V-TPL-02
P20-M6: reference-projects/yoga/ (events plugin) + video V-TPL-03
P20-M7: reference-projects/coffee-shop/ (commerce) + video V-TPL-04
P20-M8: reference-projects/social/ (user + commerce) + video V-TPL-05
P20-M9: reference-projects/influencer/ (events + commerce + user) + video V-TPL-06
P20-M10: Engine sprint — fix blockers from gap register (client-first)
P20-M11: Birkly-Docs templating overhaul; docs/advanced/production-optimization.md (hydrate/snapshot as optional only)
P20-M12: Matrix green, closure checklist, SINGLE human notification + video index

## Reference projects (templating ONLY — no custom fetch JS)

1. Blog — core only (loops, site_elements, slug, forms, i18n)
2. Portfolio — sub-entries, featured, ContentEditor HTML
3. Yoga — events plugin, upcoming_events, RSVP
4. Coffee — commerce plugin, catalog, cart, purchase options
5. Social — user_management + commerce, login, feed, member blur
6. Influencer — all three plugins, membership, premium content, paid events

Rule: If you need custom JavaScript to fetch CMS data → templating gap → fix engine or add template function.

## Locked decisions

P20-D1: Templating + data-birkly-* only on reference sites
P20-D2: Plugins per scenario via dev/marketplace install
P20-D3: reference-projects/<slug>/ with collections, website, USER_STORIES.md
P20-D4: Walkthrough videos in docs/reviews/artifacts/ (admin edit → refresh → live proof)
P20-D5: Fix blockers before polish
P20-D6: Client-first; document PHP admin parser parity separately
P20-D7: Single notification P20-M12
P20-D8: Live client ONLY on reference projects
P20-D9: Performance budgets mandatory
P20-D10: Hydrate/snapshot/bake = advanced optional docs only
P20-D11: Hydrate-dependent pass = bug

## Execution order

P20-M0 → M1 → M2 → M3
  → M4 → M5 → M6 → M7 → M8 → M9 (reference projects — can parallel after M2 if no engine blockers)
  → M10 (engine sprint from gaps found in M4–M9)
  → M11 (docs)
  → M12 (closure — notify human ONCE)

Do NOT skip M10 even if projects "work" — close gap register blockers.

Branches: cursor/p20-m[N]-<slug>-030a

## Delegate

- cms-developer: P20-M1 engine, page_bundle, client tests, M10 fixes, install script
- frontend-developer: reference-projects/website HTML (templating only), connection kit
- docs-writer: P20-M3 catalog, P20-M11 Birkly-Docs, video scripts
- qa-verifier: PERF-* per project, parity matrix, user story sign-off
- design-reviewer: reference sites functional clarity (not production polish)

## Verification gates (before P20-M12)

- [ ] docs/reviews/P20-verification-matrix.md — all rows pass
- [ ] php tests/run.php — Birkly green (includes client_template_render_test, budget tests)
- [ ] ./scripts/ci-check.sh — green
- [ ] All 6 reference projects: install script works, PERF-* documented
- [ ] NO reference project HTML contains hydrate/baked meta
- [ ] docs/advanced/production-optimization.md exists (hydrate optional)
- [ ] 6 video artifacts indexed
- [ ] docs/reviews/P20-phase-closure-checklist.md
- [ ] Roadmap § Phase 20 complete

Run php tests/run.php before every merge.
Merge after qa-verifier pass (design-reviewer on reference site HTML).

NOT IN SCOPE: beta launch, waitlist, kekaba loader refactor (note as follow-up in closure)
```

---

## Milestone checklist

| ID | Deliverable |
|----|-------------|
| M0 | Gap register + parity matrix |
| M1 | Live-path engine + client tests |
| M2 | Connection kit + install script |
| M3 | User story catalog |
| M4 | Blog + V-TPL-01 |
| M5 | Portfolio + V-TPL-02 |
| M6 | Yoga + V-TPL-03 |
| M7 | Coffee + V-TPL-04 |
| M8 | Social + V-TPL-05 |
| M9 | Influencer + V-TPL-06 |
| M10 | Engine sprint |
| M11 | Docs + advanced optimization doc |
| M12 | **Closure + single human notification** |

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Reference project needs hydrate to pass | P20-D11 — fix client, do not add bake |
| Plugin TF N+1 on influencer/social | M1 batching + M10 sprint |
| Large demo-content overlap | blog extends demo-content/blog; others net-new under reference-projects/ |
| Video production | Screen recording + script in repo; artifact path in closure |
