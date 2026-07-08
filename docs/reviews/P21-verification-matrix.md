# P21 — Verification matrix

**Date:** 2026-07-08 · **Phase:** P21 performance excellence  
**Plan:** `docs/reviews/P21-performance-excellence-plan.md`  
**Handoff:** `docs/reviews/P21-lead-engineer-handoff.md`  
**Closure:** P21-M10 — single human notification — **pending**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P21-M0** | Gap register + budgets | pending | `P21-performance-gap-register.md` |
| **P21-M1** | Bundle v2 (TF in bundle) | pending | `page_bundle_runner.php`, tests |
| **P21-M2** | Unified template scan | pending | `birkly-client.js`, fixture tests |
| **P21-M3** | Snapshot opt-in | pending | Client init + reference sites unchanged |
| **P21-M4** | GET + ETag bundle | pending | `api/page_bundle.php`, cache headers |
| **P21-M5** | Session cache | pending | `birkly-client.js` |
| **P21-M6** | Lean payloads | pending | Bundle response size tests |
| **P21-M7** | CI performance gate | pending | `performance_budget_test.php` |
| **P21-M8** | Docs benchmark v2 | pending | `kekaba-index-roundtrips.md`, Birkly-Docs |
| **P21-M9** | Video evidence | pending | V-PERF-01 artifact |
| **P21-M10** | Closure | pending | This matrix all pass |

## Performance budgets (cold load)

| ID | Page profile | Content RTTs | Total RTTs | Result | Evidence |
|----|--------------|--------------|------------|--------|----------|
| PB-01 | Blog index | ≤1 | ≤2 | pending | perf harness |
| PB-02 | Blog post slug | ≤1 | ≤2 | pending | perf harness |
| PB-03 | Yoga (events TF) | ≤1 | ≤3 | pending | perf harness |
| PB-04 | Coffee (commerce TF) | ≤1 | ≤3 | pending | perf harness |
| PB-05 | Influencer (all plugins) | ≤2 | ≤3 | pending | perf harness |
| PB-06 | Repeat visit (blog index) | 0 | ≤1 | pending | session cache harness |

## User-steps check (P21-D1)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| US-01 | No new README/setup steps | pass | pending | diff audit |
| US-02 | No bake script required | pass | pending | reference-projects grep |
| US-03 | Canonical script tag unchanged | pass | pending | reference-projects |

## Video evidence

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-PERF-01 | Network panel: blog index ≤2 RTTs | pending | recording + `docs/benchmarks/` |
| V-TPL-01 | Blog walkthrough (P20 stub → recording) | pending | `reference-projects/blog/VIDEO.md` |
