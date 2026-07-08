# P21 — Performance gap register

**Date:** 2026-07-08 · **Phase:** P21 performance excellence  
**Plan:** `docs/reviews/P21-performance-excellence-plan.md`  
**Baseline benchmark:** `docs/benchmarks/kekaba-index-roundtrips.md` (v1 — ≤5 RTT model)

| ID | Gap | Current | Target | Milestone | Status |
|----|-----|---------|--------|-----------|--------|
| G-P01 | Template functions outside bundle | Separate TF list + N POSTs | Included in bundle v2 | M1 | **closed** |
| G-P02 | Incomplete pre-network scan | Loops only | Unified scan | M2 | **closed** |
| G-P03 | Wasted snapshot RTT | Always probed | Opt-in only | M3 | **closed** |
| G-P04 | POST not cacheable | Re-fetch | GET + ETag | M4 | **closed** |
| G-P05 | No client bundle reuse | Re-fetch | sessionStorage | M5 | **closed** |
| G-P06 | Fat payloads | Full entries | Field pruning | M6 | **closed** |
| G-P07 | No CI perf gate | Manual only | performance_budget_test | M7 | **closed** |
| G-P08 | Stale marketing target | ≤5 RTTs | v2 budgets | M8 | **closed** |
| G-P09 | No video proof | Stubs | V-PERF-01 evidence | M9 | **closed** |
| G-P10 | Plugin pages exceed budget | 5–7+ RTTs | Bundle v2 | M1+M2 | **closed** |

## User impact rule

Every row must close **without** new steps for site owners. If a fix requires bake/hydrate/CLI → **reject** and fix the live path instead (P21-D1).

## Locked decisions

See `P21-performance-excellence-plan.md` § Locked decisions (P21-D1–D8).
