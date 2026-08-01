# Birkly Client Modules

This directory contains the modular components of the Birkly client library.

## Module Structure

### Core Modules (Load Order)

1. **birkly-utils.js** - Core utilities
   - `getNestedValue()` - Nested object access
   - `escapeHtml()` - HTML escaping
   - `simpleHash()` - String hashing
   - `parseQuotedArgs()` - Argument parsing
   - `tfCacheKey()` - Template function cache keys

2. **birkly-date.js** - Date formatting
   - `formatDate()` - Custom date formatting with format codes
   - `formatValueForOutput()` - Smart value formatting (dates, content, etc.)
   - `isDateField()` - Date field detection

3. **birkly-conditionals.js** - Conditional processing
   - `renderConditionals()` - If/else/endif processing
   - `isTruthyTemplateValue()` - Truthiness checks
   - `resolveCollectionEntryRef()` - Collection/entry resolution

4. **birkly-loops.js** - Loop processing
   - `processNestedLoops()` - Nested loop handling
   - `extractLoopContent()` - Loop content extraction

5. **birkly-bundle.js** - Page bundle (M4)
   - `getTemplateCollectionSlugs()` - Collection slugs from template source
   - `collectBundleRequests()` - Build batch read list from DOM + templates
   - `applyBundleResponse()` - Populate `Birkly.pageBundle` cache
   - `loadPageBundle()` - POST to `api/page_bundle.php`
   - `tryLoadPageSnapshot()` - Load publish-time snapshot (inline or `page_snapshot.php`)

6. **birkly-render.js** - Template engine v3 (string-based renderer)
   - Thin re-export of the shared engine — see below.
   - `render(html, data, options)` - Full parse → render pipeline
   - `collectBundleRequests(html)` - Data needs from template source
   - `discoverPageRegions(document, root?, { force? })` - Outermost Light DOM regions (template syntax, `data-birkly-region`, or `birkly-region`); optional scoped root; skips `data-birkly-processed`
   - `buildRegionTemplate` / `getRegionDefaultTemplate` - helpers for `<birkly-region>` entry/list defaults
   - The shared engine is inlined into `birkly-client.js` for production (single
     script tag) from `birkly-template-engine.js` via
     `scripts/sync-template-engine-inline.sh` — see "Inlining" below.

### Shared template engine (`birkly-template-engine.js`)

The canonical string engine now lives at the repo root in
**`birkly-template-engine.js`** (P51-M1). It is the single source of truth for
the Birkly template language and is shared by the client, the
`birkly-render.js` re-export, and (in later P51 milestones) server-side
rendering.

```js
// Node / CommonJS
const BirklyTemplateEngine = require('./birkly-template-engine.js');
BirklyTemplateEngine.render(html, data, options);

// Browser: exposed as globalThis.BirklyTemplateEngine
```

- UMD/CommonJS + browser-global; no third-party deps or build step.
- Public API: `render(html, data, options)`, `collectBundleRequests(html)`,
  `hasTemplateSyntax(html)`, `escapeHtml`, plus region helpers
  (`discoverPageRegions`, `buildRegionTemplate`, `getRegionDefaultTemplate`, …).
- `options.escapeHtml` (default `true`) HTML-escapes untrusted field values;
  rich-content fields (`content` / `html`) remain trusted markup.
- Syntax coverage is verified by `tests/template-engine/run.js` (V-ENG-01..10)
  against `docs/TEMPLATE_LANGUAGE_SPEC.md`.

`birkly-client-modules/birkly-render.js` is retained as a thin re-export
(`module.exports = require('../birkly-template-engine.js')`) so existing tests
and callers keep working.

#### Inlining into `birkly-client.js` (P51-M5)

`birkly-client.js` no longer carries its own copy of the parser/renderer. The
old `BIRKLY_RENDER_V3_INLINE` block is gone; in its place the production client
embeds a **byte-for-byte copy of `birkly-template-engine.js`** between markers:

