# Phase 51 — Verification matrix

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** Green 2026-07-31 (P51-M1→M6) — V-ENG (M1) passing via `tests/template-engine/run.js`; V-SSR (M2) passing via `tests/project_ssr_test.php` + `tests/template-engine/ssr_cli_test.js`; V-CLI (M4) passing via `tests/birkly_loading_test.js`; V-SET (M3) V-SET-01..05/07 passing via admin UI + `tests/project_ssr_settings_api_test.php` (V-SET-06 pending design-reviewer); V-DEC (M5) passing via `tests/template-engine/parity_client_server_test.js` + `scripts/sync-template-engine-inline.sh --check`

Legend: **pass** | **fail** | **skip** | **manual** | **pending**

---

## V-ENG — Shared template engine

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-ENG-01 | `birkly-template-engine.js` exports `render(html, data, options)` | Node require | cms-developer | **pass** |
| V-ENG-02 | Loops: `{for each entry in 'x'}…{endfor}` + filters + limit | tests/template-engine | cms-developer | **pass** |
| V-ENG-03 | Ordering: newest / oldest / last-updated / order | tests/template-engine | cms-developer | **pass** |
| V-ENG-04 | `{from … get entry …}…{endget}` + inline from | tests/template-engine | cms-developer | **pass** |
| V-ENG-05 | Conditionals: if / else / endif, has, exist, comparisons | tests/template-engine | cms-developer | **pass** |
| V-ENG-06 | Date `format` filter on system date fields | tests/template-engine | cms-developer | **pass** |
| V-ENG-07 | Sub-collection loops `of 'parent'.'slug'` / `.current` | tests/template-engine | cms-developer | **pass** |
| V-ENG-08 | System vars: `current.language`, `query.*`, `current_year` | tests/template-engine | cms-developer | **pass** |
| V-ENG-09 | HTML escaping of untrusted field values (no raw script injection) | tests/template-engine | cms-developer | **pass** |
| V-ENG-10 | Edge: missing entry, empty collection, nested loops, malformed braces | tests/template-engine | cms-developer | **pass** |

---

## V-SSR — Server-side renderer

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SSR-01 | SSR pipeline module exists and uses shared engine | File + smoke | cms-developer | **pass** — `core/project_ssr.php` renders via `scripts/birkly-ssr-cli.js` → `birkly-template-engine.js` |
| V-SSR-02 | Project HTML under `/project/` can be SSR when mode=server | Integration | cms-developer | **pass** — `/blog.html` returns `X-Birkly-Render: server` (manual + `project_ssr_test.php`) |
| V-SSR-03 | Data loaded from storage / page_bundle_runner (not fake fixtures only) | Code review | cms-developer | **pass** — `birkly_project_ssr_build_data()` uses `birkly_run_page_bundle_requests` + `birkly_public_get_entry` |
| V-SSR-04 | Response is fully rendered HTML (no raw `{for each` for covered regions) | Integration | cms-developer | **pass** — served HTML has 0 raw `{for each}`; SSR strips template syntax |
| V-SSR-05 | On engine/Node failure → original HTML + client fallback | Integration | cms-developer | **pass** — broken `BIRKLY_NODE_BIN` → static + `X-Birkly-Render: client-fallback` |
| V-SSR-06 | Cache hit within TTL returns cached HTML | Unit/integration | cms-developer | **pass** — 2nd request `X-Birkly-SSR-Cache: HIT`; `project_ssr_test.php` cache roundtrip |
| V-SSR-07 | Cache miss after TTL recomputes | Unit/integration | cms-developer | **pass** — TTL-expiry purge asserted in `project_ssr_test.php` |
| V-SSR-08 | Entry publish/update invalidates SSR cache | Hook test | cms-developer | **pass** — `birkly_project_ssr_invalidate_all()` hooked in `admin_entries.php` (save/persist) + `content.php` (order); revision in cache key also busts on content change |
| V-SSR-09 | Draft / unpublished content not leaked via SSR | Security check | cms-developer | **pass** — data comes only from published-only `birkly_public_*` read APIs; engine escapes untrusted fields |
| V-SSR-10 | External hosting / client mode never forces SSR | Integration | cms-developer | **pass** — `birkly_project_ssr_enabled()` gating asserted (external/client/none) in `project_ssr_test.php` |

---

## V-SET — Project render settings

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SET-01 | Render mode selector: client / server / auto | Admin UI | frontend-developer | **pass** (M3) — `#project-render-mode` select (Client/Server/Auto) in Project tab “Server rendering” card |
| V-SET-02 | Hosting type Birkly / External; SSR disabled when External | Admin UI + API | frontend-developer | **pass** (M3) — `#project-hosting-type` select; `updateRenderingDisabledState()` disables render mode + TTL + invalidate and shows external note; API gating unchanged |
| V-SET-03 | Cache TTL field persisted to `project.json` | API | cms-developer | **pass** (M3) — `tests/project_ssr_settings_api_test.php` persists ttl=300 and reads back via `birkly_project_ssr_settings()` |
| V-SET-04 | Manual invalidate clears SSR cache | Admin + API | cms-developer | **pass** (M3) — `invalidate_ssr_cache` action → `birkly_project_ssr_invalidate_all()`; asserted in settings API test |
| V-SET-05 | Changing render settings persists and can invalidate cache | Integration | cms-developer | **pass** (M3) — `save_settings` persists whitelisted patch and invalidates cache when `ssr_affected`; validation + clamp covered by test |
| V-SET-06 | Settings match admin design system (labels, help, spacing) | design-reviewer | design-reviewer | **pass** (M6) — design-reviewer: approve with nits; polish applied (TTL max-width, aria-live on external note, label dim when disabled, invalidate disabled in client mode) |
| V-SET-07 | Non-admin cannot change settings | Auth check | cms-developer | **pass** (M3) — unauthenticated POST to `save_settings`/`invalidate_ssr_cache` returns 401 (HTTP probe in settings API test); `requireAuth('manage_settings')` guards all writes |

