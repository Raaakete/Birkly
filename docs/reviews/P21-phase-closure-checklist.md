# P21 — Phase closure checklist

**Target:** P21-M10 — single human notification  
**Date started:** 2026-07-08  
**Date closed:** 2026-07-08

## Verification matrix

- [x] P21 verification matrix — all rows **pass**

## Test suites

- [x] Birkly `php tests/run.php` green — **142 suites PASS**
- [x] `./scripts/ci-check.sh` green
- [x] `performance_budget_test.php` + bundle tests

## Performance deliverables

- [x] Bundle v2 — `template_function` + `template_functions_meta` in `page_bundle`
- [x] Unified `collectBundleRequests()` — TF prefetch + meta in bundle plan
- [x] Snapshot probe opt-in (`meta name=birkly-snapshot` or `data-birkly-snapshot`)
- [x] GET `page_bundle` + ETag / 304 + `revision` field
- [x] Client `sessionStorage` bundle cache
- [x] Lean list payloads (field pruning)
- [x] Benchmark v2 doc + V-PERF-01 evidence

## User-steps (P21-D1)

- [x] No new mandatory setup for site owners
- [x] Live `birkly-client.js` remains default

## Human notification

- [x] Single digest at P21-M10
