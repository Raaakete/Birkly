# Phase 51 — Gap register (SSR & shared template engine)

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** Open — execution 2026-07-31 (M1 engine gaps G51-01 / G51-03 closed)

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G51-01 | Engine | No standalone `birkly-template-engine.js` shared by server + client | High | M1 | **Closed** (M1) |
| G51-02 | Engine | `birkly-client.js` inline v3 diverges from `birkly-client-modules/birkly-render.js` (forms etc.) | High | M1/M5 | Partially closed — shared engine now merges form handling (`{entry for}`, `{public_form}`) + inline `{from…get}` from inline v3; client still embeds its own copy until M5 |
| G51-03 | Engine | Incomplete automated coverage vs TEMPLATE_LANGUAGE_SPEC | High | M1 | **Closed** (M1) — `tests/template-engine/run.js` covers V-ENG-01..10 |
| G51-04 | SSR | `/project/` always static `readfile` — no HTML render path | **Blocker** | M2 | Open |
| G51-05 | SSR | No server data→template pipeline for project HTML | **Blocker** | M2 | Open |
| G51-06 | SSR | No SSR output cache / TTL / invalidation | High | M2 | Open |
| G51-07 | SSR | No documented client fallback on SSR failure | Medium | M2 | Open |
| G51-08 | Settings | `project.json` lacks render_mode / hosting_type / ssr_cache_ttl | High | M3 | Open |
| G51-09 | Settings | Admin Project tab has no SSR controls | High | M3 | Open |
| G51-10 | Settings | External hosting must disable SSR | High | M3 | Open |
| G51-11 | Client | No loading reveal after `birkly:templates-processed` | Medium | M4 | Open |
| G51-12 | Client | No animation holdback utility | Medium | M4 | Open |
| G51-13 | Client | No optional skeleton helper | Low | M4 | Open |
| G51-14 | Decouple | Client still embeds full duplicate engine | High | M5 | Open |
| G51-15 | Docs | ROADMAP / help lack Birkly-hosted SSR project mode | Medium | M6 | Open |
| G51-16 | Security | SSR must escape untrusted field output; cache keys must not leak drafts | High | M2/M6 | Open |

## Resolved (pre-P51 baseline)

| ID | Note |
|----|------|
| — | `birkly-client-modules/birkly-render.js` is CommonJS-capable string engine (test base) |
| — | `core/page_bundle_runner.php` already fetches public collection/entry data server-side |
| — | Hosting modes `none|external|project` exist in `settings/hosting.json` |
| — | Page snapshots / hydrate exist for bake demos — not full project SSR |