```js
/* BIRKLY_TEMPLATE_ENGINE_INLINE_BEGIN */
… contents of birkly-template-engine.js (UMD IIFE) …
/* BIRKLY_TEMPLATE_ENGINE_INLINE_END */
```

The IIFE registers `globalThis.BirklyTemplateEngine`; the client then points its
local `Birkly.render` (and `globalThis.Birkly.render`) at it. The client layer
keeps everything else — DOM scanning, network fetch, hydration, events, forms,
loading helpers (M4), `processTemplatesV3`, `<birkly-region>` handling.

- **Source of truth:** `birkly-template-engine.js`. Never hand-edit the inlined
  block.
- **Re-sync:** `bash scripts/sync-template-engine-inline.sh` (plain awk/diff, no
  bundler). `--check` verifies no drift and is wired into `scripts/ci-check.sh`.
- **Parity:** the client inline and the `birkly-render.js` re-export execute the
  same module, so every browser render runs identical code by construction —
  verified by `tests/template-engine/parity_client_server_test.js` (matrix
  **V-DEC-01..08**). SSR (`core/project_ssr.php`) is PHP-native since P52: it
  renders in-process with `core/birkly_template_engine.php`, which is
  fixture-parity-tested byte-for-byte against this module
  (`tests/template_engine_parity_test.php`). The former Node SSR CLI was
  deleted in P52-M4.

### Loading helpers (`birkly-loading.js`, P51-M4)

Additive, **opt-in** helpers so template-first pages can avoid FOUC / premature
animations. Default behavior is unchanged when no opt-in attributes are present.
Canonical source is `birkly-loading.js` (UMD, unit-tested under Node); the same
code is inlined into `birkly-client.js` and exposed as `Birkly.loading`.

Opt-in via static HTML attributes:

| Hook | Effect |
|------|--------|
| `<html data-birkly-loading>` / `<body data-birkly-reveal>` | Hide until ready, then fade in |
| `<html data-birkly-hold-animations>` | Freeze CSS animations/transitions until ready |
| `<any data-birkly-skeleton>` | Shimmer placeholder until that region/page is processed |

*Ready* = first of `birkly:templates-processed`, SSR marker present
(`<meta name="birkly-ssr">` / `<html data-birkly-ssr>` → reveal on microtask),
or an 8 s safety timeout. On ready, `birkly-ready` is added to opt-in elements
(and `<html>`). JS API: `Birkly.loading.reveal()`, `.showSkeleton(el)`,
`.hideSkeleton(el)`, `.isReady()`. Tests: `tests/birkly_loading_test.js`
(V-CLI-01..06). Details: `docs/reviews/P51-ssr-notes.md` → "Client loading
helpers (M4)".

### Hydrate script (baked pages)

**birkly-hydrate.js** — still supported for baked/publish-time pages. Use when HTML is pre-rendered at deploy and you only need interactivity (forms, cart, auth). See file header and [Production optimization](../../admin/docs/snapshots/production-optimization.md) in admin help.

## Usage

### Option 1: Separate Module Files (Development)

Load modules in order before the main file:

```html
<script src="birkly-client-modules/birkly-utils.js"></script>
<script src="birkly-client-modules/birkly-date.js"></script>
<script src="birkly-client-modules/birkly-conditionals.js"></script>
<script src="birkly-client-modules/birkly-loops.js"></script>
<script src="birkly-client-modules/birkly-bundle.js"></script>
<script src="birkly-client.js"></script>
```

### Option 2: Bundled File (Production)

The main `birkly-client.js` file includes all modules inline for backward compatibility.

## Module Pattern

Each module follows this pattern:

```javascript
(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    
    // Check dependencies
    if (!Birkly.utils) {
        console.error('Birkly: utils module must be loaded before this module');
        return;
    }
    
    // Module code
    Birkly.moduleName = {
        // Exported functions
    };
    
})(window.Birkly || (window.Birkly = {}));
```

## Benefits

- **Maintainability**: Clear separation of concerns
- **Testability**: Each module can be tested independently
- **Readability**: Smaller, focused files
- **Reusability**: Modules can be used independently if needed
- **Backward Compatibility**: Main file includes modules inline
