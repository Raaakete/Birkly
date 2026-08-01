# Phase 52 — Gap register (PHP-native SSR)

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** **Closed** — phase approved 2026-08-01; M1–M5 complete 2026-08-01; all gaps closed

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G52-01 | Engine | No PHP implementation of public template `render()` | **Blocker** | M1 | **Closed (M1)** — `core/birkly_template_engine.php` (`birkly_template_engine_render`), pure PHP, mirrors JS pipeline order |
| G52-02 | Engine | No shared fixture suite proving PHP ≡ JS output | **Blocker** | M1 | **Closed (M1)** — `tests/template-engine/fixtures/*.json` dual-run byte-identical via `tests/template_engine_parity_test.php` + `tests/template-engine/render-fixtures.js` |
| G52-03 | Engine | Style/script brace protection must exist in PHP (P51 regression class) | High | M1 | **Closed (M1)** — `birkly_template_engine_extract_protected_blocks` + V-PHP-11 fixtures (`11-protection.json`) |
| G52-04 | SSR | `project_ssr.php` hard-depends on Node `proc_open` for render | **Blocker** | M2 | **Closed (M2)** — `birkly_project_ssr_render()` calls `birkly_template_engine_render()` in-process; no `proc_open`/Node on render or serve path (Node helpers remain `@deprecated` dead code until M4) |
| G52-05 | SSR | Fallback reason `node_missing` is a product failure mode for localhost | High | M2/M3 | **Closed (M3)** — M2 removed `node_missing` from render/serve/runtime status (only `engine_missing`/`engine_error`/`engine_empty`); M3 retired the `node_missing`/`proc_open_disabled`/`cli_missing` copy from `admin/js/project-settings.js` (`ssrRuntimeMessage` handles `engine_missing` only) and marked the P51 docs mention historical |
| G52-06 | Defaults | `render_mode` defaults to `client` — SSR not default for managed projects | High | M3 | **Closed (M3)** — `birkly_project_settings_defaults()` + `birkly_project_ssr_settings()` fallback default to `server`; invalid values fall back to `server`; no migration — saved `"client"` in `settings/project.json` survives load and unrelated saves (V-DEF-01/02) |
| G52-07 | Ops | Dockerfile installs `nodejs` solely for SSR | High | M3 | **Closed (M3)** — `nodejs` removed from apt install, `node --version` build check dropped, Node-requirement comment replaced with PHP-only SSR note (V-OPS-01) |
| G52-08 | Ops | Admin/runtime status surfaces Node availability | Medium | M3/M4 | **Closed (M4)** — runtime status reports PHP engine readiness only (M2), admin copy no longer mentions Node (M3), and the deprecated `birkly_project_ssr_node_binary()`/`birkly_project_ssr_run_node()` dead code is deleted (M4); API `ssr_runtime` shape is `{ready, engine:'php', reason}` with no node/cli keys |
| G52-09 | Docs | P51 notes / stack docs describe Node as SSR requirement | Medium | M3 | **Closed (M3)** — `P52-ssr-notes.md` documents PHP-only SSR for localhost + Docker; `P51-ssr-notes.md` carries a top note marking the Node bridge retired; `admin/docs/snapshots/choose-your-stack.md` clarifies built-in SSR is PHP-only (Node row = external author infrastructure only) |
| G52-10 | Cleanup | `scripts/birkly-ssr-cli.js` + Node helpers remain after cutover | Medium | M4 | **Closed (M4)** — CLI script deleted, `ssr_cli_test.js` deleted, `ci-check.sh` step removed, Node-collect parity block dropped from `tests/project_ssr_test.php`; no `proc_open` on the SSR path; Node remains only for the JS engine SSOT tests + the PHP↔JS fixture parity harness (intentional, plan D4) |
| G52-11 | Local | Localhost PHP-only path not guaranteed for `X-Birkly-Render: server` | **Blocker** | M2/M5 | **Closed (M5)** — localhost `php -S` with Node absent from PATH returns `X-Birkly-Render: server` (V-CI-02); unit/integration Node-absent path already green from M2 |

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
