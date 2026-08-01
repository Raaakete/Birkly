# Phase 52 — PHP-native SSR (remove Node runtime)

**Status:** Complete 2026-08-01 — M1–M5 executed; verification matrix green  
**Date:** 2026-08-01  
**Driver:** Product — SSR must be the default for Birkly-managed/local projects; PHP is the only accepted built-in host complexity. Node as an SSR runtime is a mission mismatch (“no libraries / frameworks / dependencies” for authors and hosts alike).

**Artifacts:** `P52-gap-register.md` · `P52-verification-matrix.md` · `P52-lead-engineer-handoff.md`

---

## Problem statement

1. **Node is a second runtime** — P51 SSR shells out to Node (`proc_open` → `scripts/birkly-ssr-cli.js` → `birkly-template-engine.js`). Docker and localhost need `nodejs` for server render. Without Node, mode=server silently falls back to client.
2. **Default is client** — `render_mode` defaults to `client`, so even with Node installed, new/local projects do not get SSR unless operators opt in.
3. **Mission friction** — Birkly already requires PHP for local CMS. Adding Node is one complexity too many. Marketing and product intent: host stack = PHP only; authors ship HTML/CSS + Birkly client, never npm.
4. **Cold-path cost** — Spawning Node per MISS is slower than an in-process PHP render; HIT path is fine either way.

---

## Locked product decisions (propose → confirm with human)

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **SSR runtime = PHP only** | One built-in host complexity. Remove Node from Docker and from the SSR serve path. |
| D2 | **Default `render_mode` = `server`** when `hosting_type=birkly` (local + Birkly-hosted project sites) | “Server-side is the default if possible.” External hosting stays client-only (no Birkly PHP in front). |
| D3 | **Browser engine stays JavaScript** | `birkly-template-engine.js` remains the client string engine (inlined into `birkly-client.js`). Authors still load one Birkly script — not an npm dependency. |
| D4 | **Two implementations, one grammar SSOT** | TEMPLATE_LANGUAGE_SPEC + shared fixture suite define correctness. PHP `render` must match JS `render` on fixtures (parity is the contract). |
| D5 | **No PHP extensions** (no V8Js, no FFI-to-Node) | Pure PHP 8.x. No new host packages beyond what Birkly already needs. |
| D6 | **Template author syntax unchanged** | No language changes in this phase. |
| D7 | **Client fallback remains** | Engine/timeout/data errors → serve raw HTML + client render (`X-Birkly-Render: client-fallback`). Fallback reason is never `node_missing`. |

---

## Goals

| Goal | Success |
|------|---------|
| G1 — PHP template engine | `core/birkly_template_engine.php` (or equivalent) implements `birkly_template_engine_render($html, $data, $options)` covering the same SPEC subset as JS `render()` |
| G2 — Wire SSR to PHP | `core/project_ssr.php` calls PHP render in-process; Node CLI unused on the serve path |
| G3 — Localhost SSR without Node | `php -S` / Docker with PHP only → `X-Birkly-Render: server` for project HTML when mode=server/auto |
| G4 — Default server | New and reset defaults: `render_mode=server` for Birkly hosting; admin copy reflects “recommended / default” |
| G5 — Remove Node ops surface | Dockerfile drops `nodejs`; docs/admin status drop `node_missing`; optional deprecate `BIRKLY_NODE_BIN` |
| G6 — Parity | Shared fixtures: JS engine vs PHP engine identical HTML output |

**Out of scope:** Third-party frontend frameworks; static export/zip; Studio `/site/` rewrite; changing template grammar; rewriting `birkly-client.js` DOM/fetch layer; requiring authors to install anything.

---

## Architecture (target)

```
TEMPLATE_LANGUAGE_SPEC.md          ← grammar SSOT (docs)
tests/template-engine/fixtures/    ← shared HTML/data/expected (or dual runners)

birkly-template-engine.js          ← browser / client string engine (unchanged role)
        └─ inlined into birkly-client.js (sync script stays)

core/birkly_template_engine.php    ← NEW: PHP port of string render()
        ▲
        │ in-process call
        │
core/project_ssr.php               ← collect (PHP) → page_bundle → PHP render → cache
        ▲
core/hosting_routing.php           ← unchanged hook

settings/project.json
  render_mode: server (default) | client | auto
  hosting_type: birkly | external
  ssr_cache_ttl: seconds
```

**Retire / demote:**

| Artifact | Fate |
|----------|------|
| `scripts/birkly-ssr-cli.js` | Remove from serve path; keep briefly for parity harness if useful, then delete in M4 |
| Dockerfile `nodejs` | Remove in M3 |
| `birkly_project_ssr_node_binary()` / `run_node` | Delete or dead-code after cutover |
| Admin `ssr_runtime.node_*` | Replace with PHP engine readiness / last error |

**Keep unchanged:**

- Cache layout `cache/ssr/`, TTL, invalidation hooks
- `trusted_ssr` page-bundle reads
- Style/script brace protection (must port — P51 regression)
- `{entry for}` / public form → hydration placeholders
- Escaping rules (escape by default; trusted `content`/`html` paths)

