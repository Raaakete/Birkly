# Phase 21 — Lead-engineer handoff

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P20 complete — reference projects, live client, `page_bundle`, 141+ test suites  
**Human rule:** Execute **P21-M0→M10** autonomously. Notify human **once** at **P21-M10** only.

**Master plan:** `docs/reviews/P21-performance-excellence-plan.md`  
**Gap register:** `docs/reviews/P21-performance-gap-register.md`  
**Matrix:** `docs/reviews/P21-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 21 — Performance excellence (live path).

Human approved P21 (2026-07-08). Execute P21-M0→M10 autonomously. Notify human ONCE at P21-M10 closure only.

Read first:
- docs/reviews/P21-lead-engineer-handoff.md (this file)
- docs/reviews/P21-performance-excellence-plan.md
- docs/PROJECT_VALUES.md
- docs/benchmarks/kekaba-index-roundtrips.md
- birkly-client.js · api/page_bundle.php · core/page_bundle_runner.php
- reference-projects/README.md
- docs/advanced/production-optimization.md (hydrate is OPTIONAL only — never required)

MISSION:
Birkly is marketed as fast, lightweight, flexible, and robust. Performance must be
above average for a no-build CMS — with ZERO extra steps for users.

CRITICAL (P21-D1–D8):
- NO mandatory bake, hydrate, build scripts, npm, or terminal steps for site owners
- DEFAULT = live birkly-client.js ONLY — same canonical connection as P20:
    <script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>
- All wins are platform fixes (client + page_bundle) — no per-site fetch hacks
- Admin edit → browser refresh shows changes (no re-bake)
- Dependency-free core: PHP + vanilla JS only

PERFORMANCE TARGETS (replace ≤5 RTTs):
- Typical index: ≤1 content RTT, ≤2 total cold RTTs
- Plugin-heavy index: ≤2 content RTTs, ≤3 total cold RTTs
- Repeat visit (unchanged content): 0 content RTTs via ETag/session cache
- CI must enforce budgets — not manual DevTools only

KEY ENGINE WORK:
- P21-M1: Bundle v2 — template_function + template_functions_meta inside page_bundle
- P21-M2: Unified template scan before any network
- P21-M3: Snapshot probe opt-in only (skip on live default)
- P21-M4: GET page_bundle + ETag + content revision hash
- P21-M5: Client sessionStorage bundle cache
- P21-M6: Leaner bundle payloads (server-side field pruning)
- P21-M7: performance_budget_test.php in tests/run.php
- P21-M8: Benchmark v2 doc + Birkly-Docs
- P21-M9: Record V-PERF-01 (Network panel proof) + V-TPL-01
- P21-M10: Matrix green, tests green, single notification

Branches: cursor/p21-m[N]-<slug>-030a

Before P21-M10:
- performance_budget_test.php green
- All reference-project profiles meet PB-01…PB-06 in verification matrix
- No new user setup steps in README / START_HERE / reference-projects
- Video evidence exists (not just VIDEO.md stubs)
```

---

## Execution order

1. **P21-M0** — Gap register, performance budgets, plan sign-off artifacts
2. **P21-M1** — Bundle v2 API + runner + tests
3. **P21-M2** — Unified `collectBundleRequests()` / template scan
4. **P21-M3** ∥ **P21-M4** — Snapshot opt-in + GET/ETag bundle
5. **P21-M5** — Client session cache
6. **P21-M6** — Lean payloads
7. **P21-M7** — CI performance gate
8. **P21-M8** — Docs + benchmark v2
9. **P21-M9** — Video recordings (V-PERF-01, V-TPL-01)
10. **P21-M10** — Verification matrix, closure checklist, single digest

## Specialist delegation

| Area | Agent |
|------|-------|
| Bundle API + runner | cms-developer |
| Client init / cache | cms-developer |
| CI perf harness | qa-verifier |
| Docs + benchmark | docs-writer |
| Screen recording | computerUse or lead-engineer |
| Values / no-user-steps audit | qa-verifier |

## Closure criteria (M10)

- [ ] `P21-verification-matrix.md` — PB-01…PB-06 and US-01…US-03 all **pass**
- [ ] `tests/run.php` green including `performance_budget_test.php`
- [ ] Cold blog index: ≤2 total RTTs in harness (≤1 content RTT after endpoint cached)
- [ ] Repeat visit: 0 content RTTs when revision unchanged
- [ ] No new mandatory user steps (diff audit on README, START_HERE, reference-projects)
- [ ] V-PERF-01 recording committed or linked in benchmark doc
- [ ] Human notified once via digest

## Anti-patterns (stop and fix platform instead)

| Anti-pattern | Why |
|--------------|-----|
| “Run bake script for performance” | Violates P21-D1 |
| Site-specific cache JS | Violates platform-first |
| npm bundle splitter in core | Violates dependency-free |
| Requiring hydrate for plugin pages | Bug — bundle v2 must cover TF |
