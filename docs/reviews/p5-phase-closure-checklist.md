# Phase 5 closure checklist

**Phase:** P5 — MCP transport, CIMD OAuth & AI agents  
**Date:** 2026-06-18

## Milestones

| Milestone | Status | Evidence |
|-----------|--------|----------|
| P5-M0 Delete fix | complete | PR #46, `tests/ai_permissions_test.php` |
| P5-M1 CIMD OAuth | complete | `core/mcp_oauth.php`, `tests/mcp_cimd_test.php` |
| P5-M2 `/mcp` Streamable HTTP | complete | `api/mcp.php`, `tests/mcp_streamable_test.php` |
| P5-M3 AI agents | complete | `core/ai_agents.php`, `tests/ai_agents_test.php` |
| P5-M4 Settings AI tab | complete | `admin/templates/settings.html`, `mcp-connections.js` |
| P5-M5 Connector E2E copy | complete | Empty OAuth fields in setup steps |
| P5-M6 Matrix + videos | in progress | `p5-integration-test-matrix.md`; V21–V23 pending record |
| P5-M7 Closure | pending | This checklist |

## CI

- [x] `php tests/run.php` — 38 suites PASS
- [x] `./scripts/ci-check.sh` — PASS

## Manual QA (HTTPS deploy)

- [ ] Claude remote connector: paste `/mcp` URL, empty OAuth fields, OAuth completes
- [ ] ChatGPT connector: same flow

## Sign-off

- [x] qa-verifier — CI 38 suites PASS (rows 1–17)
- [x] design-reviewer — P5-M4 AI tab approved
- [ ] lead-engineer — closure complete (videos pending)
- [ ] Human notification — P5-M7-T5 only
