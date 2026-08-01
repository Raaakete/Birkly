# Phase 52 — Verification matrix

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** In progress — phase approved 2026-08-01; M1 (V-PHP) and M2 (V-CUT) verified 2026-08-01, M3–M5 pending

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

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEF-01 | Fresh defaults: `render_mode=server` | Unit | cms-developer | pending |
| V-DEF-02 | Existing saved `client` not overwritten | Migration test | cms-developer | pending |
| V-DEF-03 | Admin UI presents Server as default/recommended | UI | frontend-developer | pending |
| V-DEF-04 | `auto` prefers server when PHP engine available | Integration | cms-developer | pending |
| V-DEF-05 | Birkly hosting + project mode → SSR on by default | Smoke | qa-verifier | pending |
| V-DEF-06 | External hosting still cannot SSR | API + UI | cms-developer | pending |

---

## V-OPS — Ops / Docker / docs (M3)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-OPS-01 | Dockerfile has no `nodejs` package | File review | cms-developer | pending |
| V-OPS-02 | Admin runtime status has no Node requirement | UI + API | frontend-developer | pending |
| V-OPS-03 | Docs state PHP-only SSR for localhost + Docker | Docs | docs-writer / cms | pending |
| V-OPS-04 | `node_missing` fallback reason gone from happy diagnostics | Code | cms-developer | pending |

---

## V-DEL — Delete Node surface (M4)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEL-01 | No `proc_open` to Node on SSR path | Code grep | cms-developer | pending |
| V-DEL-02 | `birkly-ssr-cli.js` removed or clearly non-runtime | Repo | cms-developer | pending |
| V-DEL-03 | `BIRKLY_NODE_BIN` unused / undocumented for SSR | Code + docs | cms-developer | pending |
| V-DEL-04 | CI SSR jobs do not install Node for render | CI config | cms-developer | pending |

---

## V-CI — Closure (M5)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | Full matrix green | qa-verifier | qa-verifier | pending |
| V-CI-02 | Localhost PHP built-in server SSR smoke (no Node) | Manual/script | qa-verifier | pending |
| V-CI-03 | Perf note: PHP MISS vs prior Node spawn | Doc | lead-engineer | pending |
| V-CI-04 | Security note refresh (escape, drafts, cache) | Doc | lead-engineer | pending |
| V-CI-05 | ROADMAP marks P52 complete | ROADMAP | lead-engineer | pending |
