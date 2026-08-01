# P52 — PHP-native SSR notes

**Status:** Complete 2026-08-01 (M1–M5)  
**Scope:** Project SSR renders in-process with the PHP template engine; `render_mode` defaults to `server` for Birkly hosting; Node.js removed from the Docker runtime; the Node SSR CLI and its PHP helpers are deleted.  
**Plan:** `P52-php-native-ssr-plan.md` · **Predecessor:** `P51-ssr-notes.md` (cache, `trusted_ssr`, style protection — still valid)

---

## What changed

| Area | P51 | P52 (now) |
|------|-----|-----------|
| Render backend | `proc_open` → Node CLI (`scripts/birkly-ssr-cli.js`) | **In-process PHP** — `birkly_project_ssr_render()` calls `birkly_template_engine_render()` (`core/birkly_template_engine.php`) |
| Default `render_mode` | `client` | **`server`** for Birkly hosting (missing/unset keys only) |
| Docker image | `nodejs` apt package required for SSR | **PHP-only** — no `nodejs`, no `node --version` build check |
| Runtime readiness | Node binary + `proc_open` + CLI file | PHP engine file present (`engine: 'php'`) |
| Fallback reasons | `node_missing`, `node_timeout_or_fail`, … | `engine_missing`, `engine_error`, `engine_empty` — **never** `node_missing` |
| Node SSR surface | CLI script + `birkly_project_ssr_node_binary()` / `birkly_project_ssr_run_node()` + `BIRKLY_NODE_BIN` env | **Deleted (M4)** — no `proc_open` on the SSR path, no Node SSR CLI, no `BIRKLY_NODE_BIN` |

## PHP in-process engine

- `core/birkly_template_engine.php` implements the public template language
  in pure PHP and is fixture-parity-tested byte-for-byte against the shared JS
  engine (`birkly-template-engine.js`) — see `tests/template_engine_parity_test.php`
  (148/148) and `tests/template_engine_php_test.php`.
- The JS engine remains the single source of truth for the **browser**:
  `birkly-client.js` still inlines it. That is a Birkly-owned client script,
  **not** a host Node dependency — authors keep loading
  `<script src="/birkly-client.js"></script>` exactly as before.
- SSR needs no `proc_open`, no subprocess, no timeout budget: a cache MISS is
  a plain PHP function call inside the same request.

## Default server for Birkly hosting

- `birkly_project_settings_defaults()` (`core/project_settings.php`) and the
  fallback defaults in `birkly_project_ssr_settings()` (`core/project_ssr.php`)
  now return `render_mode = 'server'`. Invalid values also fall back to
  `server`, not `client`.
- **No migration, no overwrite:** defaults apply via
  `array_merge(defaults, settings)` — only **missing/unset** keys pick up
  `server`. A `settings/project.json` that already says `"render_mode":"client"`
  stays client after load and after unrelated saves (V-DEF-02, asserted in
  `tests/project_ssr_test.php`).
- Gating is unchanged: SSR still requires `hosting_type=birkly` **and**
  `render_mode ∈ {server, auto}` **and** hosting `public_site_mode=project`.
  External hosting can never SSR (V-DEF-06).
- Admin UI (Settings → Site delivery → Server rendering) lists
  **Server-side (default)** first; Save stays disabled until the overview
  loads, so the markup default can never clobber a saved `client` (P51 guard,
  unchanged).

## PHP-only SSR — localhost + Docker

```bash
# localhost: PHP built-in server, no Node anywhere on PATH required
php -S localhost:8080 -t . server-router.php
# defaults now render project pages server-side (X-Birkly-Render: server)

# force a mode without touching settings/project.json
BIRKLY_SSR_RENDER_MODE=client php -S localhost:8080 -t . server-router.php
```

- Docker: `Dockerfile` installs nginx/php extensions only — the `nodejs`
  package and the `node --version` build check are gone. SSR works in the
  image out of the box.
- `tests/project_ssr_test.php` proves render, runtime status, and the full
  `birkly_project_ssr_serve()` MISS→HIT path in a Node-absent
  (`PATH=/usr/bin:/bin`) environment.

