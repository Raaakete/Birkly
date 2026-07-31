# Phase 51 — Verification matrix

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** In progress 2026-07-31 — V-ENG (M1) passing via `tests/template-engine/run.js`

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
| V-SSR-01 | SSR pipeline module exists and uses shared engine | File + smoke | cms-developer | pending |
| V-SSR-02 | Project HTML under `/project/` can be SSR when mode=server | Integration | cms-developer | pending |
| V-SSR-03 | Data loaded from storage / page_bundle_runner (not fake fixtures only) | Code review | cms-developer | pending |
| V-SSR-04 | Response is fully rendered HTML (no raw `{for each` for covered regions) | Integration | cms-developer | pending |
| V-SSR-05 | On engine/Node failure → original HTML + client fallback | Integration | cms-developer | pending |
| V-SSR-06 | Cache hit within TTL returns cached HTML | Unit/integration | cms-developer | pending |
| V-SSR-07 | Cache miss after TTL recomputes | Unit/integration | cms-developer | pending |
| V-SSR-08 | Entry publish/update invalidates SSR cache | Hook test | cms-developer | pending |
| V-SSR-09 | Draft / unpublished content not leaked via SSR | Security check | cms-developer | pending |
| V-SSR-10 | External hosting / client mode never forces SSR | Integration | cms-developer | pending |

---

## V-SET — Project render settings

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SET-01 | Render mode selector: client / server / auto | Admin UI | frontend-developer | pending |
| V-SET-02 | Hosting type Birkly / External; SSR disabled when External | Admin UI + API | frontend-developer | pending |
| V-SET-03 | Cache TTL field persisted to `project.json` | API | cms-developer | pending |
| V-SET-04 | Manual invalidate clears SSR cache | Admin + API | cms-developer | pending |
| V-SET-05 | Changing render settings persists and can invalidate cache | Integration | cms-developer | pending |
| V-SET-06 | Settings match admin design system (labels, help, spacing) | design-reviewer | design-reviewer | pending |
| V-SET-07 | Non-admin cannot change settings | Auth check | cms-developer | pending |

---

## V-CLI — Client loading helpers

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CLI-01 | Opt-in loading reveal waits for `birkly:templates-processed` | Manual/unit | frontend-developer | pending |
| V-CLI-02 | Without opt-in, behavior unchanged | Regression | frontend-developer | pending |
| V-CLI-03 | Animation holdback delays CSS animations until ready | Manual/unit | frontend-developer | pending |
| V-CLI-04 | Optional skeleton helper API present | Code + demo | frontend-developer | pending |
| V-CLI-05 | Helpers work with client render mode | Manual | qa-verifier | pending |
| V-CLI-06 | Helpers do not break SSR-served pages | Manual | qa-verifier | pending |

---

## V-DEC — Client decoupling

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEC-01 | `birkly-client.js` string render delegates to shared engine | Code review | cms-developer | pending |
| V-DEC-02 | Duplicate parse/render block removed or thin wrapper only | Diff review | cms-developer | pending |
| V-DEC-03 | DOM scan / regions / MutationObserver remain in client | Code review | cms-developer | pending |
| V-DEC-04 | Network fetch / page_bundle remain in client | Code review | cms-developer | pending |
| V-DEC-05 | Events `birkly:region-processed` / `templates-processed` still fire | Test | cms-developer | pending |
| V-DEC-06 | Output parity: client render == server render for fixture suite | Node tests | cms-developer | pending |
| V-DEC-07 | Existing template region CI tests still pass | ci-check | cms-developer | pending |
| V-DEC-08 | Single-script tag still works (engine bundled or loaded) | Smoke | cms-developer | pending |

---

## V-CI — Closure

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | `./scripts/ci-check.sh` green (or documented PHP skip if env lacks PHP) | CI | lead-engineer | pending |
| V-CI-02 | Performance notes: client vs server first paint / CLS documented | Doc | lead-engineer | pending |
| V-CI-03 | Security review notes: escaping, cache, access control | Doc | lead-engineer | pending |
| V-CI-04 | design-reviewer sign-off on project settings UX | Review | design-reviewer | pending |
| V-CI-05 | ROADMAP + gap register closed; human notified once | Closure | lead-engineer | pending |
