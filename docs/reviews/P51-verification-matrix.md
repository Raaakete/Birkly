# Phase 51 — Verification matrix

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** Open 2026-07-31 (P51 execution)

Legend: **pass** | **fail** | **skip** | **manual**

---

## V-ENG — Template language core

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-ENG-01 | `birkly-template-engine.js` exists and exposes a `render` API | File review | cms-developer | open |
| V-ENG-02 | Output placeholders render correctly: `{title}`, `{'field'}` | Unit test | cms-developer | open |
| V-ENG-03 | Loops render correctly: `{for each entry in 'collection'}` with ordering and filters | Unit test | cms-developer | open |
| V-ENG-04 | Single entry blocks render correctly: `{from 'collection' get entry 'slug'}` | Unit test | cms-developer | open |
| V-ENG-05 | Conditions render correctly: `{if entry has 'field'}`, `{if 'collection'.'entry' exist}` | Unit test | cms-developer | open |
| V-ENG-06 | Date filter renders correctly: `{created_at format "F j, Y"}` | Unit test | cms-developer | open |
| V-ENG-07 | Sub-collection loops render correctly: `{for each entry in 'subs' of 'parent'.'slug'}` | Unit test | cms-developer | open |
| V-ENG-08 | System variables render correctly: `{current_year}`, `{current.language}`, `{query.'param'}` | Unit test | cms-developer | open |
| V-ENG-09 | Missing entries/fields fail gracefully without throwing | Unit test | cms-developer | open |
| V-ENG-10 | Output is HTML-escaped by default to prevent injection | Unit test | cms-developer | open |

---

## V-SSR — Server-side renderer

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SSR-01 | Server render pipeline exists and can be triggered per project | Code review | cms-developer | open |
| V-SSR-02 | Project routing resolves the correct project from URL path or domain | Unit test | cms-developer | open |
| V-SSR-03 | Server fetches data directly from Birkly storage APIs | Integration test | cms-developer | open |
| V-SSR-04 | Rendered output matches client-side output for the same page and data | Automated diff | cms-developer | open |
| V-SSR-05 | Server-side rendered page contains no raw `{...}` tags in the response | Automated check | cms-developer | open |
| V-SSR-06 | Cache stores rendered HTML and serves it on subsequent requests | Integration test | cms-developer | open |
| V-SSR-07 | Cache invalidation works when an entry or setting changes | Manual + integration | cms-developer | open |
| V-SSR-08 | SSR falls back to client-side rendering when disabled or on failure | Manual | cms-developer | open |
| V-SSR-09 | External projects cannot enable SSR | Settings test | cms-developer | open |
| V-SSR-10 | SSR handles all documented template syntax without errors | Matrix V-ENG | cms-developer | open |

---

## V-SET — Admin project settings

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SET-01 | Project settings include Hosting selector (Birkly hosted / External) | Manual admin | frontend-developer | open |
| V-SET-02 | Render mode selector shows Client-side / Server-side / Auto | Manual admin | frontend-developer | open |
| V-SET-03 | Server-side option is disabled when Hosting is External | Manual admin | frontend-developer | open |
| V-SET-04 | Cache TTL selector works and persists | Manual admin | frontend-developer | open |
| V-SET-05 | Custom domain field persists and is used for routing | Integration test | cms-developer | open |
| V-SET-06 | Changing render mode invalidates cached renders | Integration test | cms-developer | open |
| V-SET-07 | design-reviewer: settings UI matches admin design system | Review | design-reviewer | open |

---

## V-CLI — Client-side renderer optimization

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CLI-01 | Loading state helper exists and can reveal page after `birkly:templates-processed` | Code review | frontend-developer | open |
| V-CLI-02 | Animation holdback utility delays animations until rendering is done | Manual + code | frontend-developer | open |
| V-CLI-03 | Existing client-side projects work unchanged | Regression test | qa-verifier | open |
| V-CLI-04 | Optional skeleton helper does not break layouts | Manual | frontend-developer | open |
| V-CLI-05 | Client renderer still works on external static hosts | Manual | qa-verifier | open |
| V-CLI-06 | Client renderer still works when opening HTML files locally | Manual | qa-verifier | open |

---

## V-DEC — Client decoupling

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEC-01 | `birkly-client.js` imports `birkly-template-engine.js` | Code review | cms-developer | open |
| V-DEC-02 | Duplicate template parsing logic is removed from client | Code review | cms-developer | open |
| V-DEC-03 | Client output matches server output for the same page and data | Automated diff | cms-developer | open |
| V-DEC-04 | Client-side hydration works on server-rendered pages | Manual | qa-verifier | open |
| V-DEC-05 | Client-side rendering still works on external hosts | Manual | qa-verifier | open |
| V-DEC-06 | Bundle size does not grow significantly | Build check | cms-developer | open |
| V-DEC-07 | All client features (regions, forms, language switcher) still work | Manual + automated | qa-verifier | open |
| V-DEC-08 | No raw template flash in client-side mode after refactor | Manual | qa-verifier | open |

---

## V-PERF — Performance

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-PERF-01 | SSR first paint is faster than client-side for internal projects | Benchmark | lead-engineer | open |
| V-PERF-02 | SSR time-to-first-byte is acceptable under load | Benchmark | lead-engineer | open |
| V-PERF-03 | Client-side perceived load improves with loading helper | Manual | qa-verifier | open |
| V-PERF-04 | Caching reduces server load for repeated requests | Benchmark | lead-engineer | open |

---

## V-SEC — Security

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SEC-01 | SSR output HTML-escapes user content to prevent XSS | Unit test + review | cms-developer | open |
| V-SEC-02 | Server cannot be tricked into rendering arbitrary project files | Penetration test | lead-engineer | open |
| V-SEC-03 | Cache invalidation requires appropriate permissions | Code review | lead-engineer | open |
| V-SEC-04 | External projects cannot access internal SSR pipeline | Integration test | cms-developer | open |

---

## V-CI — Closure

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | `./scripts/ci-check.sh` green | Shell | lead-engineer | open |
| V-CI-02 | New PHPUnit tests pass | CI | cms-developer | open |
| V-CI-03 | Gap register items closed or explicitly deferred | Review | lead-engineer | open |
| V-CI-04 | Pilot project runs successfully on SSR for 48 hours | Manual | lead-engineer | open |
| V-CI-05 | Documentation is complete and reviewed | Review | docs-writer | open |
