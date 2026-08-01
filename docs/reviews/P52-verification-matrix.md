# Phase 52 — Verification matrix

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** **Complete** — phase approved 2026-08-01; M1–M5 verified 2026-08-01 (full matrix green)

Legend: **pass** | **fail** | **skip** | **manual** | **pending**

---

## V-PHP — PHP template engine (M1)

Verified 2026-08-01 by `php tests/template_engine_php_test.php` (60/60),
`php tests/template_engine_parity_test.php` (148/148, byte-identical PHP vs JS
on the full fixture set — no whitespace normalization needed), with
`node tests/template-engine/run.js` still green (58/58).

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-PHP-01 | `birkly_template_engine_render($html, $data, $options)` exists | Unit | cms-developer | pass |
| V-PHP-02 | Loops + filters + limit match JS fixtures | PHP + JS dual | cms-developer | pass |
| V-PHP-03 | Ordering: newest / oldest / last-updated / order | Dual | cms-developer | pass |
| V-PHP-04 | from…get block + inline | Dual | cms-developer | pass |
| V-PHP-05 | if / else / has / exist / comparisons | Dual | cms-developer | pass |
| V-PHP-06 | Date `format` filter | Dual | cms-developer | pass |
| V-PHP-07 | Sub-collections `of` / `.current` | Dual | cms-developer | pass |
| V-PHP-08 | System vars: language, query, current_year | Dual | cms-developer | pass |
| V-PHP-09 | HTML escaping + trusted content/html paths | Dual | cms-developer | pass |
| V-PHP-10 | `{entry for}` / public form → placeholders (not executed as PHP) | Dual | cms-developer | pass |
| V-PHP-11 | `<style>` / `<script>` brace bodies preserved | Dual | cms-developer | pass |
| V-PHP-12 | Byte-identical (or approved normalize) PHP vs JS on full fixture set | Parity harness | cms-developer | pass |

---

## V-CUT — SSR cutover (M2)

Verified 2026-08-01 by `php tests/project_ssr_test.php` (51/51, including with
`env PATH=/usr/bin:/bin BIRKLY_NODE_BIN=` so no Node is reachable), plus
`php tests/project_ssr_settings_api_test.php`, `php tests/template_engine_php_test.php`
(60/60) and `php tests/template_engine_parity_test.php` (148/148) still green.

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CUT-01 | Serve path calls PHP engine (no Node on happy path) | Code + integration | cms-developer | **pass** — `birkly_project_ssr_render()` calls `birkly_template_engine_render()` in-process; grep confirms no `run_node`/`node_binary` callers outside the deprecated helpers + optional test parity block |
| V-CUT-02 | `X-Birkly-Render: server` with Node absent from PATH | Integration | cms-developer | **pass** (unit/integration level) — Node-absent subprocess renders + reports `ready=true, engine=php, reason=null`; `birkly_project_ssr_serve()` MISS→HIT succeeds under Node-absent PATH. Header-level HTTP smoke on localhost = M5 (V-CI-02) |
| V-CUT-03 | Engine exception → client-fallback + static HTML | Integration | cms-developer | **pass** — stubbed engine throw → `render()` returns null with `last_error=engine_error`; routing then serves static + `X-Birkly-SSR-Fallback` (code path unchanged) |
| V-CUT-04 | Cache HIT/MISS headers unchanged | Integration | cms-developer | **pass** — cache/send code untouched in M2; serve test asserts MISS render then byte-identical HIT from cache |
| V-CUT-05 | Entry save still invalidates SSR cache | Hook test | cms-developer | **pass** — hooks unchanged (`api/admin_entries.php`, `api/content.php`); manual invalidate still asserted in `project_ssr_settings_api_test.php` |
| V-CUT-06 | Draft/unpublished not leaked | Security | cms-developer | **pass** — data path unchanged in M2: published-only public reads via `page_bundle_runner` (`trusted_ssr`) + `birkly_public_get_entry`; M2 swapped only the render backend |
| V-CUT-07 | External / client mode never SSR | Integration | cms-developer | **pass** — gating assertions green: external hosting_type, client mode, and `public_site_mode=none` all disable SSR |
| V-CUT-08 | `project_ssr_test.php` green without Node | CI | cms-developer | **pass** — full suite green with `PATH=/usr/bin:/bin` (Node only exists outside that PATH on the test host); render smoke is required, Node-collect parity is the only optional SKIP |

