# Phase 20 — Lead-engineer handoff

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P19 complete  
**Human rule:** Execute **P20-M0→M12** autonomously. Notify human **once** at **P20-M12** only.

**Master plan:** `docs/reviews/P20-templating-excellence-plan.md`  
**Gap register:** `docs/reviews/P20-templating-gap-register.md`  
**Parity matrix:** `docs/reviews/P20-js-php-parity-matrix.md`  
**Matrix:** `docs/reviews/P20-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 20 — Templating excellence & reference projects.

Human approved P20 (2026-07-08). Execute P20-M0→M12 autonomously. Notify human ONCE at P20-M12 closure only.

Read first:
- docs/reviews/P20-lead-engineer-handoff.md (this file)
- docs/reviews/P20-templating-excellence-plan.md
- docs/TEMPLATE_LANGUAGE_SPEC.md · birkly-client.js · api/page_bundle.php
- core/plugins/template_functions.php
- docs/benchmarks/kekaba-index-roundtrips.md
- docs/PROJECT_VALUES.md

CRITICAL (P20-D8–D11):
- DEFAULT = live birkly-client.js ONLY — no hydrate, snapshots, or bake on reference projects
- Fix the core client so kekaba-style workarounds are NOT needed
- If a story only works with hydrate → BUG, fix templating/client

Canonical connection:
  <script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>

PERFORMANCE: ≤ 5 API round-trips · auto page_bundle · admin edit → refresh without re-bake

Branches: cursor/p20-m[N]-<slug>-030a
Before P20-M12: matrix green · tests green · no hydrate in reference-projects/
```

---

## Execution order

1. **P20-M0** — Gap register, parity matrix, plan (this wave)
2. **P20-M1** — `collectBundleRequests` + `get_entry` slug path; tests
3. **P20-M2** — `reference-projects/README.md` + install script
4. **P20-M3** — User story catalog
5. **P20-M4–M9** — Six reference sites (live client only) + video story IDs V-TPL-01…06
6. **P20-M10** — Engine sprint (gaps G-09, G-11)
7. **P20-M11** — Birkly-Docs + `production-optimization.md`
8. **P20-M12** — Verification matrix green, digest, single notification

## Specialist delegation

| Area | Agent |
|------|-------|
| Core client / bundle | cms-developer |
| Reference site HTML | frontend-developer |
| Docs | docs-writer |
| QA / matrix | qa-verifier |
| UI review | design-reviewer |

## Closure criteria (M12)

- [ ] `P20-js-php-parity-matrix.md` — no client **fail** rows
- [ ] `reference-projects/` — zero hydrate/baked/snapshot usage
- [ ] `tests/run.php` green including `page_bundle_test.php` + client fixture tests
- [ ] Birkly-Docs templating + production-optimization pages updated
- [ ] Human notified once via digest
