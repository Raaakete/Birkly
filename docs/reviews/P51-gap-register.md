# Phase 51 — Gap register (server-side rendering & shared template engine)

**Plan:** `P51-ssr-shared-template-engine-plan.md`  
**Status:** Open 2026-07-31 (P51 execution)

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G51-01 | Template engine | Template parsing/rendering is embedded in `birkly-client.js` and not reusable | High | M1 | Open |
| G51-02 | Template engine | No formal shared module API for server and client | High | M1 | Open |
| G51-03 | Template engine | No test suite that verifies both renderers produce identical output | High | M1/M5 | Open |
| G51-04 | Server renderer | No server-side rendering pipeline for project sites | High | M2 | Open |
| G51-05 | Server renderer | No project routing logic for SSR requests | High | M2 | Open |
| G51-06 | Server renderer | No cache layer for rendered pages | Medium | M2 | Open |
| G51-07 | Server renderer | No fallback to client-side rendering when SSR fails | Medium | M2 | Open |
| G51-08 | Settings | No project-level render mode setting in admin | High | M3 | Open |
| G51-09 | Settings | No domain/path mapping UI for projects | Medium | M3 | Open |
| G51-10 | Settings | No cache invalidation controls | Medium | M3 | Open |
| G51-11 | Client renderer | No loading state helper to hide flash | Medium | M4 | Open |
| G51-12 | Client renderer | No animation holdback utility | Medium | M4 | Open |
| G51-13 | Client renderer | No skeleton placeholder helper | Low | M4 | Open |
| G51-14 | Client renderer | `birkly-client.js` duplicates template logic instead of using shared engine | High | M5 | Open |
| G51-15 | QA | No performance benchmark comparing client-side and server-side | Medium | M6 | Open |
| G51-16 | QA | No security review for SSR injection risks | High | M6 | Open |
| G51-17 | Docs | No admin documentation for render settings | Medium | M6 | Open |
| G51-18 | Docs | No author documentation explaining render modes | Medium | M6 | Open |

## Resolved (pre-P51 baseline)

| ID | Note |
|----|------|
| — | `birkly-client.js` already emits `birkly:templates-processed` for client-side use |
| — | `birkly-client.js` already supports scoped `Birkly.processTemplates({ root })` |
| — | Template language spec exists at `docs/TEMPLATE_LANGUAGE_SPEC.md` |