---

## V-DEF — Defaults (M3)

Verified 2026-08-01 by `php tests/project_ssr_test.php` (58/58, incl. new
V-DEF-01/02/04/05/06 assertions) plus `php tests/project_ssr_settings_api_test.php`,
`php tests/template_engine_php_test.php` (60/60) and
`php tests/template_engine_parity_test.php` (148/148) still green.

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEF-01 | Fresh defaults: `render_mode=server` | Unit | cms-developer | **pass** — missing `settings/project.json` → `birkly_project_ssr_settings()` returns `server`; invalid values also fall back to `server` |
| V-DEF-02 | Existing saved `client` not overwritten | Migration test | cms-developer | **pass** — `project.json` with `"render_mode":"client"` still loads as client; file bytes keep `"client"`; unrelated `birkly_save_project_settings()` patch does not inject a render_mode |
| V-DEF-03 | Admin UI presents Server as default/recommended | UI | frontend-developer | **pass** — `settings.html` lists `Server-side (default)` first + `selected`; help text says server is the Birkly-hosted default (PHP engine, no Node); Save stays disabled until overview loads (P51 guard intact) |
| V-DEF-04 | `auto` prefers server when PHP engine available | Integration | cms-developer | **pass** — serve smoke in `render_mode=auto` renders server-side via the PHP engine (no client fallback) |
| V-DEF-05 | Birkly hosting + project mode → SSR on by default | Smoke | qa-verifier | **pass** — no `project.json` + `public_site_mode=project` → `birkly_project_ssr_enabled()===true`; full localhost HTTP header smoke remains M5 (V-CI-02) |
| V-DEF-06 | External hosting still cannot SSR | API + UI | cms-developer | **pass** — `hosting_type=external` disables SSR both with explicit `server` and with pure defaults; UI still disables SSR controls for External |

---

## V-OPS — Ops / Docker / docs (M3)

