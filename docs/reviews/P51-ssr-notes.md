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

## Client loading helpers (M4)

**Status:** Implemented 2026-07-31 (frontend-developer). Additive, opt-in — the
default page path is byte-for-byte unchanged when none of the data-attributes
below are present. Closes gap register **G51-11, G51-12, G51-13**. Verified by
`tests/birkly_loading_test.js` (matrix **V-CLI-01..06**).

**Source of truth:** `birkly-client-modules/birkly-loading.js` (UMD, unit-tested
under Node). The same implementation is inlined into `birkly-client.js` (marked
`P51-M4` section, before the public API export) so the single bundled client
script needs no extra `<script>` tag. Exposed as `Birkly.loading`.

### Opt-in API (static HTML)

| Hook | Effect |
|------|--------|
| `<html data-birkly-loading>` | Hide the page (`opacity:0`) until ready, then fade it in. |
| `<body data-birkly-reveal>` | Same, applied to `<body>`. |
| `<html data-birkly-hold-animations>` | Freeze CSS `animation`/`transition` on descendants until ready. |
| `<any data-birkly-skeleton>` | Show a shimmer placeholder on that element until its region (or the page) is processed. |

**Ready** = the first of: `birkly:templates-processed` fires · an SSR marker is
present (`<meta name="birkly-ssr">` or `<html data-birkly-ssr>`) → reveal on the
next microtask · an **8 s safety timeout** elapses (page never stays blank).
On ready the helper adds `birkly-ready` to opt-in elements (and `<html>`); the
injected CSS uses `:not(.birkly-ready)` selectors to reveal, resume animations,
and drop skeletons. `birkly:templates-processed` behavior is preserved (the
helper only *listens*).

### JS API

```js
Birkly.loading.reveal();          // force reveal now (idempotent)
Birkly.loading.showSkeleton(el);  // add shimmer placeholder
Birkly.loading.hideSkeleton(el);  // remove shimmer placeholder
Birkly.loading.isReady();         // boolean
```

A single `<style id="birkly-loading-styles">` is injected once (reveal fade +
shimmer keyframes). For **zero-flash** reveal, load `birkly-client.js` in
`<head>` or add this tiny snippet to `<head>` so the hide rule applies before
first paint:

```html
<style>[data-birkly-loading]:not(.birkly-ready){opacity:0}</style>
```

**Matrix V-CLI-01..06 (all PASS):** default no-op path · reveal on
templates-processed · SSR meta microtask short-circuit (no timer) · safety
timeout reveal · animation-holdback release · skeleton lifecycle
(declared → shimmer, `birkly:region-processed` clears, manual show/hide, reveal
clears leftovers).

## Known limitations / follow-ups

- **Invalidation granularity:** content writes call `invalidate_all()` (simple + correct). Per-page/prefix invalidation exists but is not yet wired to specific collections.
- **Per-request Node process:** each cache MISS spawns a Node process (~tens of ms). Acceptable with caching on; a persistent worker is out of scope for P51.
- **Language:** SSR resolves language from `?lang=` only (localStorage is client-side). Multilingual detail pages render the `?lang=` variant.
- **48h production pilot:** enable `render_mode=server` on one internal Birkly-hosted project after merge; monitor `X-Birkly-Render` / fallback rates.

## Performance notes (M6 / V-CI-02)

| Mode | First paint | Layout shift |
|------|-------------|--------------|
| **Client** (`render_mode=client`) | Browser paints raw HTML; template regions fill after `page_bundle` + `processTemplates`. Without opt-in helpers, authors may see brief `{…}` / empty regions. | Possible CLS when loops inject height after fetch. Mitigate with `data-birkly-loading` / `data-birkly-hold-animations` / skeletons (M4). |
| **Server** (`render_mode=server`) | HTML arrives with template syntax already rendered (`X-Birkly-Render: server`). First paint shows content; client only enhances forms/nav. Cache HIT avoids Node spawn. | Lower CLS for content blocks; still use loading helpers if CSS animations should wait for fonts/assets. |
| **Auto** | Same as server when Node + gating succeed; falls back to client with `X-Birkly-Render: client-fallback`. | Same as whichever path ran. |

