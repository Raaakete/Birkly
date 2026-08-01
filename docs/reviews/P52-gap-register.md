# Phase 52 — Gap register (PHP-native SSR)

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** In progress — phase approved 2026-08-01; M1 landed (G52-01..03 closed), M2 landed (G52-04 closed, G52-05/G52-11 partially closed), M3–M5 open

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G52-01 | Engine | No PHP implementation of public template `render()` | **Blocker** | M1 | **Closed (M1)** — `core/birkly_template_engine.php` (`birkly_template_engine_render`), pure PHP, mirrors JS pipeline order |
| G52-02 | Engine | No shared fixture suite proving PHP ≡ JS output | **Blocker** | M1 | **Closed (M1)** — `tests/template-engine/fixtures/*.json` dual-run byte-identical via `tests/template_engine_parity_test.php` + `tests/template-engine/render-fixtures.js` |
| G52-03 | Engine | Style/script brace protection must exist in PHP (P51 regression class) | High | M1 | **Closed (M1)** — `birkly_template_engine_extract_protected_blocks` + V-PHP-11 fixtures (`11-protection.json`) |
| G52-04 | SSR | `project_ssr.php` hard-depends on Node `proc_open` for render | **Blocker** | M2 | **Closed (M2)** — `birkly_project_ssr_render()` calls `birkly_template_engine_render()` in-process; no `proc_open`/Node on render or serve path (Node helpers remain `@deprecated` dead code until M4) |
| G52-05 | SSR | Fallback reason `node_missing` is a product failure mode for localhost | High | M2/M3 | **Partially closed (M2)** — render/serve/runtime status can never emit `node_missing` (only `engine_missing`/`engine_error`/`engine_empty`); remaining slice: retired `node_missing` copy in `admin/js/project-settings.js` + P51 docs mention → M3 |
| G52-06 | Defaults | `render_mode` defaults to `client` — SSR not default for managed projects | High | M3 | Open |
| G52-07 | Ops | Dockerfile installs `nodejs` solely for SSR | High | M3 | Open |
| G52-08 | Ops | Admin/runtime status surfaces Node availability | Medium | M3/M4 | Open |
| G52-09 | Docs | P51 notes / stack docs describe Node as SSR requirement | Medium | M3 | Open |
| G52-10 | Cleanup | `scripts/birkly-ssr-cli.js` + Node helpers remain after cutover | Medium | M4 | Open |
| G52-11 | Local | Localhost PHP-only path not guaranteed for `X-Birkly-Render: server` | **Blocker** | M2/M5 | **Partially closed (M2)** — unit + integration proof: render, runtime status, and full `birkly_project_ssr_serve()` (MISS→HIT) all pass in a `PATH=/usr/bin:/bin` (Node-absent) environment; remaining slice: full localhost HTTP smoke asserting the `X-Birkly-Render: server` header → M5 (V-CI-02) |

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