## Fallback behavior (unchanged shape, new reasons)

Any engine failure still returns `null` from the render path so the caller
serves the original static file and the browser client renders normally:

- `X-Birkly-Render: client-fallback` + `X-Birkly-SSR-Fallback: <reason>`
- Possible reasons: `engine_missing`, `engine_error`, `engine_empty`.
  `node_missing` / `node_timeout_or_fail` no longer exist.
- Admin runtime note (`ssrRuntimeMessage()` in `admin/js/project-settings.js`)
  only warns when the PHP engine is missing — all Node-era copy is removed.

## Still valid from P51 (see `P51-ssr-notes.md`)

- **Cache:** `cache/ssr/{hash}.html` + meta, key includes content revision,
  TTL from `ssr_cache_ttl`, invalidation hooks on entry writes + manual
  invalidate.
- **`trusted_ssr`:** data loads through `birkly_run_page_bundle_requests(...,
  ['trusted_ssr' => true])` — published-only public reads, no draft leakage.
- **Style/script protection:** `<style>`/`<script>` brace bodies are extracted
  before templating and restored after (V-PHP-11 in the PHP engine).
- **Settings persistence + admin Save guard.**

## Node SSR surface deleted (M4)

- The Node SSR CLI (formerly at `scripts/`), its PHP helpers
  (`…_node_binary()`, `…_run_node()`), the `BIRKLY_NODE_BIN` env hook, and the
  CLI smoke test + its `ci-check.sh` step are all deleted. `core/project_ssr.php`
  contains no `proc_open` and never shells out.
- The optional Node-collect parity block in `tests/project_ssr_test.php` went
  with the helpers — PHP `collect_requests` assertions remain required.
- Node stays in CI **only** for the JS engine SSOT tests
  (`tests/template-engine/run.js`, `parity_client_server_test.js`, region/client
  unit tests) and the PHP↔JS fixture parity harness
  (`tests/template_engine_parity_test.php`, intentional per plan D4). None of
  these run on the serve path; the Docker runtime has no Node (M3).

## Performance (M5)

Measured on this host against `project/index.html` (same template bytes):

| Path | Approx. cost |
|------|----------------|
| **PHP in-process** `birkly_template_engine_render()` (avg of 50 warm runs) | **~0.013 ms** |
| **Prior Node spawn** (`spawnSync` → require engine → `render`) | **~22.5 ms** |

Cache **HIT** path is unchanged (read file + send headers) — dominant win is
MISS: no process spawn, no JSON stdin bridge, no wall-clock CLI timeout budget.
Absolute numbers vary by hardware/template size; the order-of-magnitude gap
(in-process µs–ms vs spawn tens of ms) is the product-relevant result.

## Security (M5 refresh)

| Concern | Status |
|---------|--------|
| HTML escaping | Untrusted field values escaped by default in both engines; paths containing `content` / `html` stay trusted HTML by convention (parity fixtures cover XSS-style titles) |
| Draft / unpublished leak | Data still loads only through published public reads (`page_bundle_runner` + `trusted_ssr`); drafts never enter the SSR data bag |
| Cache key isolation | Key = `sha256(path + language + content-revision + relevant query)`; content edits bust revision; `utm_*` ignored; TTL + invalidate-on-save unchanged |
| Fallback | Engine failures serve static HTML + `X-Birkly-Render: client-fallback` — never a half-rendered privileged payload |
| Host surface | No `proc_open` / Node on the SSR path — smaller attack surface than P51 |

## Localhost smoke (M5 / V-CI-02)

```bash
env PATH=/usr/bin:/bin BIRKLY_NODE_BIN= php -S 127.0.0.1:8765 -t . server-router.php
curl -sI http://127.0.0.1:8765/index.html
# → X-Birkly-Render: server
# → X-Birkly-SSR-Cache: MISS  (then HIT on repeat)
```

Verified 2026-08-01 with default `render_mode=server` (no `project.json`),
`public_site_mode=project`, Node absent from PATH. Artifact:
`p52-m5-localhost-ssr-smoke.txt`.