---

## Port scope (what PHP must implement)

Mirror the JS `render()` pipeline order:

1. `extractProtectedBlocks` / restore — `<style>` / `<script>` bodies
2. `processEntryForBlocks` — `{entry for}`
3. `processPublicFormBlocks` — `{public_form}`
4. `processFromGetBlocks` / inline from-get
5. `processForLoops` (filters, order, limit, sub-collections)
6. `processIfBlocks`
7. `processCountVariables`
8. `processVariables` (incl. date `format`, nested paths, escaping)

**May stay JS-only (not required for SSR):** region DOM discovery helpers (`discoverPageRegions`, default region templates used only by client Light DOM). PHP SSR renders full page HTML strings the same way the Node CLI did — it does not need DOM APIs.

**Already PHP:** `birkly_project_ssr_collect_requests()` — keep; optionally converge with engine `collectBundleRequests` later if drift appears.

---

## Milestones

### P52-M1 — PHP template engine + fixture parity (cms-developer)

- Add `core/birkly_template_engine.php` with public `birkly_template_engine_render()`.
- Port behavior from `birkly-template-engine.js` `render()` path (not region/DOM helpers).
- Extract or mirror `tests/template-engine/` cases into a dual runner:
  - Existing `tests/template-engine/run.js` stays green (JS SSOT for browser).
  - New `tests/template_engine_php_test.php` (or similar) runs the **same** fixtures through PHP.
- Parity harness: for each fixture, `JS render === PHP render` (byte-stable or documented whitespace normalize if needed — prefer exact).
- Include V-ENG-11 class case: CSS/JS brace protection.
- Matrix: V-PHP-01–12

### P52-M2 — SSR cutover to PHP (cms-developer)

- `birkly_project_ssr_render()` calls PHP engine in-process (no `proc_open`).
- Preserve headers, meta marker, cache, fallback on exception/timeout.
- Update `tests/project_ssr_test.php`: remove Node binary assumptions; assert server render with PHP-only env.
- Temporary: if `BIRKLY_SSR_ENGINE=node` env exists for emergency rollback during soak — **optional**, remove in M4. Prefer feature flag only if human wants belt-and-suspenders; default path is PHP.
- Matrix: V-CUT-01–08

### P52-M3 — Default server + remove Node from ops (cms-developer + frontend-developer)

- Defaults: `render_mode` → `server` in `birkly_project_settings_defaults()` / SSR settings fallbacks.
- Migration note: existing `project.json` with explicit `client` stays client (do not force-overwrite saved prefs). Only **missing/default** path becomes server.
- Admin UI: Server option labeled as default/recommended; runtime status shows PHP engine (no Node warning).
- Dockerfile: remove `nodejs` install + comments.
- Docs: update `P51-ssr-notes.md` addendum or new `P52-ssr-notes.md`; stack docs that say “PHP/Node”.
- Localdev README / choose-your-stack: localhost SSR = PHP only.
- Matrix: V-DEF-01–06, V-OPS-01–04

### P52-M4 — Delete Node SSR surface (cms-developer)

- Remove serve-path Node helpers; delete or archive `scripts/birkly-ssr-cli.js` if unused.
- CI: no job requires Node for SSR (Node may remain only if other unrelated tests need it — prefer not).
- Clean admin/API `ssr_runtime` shape.
- Matrix: V-DEL-01–04

### P52-M5 — Closure (lead-engineer + qa-verifier)

- Full matrix green; localhost smoke: PHP built-in server, mode=server, no Node on PATH → `X-Birkly-Render: server`.
- Performance note: MISS latency PHP vs old Node spawn (document).
- Security re-check: escaping, draft leak, cache keys.
- ROADMAP → P52 complete; notify human once.
- Matrix: V-CI-01–05

---

## Dependencies / risks

| Risk | Mitigation |
|------|------------|
| PHP/JS drift after ship | Shared fixtures in CI; any grammar change updates both engines + fixtures in one PR |
| Subtle regex/Unicode differences | Port carefully; add fixtures for nested braces, UTF-8 titles, empty loops |
| Large one-shot port | M1 lands engine behind tests before cutover; M2 flips wire only when parity green |
| Existing sites on `client` | Do not migrate saved settings; only change defaults for new/unset |
| Authors confuse “JS client” with “Node dependency” | Docs: browser script ≠ host Node; host = PHP only |

---

## Sequencing vs P51

P51 delivered the product surface (settings, cache, routing, client helpers, shared JS engine). **P52 replaces only the SSR execution backend and flips the default.** Do not reopen template syntax or client loading work unless a parity bug requires it.

---

## Approval checklist (human)

- [x] D1–D7 accepted
- [x] No Node rollback flag — PHP-only after M1 green
- [x] Default = `server` for Birkly hosting only
- [x] Phase approved → lead-engineer executes M1→M5

---

## Suggested first PR after approval

Branch: `cursor/p52-php-template-engine-afa4`  
Scope: M1 only — PHP engine + PHP fixture tests + parity vs JS (no Dockerfile/default flip yet).
