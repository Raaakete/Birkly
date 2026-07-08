# P21 — Performance gap register

**Date:** 2026-07-08 · **Phase:** P21 performance excellence  
**Plan:** `docs/reviews/P21-performance-excellence-plan.md`  
**Baseline benchmark:** `docs/benchmarks/kekaba-index-roundtrips.md` (v1 — ≤5 RTT model)

| ID | Gap | Current | Target | Milestone |
|----|-----|---------|--------|-----------|
| G-P01 | Template functions outside bundle | Separate `template_functions_list` + N `template_function_call` POSTs | Included in bundle v2 | M1 |
| G-P02 | Incomplete pre-network scan | Bundle built from loops only; TF/prefetch resolved later | Single scan → full request list | M2 |
| G-P03 | Wasted snapshot RTT | `tryLoadPageSnapshot()` on every page before bundle | Opt-in only (`meta` or attribute) | M3 |
| G-P04 | POST bundle not browser-cacheable | Repeat visits re-fetch full JSON | GET + ETag + revision hash | M4 |
| G-P05 | No client-side bundle reuse | Back button re-fetches | `sessionStorage` keyed by revision | M5 |
| G-P06 | Fat bundle payloads | Full entry objects for every list | Server field pruning (safe defaults) | M6 |
| G-P07 | No CI perf regression gate | Manual DevTools only | `performance_budget_test.php` | M7 |
| G-P08 | Marketing target stale | “≤5 RTTs” doc only | v2 budgets + measured evidence | M8 |
| G-P09 | No video proof | VIDEO.md stubs only | V-PERF-01 recording | M9 |
| G-P10 | Plugin-heavy pages exceed budget | Influencer/social 5–7+ RTTs | ≤3 cold total via bundle v2 | M1+M2 |

## User impact rule

Every row must close **without** new steps for site owners. If a fix requires bake/hydrate/CLI → **reject** and fix the live path instead (P21-D1).

## Locked decisions

See `P21-performance-excellence-plan.md` § Locked decisions (P21-D1–D8).