**Qualitative finding (dev server, 2026-07-31):** SSR MISS for a small list page completed within the 5s Node budget; subsequent HIT returned cached HTML without re-spawn. Formal Lighthouse/browser matrix deferred to post-merge pilot (not a P51 blocker).

## Security notes (M6 / V-CI-03)

| Risk | Mitigation |
|------|------------|
| XSS via field values in SSR HTML | Shared engine `escapeHtml` default `true` for untrusted fields; rich `content`/`html` remain trusted by existing convention (same as client). Covered by V-ENG-09 / V-SSR-09. |
| Draft leakage via SSR/cache | Data built only through public read APIs (`birkly_public_list_entries` / `birkly_public_get_entry`) — published filter. Cache keys include content revision so unpublished edits do not revive stale public HTML incorrectly after publish/unpublish cycles without invalidate. |
| Cache poisoning / stale private HTML | SSR serves public project files only; no session-specific HTML in cache. Admin auth required for settings + invalidate. |
| Node CLI abuse | `proc_open` with `escapeshellarg` path, ~5s timeout, terminate on overrun; stdin JSON only; failure → static fallback (no crash). |
| Unauthorized settings change | `requireAuth('manage_settings')` + CSRF on `save_settings` / `invalidate_ssr_cache` (V-SET-07). |
| External hosting SSR surprise | `hosting_type=external` or hosting `public_site_mode≠project` disables SSR entirely (V-SSR-10 / V-SET-02). |

## Client decoupling (M5)

**Status:** Implemented 2026-07-31 (cms-developer). Closes gap register
**G51-02, G51-14**. Verified by matrix **V-DEC-01..08**
(`tests/template-engine/parity_client_server_test.js`).

`birkly-client.js` previously carried its own duplicated parser/renderer (the
`BIRKLY_RENDER_V3_INLINE` block, ~840 lines). That block is **removed**. The
client now embeds a **byte-for-byte copy of `birkly-template-engine.js`** — the
same module SSR uses — between markers:

```js
/* BIRKLY_TEMPLATE_ENGINE_INLINE_BEGIN */
… birkly-template-engine.js (UMD IIFE) …
/* BIRKLY_TEMPLATE_ENGINE_INLINE_END */
```

- **Approach A (inline/sync):** a single production `<script src="/birkly-client.js">`
  keeps working with **zero HTML changes** — no runtime `require`, no second
  script tag, no bundler. The inlined IIFE registers
  `globalThis.BirklyTemplateEngine`; the client points its local `Birkly.render`
  (and `globalThis.Birkly.render`) at it.
- **Source of truth:** `birkly-template-engine.js`. The inlined block is
  generated, never hand-edited.
- **Sync script:** `scripts/sync-template-engine-inline.sh` (plain awk/diff).
  Write mode refreshes the block; `--check` fails on drift and is wired into
  `scripts/ci-check.sh` (plus `node --check birkly-client.js`).
- **What stays in the client layer:** DOM scanning / region discovery, network
  fetch + page-bundle, hydration, events, forms, loading helpers (M4),
  `processTemplatesV3`, `<birkly-region>` prepare/wrap. Only the template
  *parsing/rendering* logic moved out.
- **Parity:** client, SSR CLI (`scripts/birkly-ssr-cli.js`), `core/project_ssr.php`,
  and the `birkly-render.js` re-export all execute one module, so client and
  server render output is identical **by construction**. The parity suite also
  asserts the inlined bytes equal the engine source and that the old block is
  gone.

### Residual duplication

None in the render engine: there is exactly one engine implementation. The
`birkly-render.js` re-export and the inlined client block both resolve to
`birkly-template-engine.js`. The client keeps a separate, engine-independent
`Birkly.bundle.collectBundleRequests` (DOM-oriented) alongside the engine's
static `collectBundleRequests(html)`; these serve different layers and are not
template-render duplication.