---

## V-CLI — Client loading helpers

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CLI-01 | Opt-in loading reveal waits for `birkly:templates-processed` | Manual/unit | frontend-developer | **pass** (M4) — `tests/birkly_loading_test.js` V-CLI-02: not ready before event, `birkly-ready` added on `birkly:templates-processed` |
| V-CLI-02 | Without opt-in, behavior unchanged | Regression | frontend-developer | **pass** (M4) — V-CLI-01 in test: no opt-in elements ⇒ only an inert `<style>` is injected; full `scripts/ci-check.sh` green (region/engine suites unchanged) |
| V-CLI-03 | Animation holdback delays CSS animations until ready | Manual/unit | frontend-developer | **pass** (M4) — V-CLI-05 in test: `data-birkly-hold-animations` element gets `birkly-ready` only after processing; CSS `:not(.birkly-ready)` freezes `animation`/`transition` |
| V-CLI-04 | Optional skeleton helper API present | Code + demo | frontend-developer | **pass** (M4) — V-CLI-06 in test: `data-birkly-skeleton` shimmer + `showSkeleton`/`hideSkeleton`; cleared on `birkly:region-processed`/templates-processed |
| V-CLI-05 | Helpers work with client render mode | Manual | qa-verifier | **pass with note** (M4) — unit covers client path (event + timeout reveal); live browser spot-check recommended at closure |
| V-CLI-06 | Helpers do not break SSR-served pages | Manual | qa-verifier | **pass with note** (M4) — V-CLI-03 in test: SSR marker (`<meta name="birkly-ssr">`/`data-birkly-ssr`) short-circuits to microtask reveal with no safety timer; live SSR spot-check recommended at closure |

---

## V-DEC — Client decoupling

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEC-01 | `birkly-client.js` string render delegates to shared engine | Code review | cms-developer | **pass** (M5) — local `Birkly.render` + public `Birkly.render()`/`renderEngine` point at `globalThis.BirklyTemplateEngine`; parity test V-DEC-01 asserts single shared module |
| V-DEC-02 | Duplicate parse/render block removed or thin wrapper only | Diff review | cms-developer | **pass** (M5) — `BIRKLY_RENDER_V3_INLINE` block deleted; client now inlines a byte-for-byte copy of `birkly-template-engine.js` between `BIRKLY_TEMPLATE_ENGINE_INLINE_*` markers (parity test V-DEC-02) |
| V-DEC-03 | DOM scan / regions / MutationObserver remain in client | Code review | cms-developer | **pass** (M5) — `processTemplatesV3`, region discovery, observers untouched in client layer |
| V-DEC-04 | Network fetch / page_bundle remain in client | Code review | cms-developer | **pass** (M5) — `fetchContent`, `Birkly.bundle.*`, page-bundle loading unchanged |
| V-DEC-05 | Events `birkly:region-processed` / `templates-processed` still fire | Test | cms-developer | **pass** (M5) — event dispatch unchanged; `tests/template_regions_test.js` + `tests/birkly_region_test.js` green |
| V-DEC-06 | Output parity: client render == server render for fixture suite | Node tests | cms-developer | **pass** (M5) — `tests/template-engine/parity_client_server_test.js` (one shared module ⇒ identical output; fixtures V-DEC-03..08) |
| V-DEC-07 | Existing template region CI tests still pass | ci-check | cms-developer | **pass** (M5) — full `scripts/ci-check.sh` green |
| V-DEC-08 | Single-script tag still works (engine bundled or loaded) | Smoke | cms-developer | **pass** (M5) — engine inlined, zero HTML changes; `node --check birkly-client.js` + sync `--check` drift guard in ci-check |

---

## V-CI — Closure

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | `./scripts/ci-check.sh` green (or documented PHP skip if env lacks PHP) | CI | lead-engineer | **pass** (M6) — full `bash scripts/ci-check.sh` green on closure branch |
| V-CI-02 | Performance notes: client vs server first paint / CLS documented | Doc | lead-engineer | **pass** (M6) — `docs/reviews/P51-ssr-notes.md` Performance notes section |
| V-CI-03 | Security review notes: escaping, cache, access control | Doc | lead-engineer | **pass** (M6) — `docs/reviews/P51-ssr-notes.md` Security notes section |
| V-CI-04 | design-reviewer sign-off on project settings UX | Review | design-reviewer | **pass** (M6) — design-reviewer approve-with-nits; polish committed |
| V-CI-05 | ROADMAP + gap register closed; human notified once | Closure | lead-engineer | **pass** (M6) — ROADMAP + gap register closed; human notified at M6 |
