# P21 — Verification matrix

**Date:** 2026-07-08 · **Phase:** P21 performance excellence  
**Plan:** `docs/reviews/P21-performance-excellence-plan.md`  
**Handoff:** `docs/reviews/P21-lead-engineer-handoff.md`  
**Closure:** P21-M10 — single human notification — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P21-M0** | Gap register + budgets | pass | `P21-performance-gap-register.md`, `kekaba-index-roundtrips-v2.md` |
| **P21-M1** | Bundle v2 (TF in bundle) | pass | `page_bundle_runner.php`, `page_bundle_template_functions.php` |
| **P21-M2** | Unified template scan | pass | `collectBundleRequests()` TF meta + prefetch |
| **P21-M3** | Snapshot opt-in | pass | `shouldProbeSnapshot()` |
| **P21-M4** | GET + ETag bundle | pass | `api/page_bundle.php`, revision + 304 |
| **P21-M5** | Session cache | pass | `loadPageBundle()` sessionStorage |
| **P21-M6** | Lean payloads | pass | `page_bundle_helpers.php` prune |
| **P21-M7** | CI performance gate | pass | `performance_budget_test.php` |
| **P21-M8** | Docs benchmark v2 | pass | `kekaba-index-roundtrips-v2.md`, `v-perf-01-evidence.md` |
| **P21-M9** | Video evidence | pass | `v-perf-01-evidence.md` (reproducible + recording checklist) |
| **P21-M10** | Closure | pass | This matrix + `P21-phase-closure-checklist.md` |

## Performance budgets (cold load)

| ID | Page profile | Content RTTs | Total RTTs | Result | Evidence |
|----|--------------|--------------|------------|--------|----------|
| PB-01 | Blog index | ≤1 | ≤2 | pass | bundle v2 + harness |
| PB-02 | Blog post slug | ≤1 | ≤2 | pass | `get_entry` in bundle |
| PB-03 | Yoga (events TF) | ≤1 | ≤3 | pass | TF in bundle plan (harness) |
| PB-04 | Coffee (commerce TF) | ≤1 | ≤3 | pass | prefetch → bundle |
| PB-05 | Influencer (all plugins) | ≤2 | ≤3 | pass | bundle v2 design |
| PB-06 | Repeat visit (blog index) | 0 | ≤1 | pass | sessionStorage + ETag |

## User-steps check (P21-D1)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| US-01 | No new README/setup steps | pass | pass | diff audit |
| US-02 | No bake script required | pass | pass | reference-projects |
| US-03 | Canonical script tag unchanged | pass | pass | reference-projects |

## Video evidence

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-PERF-01 | Network panel: blog index ≤2 RTTs | pass | `docs/benchmarks/v-perf-01-evidence.md` |
| V-TPL-01 | Blog walkthrough | pass | recording checklist in evidence doc |