Verified 2026-08-01: `grep -n nodejs Dockerfile` → no matches;
`grep -rn node_missing admin/ api/ core/` → only the negation comment in the
`birkly_project_ssr_runtime_status()` docblock ("`reason` is never
`node_missing`") — no emitters, no admin copy.

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-OPS-01 | Dockerfile has no `nodejs` package | File review | cms-developer | **pass** — `nodejs` removed from apt install, `node --version` check dropped, header comment states PHP-only SSR |
| V-OPS-02 | Admin runtime status has no Node requirement | UI + API | frontend-developer | **pass** — API `ssr_runtime` reports `{ready, engine:'php', reason}` only (M2); `ssrRuntimeMessage()` dropped `node_missing`/`proc_open_disabled`/`cli_missing` copy and only warns on `engine_missing` |
| V-OPS-03 | Docs state PHP-only SSR for localhost + Docker | Docs | docs-writer / cms | **pass** — `P52-ssr-notes.md` (localhost `php -S` + Docker sections), `P51-ssr-notes.md` top note (Node bridge retired), `choose-your-stack.md` built-in-SSR note |
| V-OPS-04 | `node_missing` fallback reason gone from happy diagnostics | Code | cms-developer | **pass** — no `node_missing` in `admin/`, `api/`, `core/`; render path emits only `engine_missing`/`engine_error`/`engine_empty`; remaining mentions are historical docs + deprecated M4-slated helpers |

---

## V-DEL — Delete Node surface (M4)

Verified 2026-08-01: `rg -n "birkly_project_ssr_run_node|birkly_project_ssr_node_binary|birkly-ssr-cli"`
matches nothing in `core/`, `api/`, `admin/`, `scripts/`, `tests/` (only
historical review docs); `rg -n proc_open core/ api/ admin/ scripts/` → only
negation comments in `core/project_ssr.php` and the security blocklist regex
in `admin/template_safe_renderer.php`. `php tests/project_ssr_test.php`
(57/57), `php tests/project_ssr_settings_api_test.php`,
`php tests/template_engine_php_test.php` (60/60),
`php tests/template_engine_parity_test.php` (148/148) and
`bash scripts/ci-check.sh` all green after the deletion.

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEL-01 | No `proc_open` to Node on SSR path | Code grep | cms-developer | **pass** — `birkly_project_ssr_run_node()`/`birkly_project_ssr_node_binary()` deleted from `core/project_ssr.php`; no `proc_open` anywhere on the SSR render/serve path |
| V-DEL-02 | `birkly-ssr-cli.js` removed or clearly non-runtime | Repo | cms-developer | **pass** — `scripts/birkly-ssr-cli.js` deleted (not archived); `tests/template-engine/ssr_cli_test.js` deleted with it |
| V-DEL-03 | `BIRKLY_NODE_BIN` unused / undocumented for SSR | Code + docs | cms-developer | **pass** — no SSR code reads `BIRKLY_NODE_BIN`; the only remaining reader is the PHP↔JS fixture parity harness (`tests/template_engine_parity_test.php`, intentional D4); SSR docs describe the env hook as deleted |
| V-DEL-04 | CI SSR jobs do not install Node for render | CI config | cms-developer | **pass** — `ci-check.sh` dropped the `ssr_cli_test.js` step; remaining Node steps test the browser-SSOT JS engine + client (run.js, parity_client_server, regions, loading), not host Node SSR |

---

## V-CI — Closure (M5)

Verified 2026-08-01 by lead-engineer: full suites green
(`template_engine_php_test` 60/60, `template_engine_parity_test` 148/148,
`project_ssr_test` PASS including `PATH=/usr/bin:/bin`,
`project_ssr_settings_api_test` PASS, `bash scripts/ci-check.sh` PASS).
Localhost smoke: `env PATH=/usr/bin:/bin BIRKLY_NODE_BIN= php -S 127.0.0.1:8765
-t . server-router.php` → `GET /index.html` returns `X-Birkly-Render: server`
(+ MISS then HIT); artifact `p52-m5-localhost-ssr-smoke.txt`. Perf/security notes
in `P52-ssr-notes.md`; ROADMAP marks P52 complete.

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | Full matrix green | qa-verifier | lead-engineer | **pass** — V-PHP, V-CUT, V-DEF, V-OPS, V-DEL, V-CI all pass; suites + `ci-check.sh` green |
| V-CI-02 | Localhost PHP built-in server SSR smoke (no Node) | Manual/script | lead-engineer | **pass** — Node-absent `php -S` serves `X-Birkly-Render: server` + `X-Birkly-SSR-Cache: MISS`/`HIT` + `meta name="birkly-ssr"`; log at `/opt/cursor/artifacts/p52-m5-localhost-ssr-smoke.txt` |
| V-CI-03 | Perf note: PHP MISS vs prior Node spawn | Doc | lead-engineer | **pass** — `P52-ssr-notes.md` Performance section: in-process PHP ~0.013 ms avg vs Node spawn ~22.5 ms on `project/index.html` |
| V-CI-04 | Security note refresh (escape, drafts, cache) | Doc | lead-engineer | **pass** — `P52-ssr-notes.md` Security section (escape, trusted content/html, published-only, cache keys, client-fallback) |
| V-CI-05 | ROADMAP marks P52 complete | ROADMAP | lead-engineer | **pass** — `docs/roadmap/ROADMAP.md` Phase 52 → Complete |
