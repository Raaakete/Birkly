# Phase 51 — Server-side rendering & shared template engine

**Status:** Approved (2026-07-31) — awaiting human approval, then lead-engineer execution
**Human decision (locked):** Two rendering options only: optimized client-side renderer and server-side renderer. No build-time static export. No third-party frontend frameworks.

**Artifacts:** `P51-gap-register.md` · `P51-verification-matrix.md` · `P51-lead-engineer-handoff.md`

---

## Problem statement

1. **Flash of raw templates** — Birkly project sites use client-side templating. The browser receives plain HTML with raw `{for each...}`, `{from...}`, `{title}`, and `{'field'}` tags, then fetches CMS data and renders. Visitors see raw syntax and empty layouts for a noticeable moment.
2. **Layout shifts and animation issues** — Scripts and animations often run before content resolves, causing broken measurements, late triggers, and jarring transitions.
3. **Client engine is a monolith** — `birkly-client.js` contains the template language, DOM scanning, network fetching, hydration, event handling, and UI enhancements all in one file. This makes it hard to reuse the template logic on the server.
4. **No server-side option** — Birkly-hosted projects cannot be pre-rendered by the server. External projects are limited to client-side rendering.

---

## Goals

| Goal | Success |
|------|---------|
| G1 — Shared template engine | A standalone `birkly-template-engine` module parses and renders the Birkly template language independently of DOM or network code |
| G2 — Server-side renderer | Internal Birkly-hosted projects can be served with fully rendered HTML; output matches the client renderer |
| G3 — Project render settings | Users can choose client-side or server-side rendering per project from the Birkly admin |
| G4 — Client optimization | Client-side rendering gets loading/animation helpers so external projects feel smoother without hacks |
| G5 — Client decoupling | `birkly-client.js` eventually uses the shared template engine, removing duplicate logic |

---

## Architecture

```
┌─────────────────────────────────────────┐
│ Birkly Template Language Core           │
│ (birkly-template-engine)                │
│ - parser                                │
│ - data resolver                         │
│ - output generator                      │
└─────────────────────────────────────────┘
              │                  │
              ▼                  ▼
┌─────────────────────┐  ┌─────────────────────┐
│ Client Renderer     │  │ Server Renderer     │
│ birkly-client.js    │  │ PHP/server module   │
│ - DOM scanning      │  │ - request routing   │
│ - network fetch     │  │ - DB data fetch     │
│ - hydration         │  │ - HTML output         │
│ - events            │  │ - caching             │
└─────────────────────┘  └─────────────────────┘
```

**Design rules:**

- The template language core is the single source of truth for syntax and semantics.
- Both renderers produce identical output for the same HTML and data.
- Platform-specific concerns stay out of the core: DOM, network, caching, and request handling live in the renderer layers.
- Existing client-side projects must continue to work without changes.
- SSR is opt-in per project.

---

## Milestones

### P51-M1 — Extract the template language core

Create a standalone `birkly-template-engine` module by auditing and lifting the relevant logic from `birkly-client.js`.

| Deliverable | Path |
|-------------|------|
| Template language spec review | `docs/TEMPLATE_LANGUAGE_SPEC.md` |
| Shared engine module | `birkly-template-engine.js` (new) |
| Test suite | `tests/template-engine/` (new) |

**Core API (initial):**

```javascript
BirklyTemplateEngine.render(html, data, options)
```

**Must support:**

- Output placeholders: `{title}`, `{'field'}`
- Loops: `{for each entry in 'collection'}`
- Single entries: `{from 'collection' get entry 'slug'}`
- Conditions: `{if entry has 'field'}`, `{if 'collection'.'entry' exist}`
- Filters: `format` for dates
- Ordering: `ordered by newest`, `ordered by oldest`, `ordered by order`
- Sub-collections: `{for each entry in 'subs' of 'parent'.'slug'}`
- System variables: `{current_year}`, `{current.language}`, `{query.'param'}`

**Do not modify `birkly-client.js` yet.**

### P51-M2 — Build the server-side renderer

Add a server module that renders Birkly project pages on request.

| Deliverable | Path |
|-------------|------|
| Server render pipeline | `core/server_render.php` or equivalent |
| Project request routing | `server-router.php` extension |
| Data fetcher | Uses existing Birkly storage APIs |
| Cache layer | File or memory cache with invalidation |

**Behavior:**

1. Visitor requests a project page.
2. Server determines the project from URL path or domain mapping.
3. Server reads the requested HTML file.
4. Server scans for templates and collects required data.
5. Server fetches data from the Birkly database.
6. Server calls `birkly-template-engine` to render the HTML.
7. Server sends finished HTML to the browser.
8. If SSR fails or is disabled, fall back to client-side rendering.

### P51-M3 — Add project render settings

Extend the Birkly admin project settings with a render-mode selector.

| Setting | Options | Default |
|---|---|---|
| Hosting | Birkly hosted / External | Birkly hosted |
| Render mode | Client-side / Server-side / Auto | Auto |
| Cache TTL | Minutes / Hours / Manual | 1 hour |
| Custom domain | Optional domain string | Empty |

**Behavior:**

- Server-side rendering is only available when the project is Birkly-hosted.
- External projects keep client-side rendering.
- Changing the render mode invalidates the rendered cache.

### P51-M4 — Optimize client-side rendering

Add utilities to `birkly-client.js` to make client-side projects feel smoother.

| Deliverable | Notes |
|-------------|-------|
| Loading state helper | Reveal page after `birkly:templates-processed` |
| Animation holdback | Utility to delay animations until rendering is done |
| Skeleton helper | Optional CSS/JS skeleton placeholders |

**Constraints:**

- All changes are additive.
- Existing client-side projects must continue to work unchanged.

### P51-M5 — Decouple `birkly-client.js`

Refactor the client renderer to use the shared template engine.

| Deliverable | Notes |
|-------------|-------|
| Refactored `birkly-client.js` | Uses `birkly-template-engine` for parsing/rendering |
| Client-specific layer | DOM scanning, network fetch, hydration, events |

**Constraints:**

- Feature parity with the original client engine.
- Identical output compared to server renderer.
- No increase in bundle size unless justified.

### P51-M6 — Test, review, and pilot

Run the full verification matrix and pilot the feature on one internal project.

| Deliverable | Notes |
|-------------|-------|
| Test report | Unit, integration, visual regression, performance |
| Security review | SSR injection risks, cache invalidation, access control |
| Pilot result | One internal project running SSR for 48 hours |
| Documentation | Admin docs + author docs |

---

## Constraints

- No third-party frontend frameworks.
- No build-time static export.
- Existing template syntax must not change for website authors.
- Existing client-side projects must continue to work without changes.
- Do not modify `birkly-client.js` until the shared engine and server renderer are stable.

---

## Dependencies

- `birkly-client.js` audit and understanding (M1).
- Existing Birkly storage/collection APIs for server data fetching (M2).
- Admin project settings UI (M3).
- `birkly:templates-processed` event and region-processing internals (M4).
