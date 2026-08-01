# Phase 52 — Gap register (PHP-native SSR)

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** Open — phase approved 2026-08-01; gaps close with M1→M5

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G52-01 | Engine | No PHP implementation of public template `render()` | **Blocker** | M1 | Open |
| G52-02 | Engine | No shared fixture suite proving PHP ≡ JS output | **Blocker** | M1 | Open |
| G52-03 | Engine | Style/script brace protection must exist in PHP (P51 regression class) | High | M1 | Open |
| G52-04 | SSR | `project_ssr.php` hard-depends on Node `proc_open` for render | **Blocker** | M2 | Open |
| G52-05 | SSR | Fallback reason `node_missing` is a product failure mode for localhost | High | M2/M3 | Open |
| G52-06 | Defaults | `render_mode` defaults to `client` — SSR not default for managed projects | High | M3 | Open |
| G52-07 | Ops | Dockerfile installs `nodejs` solely for SSR | High | M3 | Open |
| G52-08 | Ops | Admin/runtime status surfaces Node availability | Medium | M3/M4 | Open |
| G52-09 | Docs | P51 notes / stack docs describe Node as SSR requirement | Medium | M3 | Open |
| G52-10 | Cleanup | `scripts/birkly-ssr-cli.js` + Node helpers remain after cutover | Medium | M4 | Open |
| G52-11 | Local | Localhost PHP-only path not guaranteed for `X-Birkly-Render: server` | **Blocker** | M2/M5 | Open |

## Closed by P51 (context — not reopened)

| ID | Note |
|----|------|
| — | Shared JS engine, settings UI, cache, hosting hook, client loading helpers — keep |
| — | PHP already owns data load (`page_bundle_runner` + `collect_requests`) |

## Explicit non-gaps

| Item | Why not a gap |
|------|----------------|
| Browser still runs JS engine | Intended — client script is Birkly-owned, not a host/author npm dependency |
| Two engine implementations | Accepted cost of D3+D4; parity fixtures are the control |
