# P51-M2 — Project SSR pipeline notes

**Status:** Implemented 2026-07-31 (cms-developer)
**Scope:** Optional server-side rendering for Birkly-hosted `project/` sites, built on the shared template engine from M1. No third-party frameworks, no build-time export, template syntax unchanged.

---

## Components

| File | Role |
|------|------|
| `birkly-template-engine.js` | Shared string engine (M1, unchanged). Single source of truth for `render()` + `collectBundleRequests()`. |
| `scripts/birkly-ssr-cli.js` | Tiny Node bridge. Reads one JSON doc on **stdin**, writes one on **stdout**. Modes: `collect` (→ requests) and `render` (→ HTML). Always exits `0`; failures are reported as `{"ok":false,"error":...}` so PHP can fall back. |
| `core/project_ssr.php` | PHP SSR core: settings, gating, data build, Node invocation, cache, serve. |
| `core/hosting_routing.php` | `birkly_hosting_serve_public_file()` tries SSR before static `readfile`. |

## Node CLI bridge

```bash
# render
node scripts/birkly-ssr-cli.js <<< '{"mode":"render","html":"<p>{title}</p>","data":{"title":"Hi"}}'
# -> {"ok":true,"html":"<p>Hi</p>"}

# collect
node scripts/birkly-ssr-cli.js <<< '{"mode":"collect","html":"{for each entry in '\''blog'\''}{title}{endfor}"}'
# -> {"ok":true,"requests":[{"type":"list_entries","into":"blog","collection":"blog"}]}
```

PHP invokes it with `proc_open` (cwd = repo root), writes the payload to stdin, closes stdin, and reads stdout with a **~5s wall-clock timeout** (`birkly_project_ssr_run_node()`). On timeout the child is `proc_terminate`-d and PHP returns `null`.

The Node binary is `node` by default, override with `BIRKLY_NODE_BIN`.

## Request flow

```
GET /blog.html  (Birkly host, public_site_mode=project, render_mode=server|auto)
  → birkly_hosting_dispatch()
    → birkly_hosting_resolve_public_file() → /…/project/blog.html
    → birkly_hosting_serve_public_file()
        → birkly_project_ssr_should_render()?  (enabled + html + under project/)
            yes → birkly_project_ssr_serve()
                    1. collect requests (PHP heuristic mirroring engine)
                    2. compute content revision (page_bundle_helpers)
                    3. cache_get(key) → HIT? send cached (X-Birkly-SSR-Cache: HIT)
                    4. build_data(): page_bundle_runner + published get_entry for detail
                    5. render via Node CLI
                    6. inject <meta name="birkly-ssr" content="1">
                    7. cache_put + send (X-Birkly-Render: server, cache MISS)
            no/failure → static readfile (+ X-Birkly-Render: client-fallback on SSR opt-in failure)
```

Data context mirrors the client (`processTemplatesV3`): `current_year`, `query`, `current.language`,
`site_elements` (slug/key-keyed map), bundle `list_entries`/`get_entry` results, and for detail pages
(`?collection=&slug=`) the current entry flattened at top level + `data.entry`.

## Settings (`settings/project.json`)

```json
{ "render_mode": "client|server|auto", "hosting_type": "birkly|external", "ssr_cache_ttl": 60 }
```

- Defaults: `render_mode=client` (safe), `hosting_type=birkly`, `ssr_cache_ttl=60`.
- SSR runs only when: `hosting_type=birkly` **and** `render_mode ∈ {server, auto}` **and** `hosting.json` `public_site_mode=project`.
- `BIRKLY_SSR_RENDER_MODE` env overrides `render_mode` (handy for tests/staging).
- Full admin UI for these controls is **M3**.

### Enable SSR for testing

```bash
# via env (no file changes)
BIRKLY_SSR_RENDER_MODE=server php -S localhost:8080 -t . server-router.php
# or write settings/project.json { "render_mode": "server" }
```

## Cache

- Location: `cache/ssr/{hash}.html` + `{hash}.meta.json` (gitignored).
- Key: `sha256(path + language + content-revision + relevant query params)`.
  - Content revision comes from `birkly_page_bundle_compute_revision()` (collection meta + entry counts/mtimes), so **content edits bust the cache automatically** even before TTL.
  - Relevant query params: `collection`, `slug`, `id`, `entry` (avoids per-`utm_*` fragmentation).
- TTL from `ssr_cache_ttl`. `ttl=0` disables caching (always fresh render).
- Invalidation:
  - `birkly_project_ssr_invalidate_all()` — hooked into `api/admin_entries.php` (single save + `persistEntryRecord`, covering create/update/delete/bulk) and `api/content.php` (entry order).
  - `birkly_project_ssr_invalidate_by_prefix($pathPrefix)` — targeted purge helper (used by tests; available for future M3 admin actions).

## Fallback & safety

- Any failure (Node missing/broken, timeout, engine error, empty output) → `birkly_project_ssr_serve()` returns `false` → caller serves the original static file. When SSR was opted-in but failed, `X-Birkly-Render: client-fallback` is set so the outcome is observable.
- SSR strips template syntax from the output, so the client's `hasTemplateSyntax()` finds nothing to re-render; the `<meta name="birkly-ssr">` marker + `X-Birkly-Render: server` header make the SSR path observable. `birkly-client.js` is untouched and remains idempotent (enhances forms/nav as usual).
- Only **published** content is rendered: data flows through `birkly_public_list_entries` / `birkly_public_get_entry`, which filter to `status=published`. Drafts are never fetched.
- Untrusted field values are HTML-escaped by the engine (rich `content`/`html` fields remain trusted HTML by existing convention).

## Known limitations / M3+ follow-ups

- **Admin UI (M3):** no Project-tab controls yet for render mode / hosting type / TTL / manual invalidate — settings are file/env only.
- **Invalidation granularity:** content writes call `invalidate_all()` (simple + correct). Per-page/prefix invalidation exists but is not yet wired to specific collections.
- **Per-request Node process:** each cache MISS spawns a Node process (~tens of ms). Acceptable with caching on; a persistent worker is out of scope for M2.
- **Language:** SSR resolves language from `?lang=` only (localStorage is client-side). Multilingual detail pages render the `?lang=` variant.
- **Client decoupling (M5):** `birkly-client.js` still embeds its own engine copy; server and client share the engine file but the client is not yet refactored to require it.
