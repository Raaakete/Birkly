# Phase 51 — Server-side rendering & shared template engine

**Status:** Complete (2026-07-31) — P51-M1→M6 executed; matrix green  
**Human decision (locked):** Shared JS template engine + optional SSR for Birkly-hosted project sites. No third-party frontend frameworks. No build-time static export / zip download. Template language syntax unchanged for authors. Do not modify `birkly-client.js` until M1 and M2 are stable.

**Artifacts:** `P51-gap-register.md` · `P51-verification-matrix.md` · `P51-lead-engineer-handoff.md`

---

## Problem statement

1. **Duplicated engine risk** — Template parsing/rendering lives primarily inside `birkly-client.js` (inlined v3) with a near-parity module at `birkly-client-modules/birkly-render.js`. There is no first-class shared package both server and client can consume.
2. **Client-only first paint** — `/project/` HTML is served as static files; templates flash as raw `{…}` until `birkly-client.js` fetches data and renders. No project-level SSR path.
3. **No render controls** — Project settings expose visit button / files only; operators cannot choose client vs server render, cache TTL, or invalidate SSR cache.
4. **Client UX gaps** — No first-class loading reveal, animation holdback, or optional skeleton helpers for template-first pages.

---

## Goals

| Goal | Success |
|------|---------|
| G1 — Shared engine | `birkly-template-engine.js` with `BirklyTemplateEngine.render(html, data, options)` covers TEMPLATE_LANGUAGE_SPEC syntax |
| G2 — SSR for hosted projects | Birkly-hosted `/project/` can serve fully rendered HTML via shared engine + storage data |
| G3 — Project render settings | Admin: render mode (client / server / auto), hosting type, cache TTL, manual invalidate |
| G4 — Client polish | Additive loading / animation / skeleton helpers; existing client projects unchanged |
| G5 — Client decoupling | `birkly-client.js` uses shared engine for string render; DOM/fetch/events stay in client |

**Out of scope:** React/Vue/etc., static site export / zip download, template syntax changes, Studio `/site/` plugin rewrite.

---

## Architecture (target)

```
birkly-template-engine.js          ← shared string engine (Node + browser)
        │
        ├─ scripts/birkly-ssr-cli.js   ← Node CLI: stdin/argv JSON → rendered HTML
        │         ▲
        │         │ proc_open / node
        │         │
        ├─ core/project_ssr.php        ← resolve file, collect data (page_bundle_runner),
        │                                 render, cache, fallback marker
        │         ▲
        │         │
        └─ core/hosting_routing.php    ← when HTML + SSR enabled → project_ssr

birkly-client.js                   ← DOM scan, fetch/bundle, processTemplates, events
        └─ uses BirklyTemplateEngine.render after M5

settings/project.json
  render_mode: client|server|auto
  hosting_type: birkly|external   (SSR only when birkly + public_site_mode=project)
  ssr_cache_ttl: seconds
```

**Fallback:** On SSR failure (Node missing, engine error, timeout), serve original HTML and let client render (optionally `X-Birkly-Render: client-fallback`).

**Cache:** `cache/ssr/{projectKey}/{hash}.html` (+ meta JSON). Invalidate on entry/collection writes and admin “Invalidate cache”.

---

## Milestones

### P51-M1 — Shared template engine (cms-developer)

- Audit inlined engine in `birkly-client.js` + `birkly-client-modules/birkly-render.js`.
- Create root `birkly-template-engine.js` exporting:
  - `BirklyTemplateEngine.render(html, data, options)`
  - helpers needed for parity (`collectBundleRequests`, region helpers may stay client-adjacent but string `render` must be shared).
- Cover TEMPLATE_LANGUAGE_SPEC: loops, from…get, conditionals, date `format`, ordering, sub-collections, system variables, `{entry for}` / public forms if present in v3 inline.
- Tests under `tests/template-engine/` for every syntax case + edge cases.
- **Do not** change `birkly-client.js` production path yet (may re-export or symlink for tests only).
- Matrix: V-ENG-01–10

### P51-M2 — Server-side renderer (cms-developer)

- `core/project_ssr.php` (+ helpers): project resolve from path/domain, load HTML from `project/`, build data via `page_bundle_runner` / storage APIs, call shared engine via Node CLI.
- Hook `hosting_routing.php` / public file serve for HTML when SSR enabled.
- Output cache with TTL; invalidation hooks from entry/collection save paths.
- Fallback to raw HTML + client render on failure.
- Matrix: V-SSR-01–10

### P51-M3 — Admin project render settings (frontend-developer + cms-developer)

- Extend `settings/project.json` + `api/project_site.php`.
- Settings → Project UI: render mode, hosting type (disable SSR when external), cache TTL, invalidate button.
- Persist + invalidate cache on change.
- Matrix: V-SET-01–07

### P51-M4 — Client loading / animation helpers (frontend-developer)

- Additive APIs on `birkly-client.js` (or small helper module loaded by client):
  - Reveal after `birkly:templates-processed`
  - Animation holdback until render done
  - Optional skeleton placeholder helper
- Existing projects unchanged without opt-in attrs/classes.
- Matrix: V-CLI-01–06

### P51-M5 — Client decoupling (cms-developer)

- Only after M1+M2 stable: refactor `birkly-client.js` to use `birkly-template-engine.js` for string render.
- Remove duplicated parse/render logic; keep DOM/network/hydration/events in client.
- Parity tests: client vs server same output.
- Matrix: V-DEC-01–08

### P51-M6 — Closure (lead-engineer + qa-verifier + design-reviewer)

- Full verification matrix green.
- Performance notes: client vs server first paint / CLS (documented).
- Security: injection escaping, cache poisoning, access control for SSR.
- Docs + ROADMAP update.
- design-reviewer sign-off on project settings UX.
- Notify human once.
- Matrix: V-CI-01–05

---

## Dependencies

```
M1 (engine + tests) ──► M2 (SSR) ──► M5 (client uses engine)
                     └──► M3 (settings wire to SSR)
M4 (client helpers) can parallel M3 after M2 API shape known
M6 after M1–M5
```

**PR waves (allowed):**

1. Shared engine + server renderer (+ settings if ready)  
2. Client optimization + decoupling + closure

---

## Constraints

- No third-party frontend frameworks.
- No build-time static export / zip download feature.
- Do not change author-facing template language syntax.
- Do not modify `birkly-client.js` until M1 and M2 are stable and tested.
- Prefer Node CLI bridge for PHP→JS engine (no V8js hard dependency); graceful client fallback if Node unavailable.
