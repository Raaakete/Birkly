# Phase 51 — Gap register (SSR & shared template engine)

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** Open — execution 2026-07-31 (M1 engine gaps G51-01 / G51-03 closed; M2 SSR gaps G51-04/05/06/07 closed; M3 settings gaps G51-08/09/10 closed; M4 client gaps G51-11/12/13 closed; G51-16 partial)

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G51-01 | Engine | No standalone `birkly-template-engine.js` shared by server + client | High | M1 | **Closed** (M1) |
| G51-02 | Engine | `birkly-client.js` inline v3 diverges from `birkly-client-modules/birkly-render.js` (forms etc.) | High | M1/M5 | Partially closed — shared engine now merges form handling (`{entry for}`, `{public_form}`) + inline `{from…get}` from inline v3; client still embeds its own copy until M5 |
| G51-03 | Engine | Incomplete automated coverage vs TEMPLATE_LANGUAGE_SPEC | High | M1 | **Closed** (M1) — `tests/template-engine/run.js` covers V-ENG-01..10 |
| G51-04 | SSR | `/project/` always static `readfile` — no HTML render path | **Blocker** | M2 | **Closed** (M2) — `core/project_ssr.php` + `birkly_hosting_serve_public_file()` render project HTML when enabled |
| G51-05 | SSR | No server data→template pipeline for project HTML | **Blocker** | M2 | **Closed** (M2) — `birkly_project_ssr_build_data()` collects requests → `page_bundle_runner` → shared engine via Node CLI (`scripts/birkly-ssr-cli.js`) |
| G51-06 | SSR | No SSR output cache / TTL / invalidation | High | M2 | **Closed** (M2) — `cache/ssr/` keyed by path+query+lang+content revision; TTL + `invalidate_all`/`invalidate_by_prefix`; hooks on entry save/delete/bulk/order |
| G51-07 | SSR | No documented client fallback on SSR failure | Medium | M2 | **Closed** (M2) — Node/engine failure or timeout → static file + `X-Birkly-Render: client-fallback`; see `P51-ssr-notes.md` |
| G51-08 | Settings | `project.json` lacks render_mode / hosting_type / ssr_cache_ttl | High | M3 | **Closed** (M3) — `save_settings` whitelists render_mode/hosting_type + clamps `ssr_cache_ttl` (0–86400) via `birkly_project_ssr_validate_render_settings()`; `overview` returns current values; SSR-affecting saves invalidate cache |
| G51-09 | Settings | Admin Project tab has no SSR controls | High | M3 | **Closed** (M3) — Project tab “Server rendering” card (hosting/render mode selects, TTL, Invalidate SSR cache button) in `admin/templates/settings.html` + `admin/js/project-settings.js`; new `invalidate_ssr_cache` API action |
| G51-10 | Settings | External hosting must disable SSR | High | M3 | **Closed** (M3) — API gating unchanged (`hosting_type=external` ⇒ `birkly_project_ssr_enabled()` false); UI disables render mode + TTL + invalidate and shows external note when hosting=External |
| G51-11 | Client | No loading reveal after `birkly:templates-processed` | Medium | M4 | **Closed** (M4) — opt-in `data-birkly-loading`/`data-birkly-reveal`; `birkly-ready` added on `birkly:templates-processed`, SSR marker (microtask), or 8s safety timeout. Source `birkly-client-modules/birkly-loading.js`, inlined in `birkly-client.js`, exposed as `Birkly.loading` |
| G51-12 | Client | No animation holdback utility | Medium | M4 | **Closed** (M4) — opt-in `data-birkly-hold-animations` freezes CSS `animation`/`transition` via `:not(.birkly-ready)` selectors until ready |
| G51-13 | Client | No optional skeleton helper | Low | M4 | **Closed** (M4) — `data-birkly-skeleton` shimmer + `Birkly.loading.showSkeleton/hideSkeleton`; cleared on `birkly:region-processed` / templates-processed. Tests `tests/birkly_loading_test.js` (V-CLI-01..06) |
| G51-14 | Decouple | Client still embeds full duplicate engine | High | M5 | Open |
| G51-15 | Docs | ROADMAP / help lack Birkly-hosted SSR project mode | Medium | M6 | Open |
| G51-16 | Security | SSR must escape untrusted field output; cache keys must not leak drafts | High | M2/M6 | Partially closed (M2) — engine escapes untrusted fields; SSR data uses published-only public read APIs; cache key includes content revision. Full security review remains M6 |

## Resolved (pre-P51 baseline)

| ID | Note |
|----|------|
| — | `birkly-client-modules/birkly-render.js` is CommonJS-capable string engine (test base) |
| — | `core/page_bundle_runner.php` already fetches public collection/entry data server-side |
| — | Hosting modes `none|external|project` exist in `settings/hosting.json` |
| — | Page snapshots / hydrate exist for bake demos — not full project SSR |
