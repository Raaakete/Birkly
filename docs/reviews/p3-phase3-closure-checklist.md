# Phase 3 closure checklist

**Date:** 2026-06-25  
**Lead:** lead-engineer (autonomous execution per roadmap approval 2026-06-25)

## Milestones

| Milestone | Status | Evidence |
|-----------|--------|----------|
| P3-M1 Baseline gate | complete | Human approved 2026-06-25 |
| P3-M2 AI security & RBAC | complete | PR #32, #33; `ai_permissions_test.php` |
| P3-M3 BYOAI context | complete | `docs/ai/birkly-context-pack.md`, `ai_context_pack_test.php` |
| P3-M4 Structured AI chat | complete | `core/ai_tool_registry.php`, `ai_tool_registry_test.php` |
| P3-M5 MCP per-connection keys | complete | `mcp_api_key` on create, rotate UI, `mcp-bridge.js` |
| P3-M6 Automation plugin | complete | `plugins/automation/`, `automation_plugin_test.php` |
| P3-M7 Automation E2E | complete | Builder UI, cron CLI, README |
| P3-M8 User plugin | complete | `site_users` routing, `user_plugin_permissions_test.php` |
| P3-M9 Production hardening | complete | `p3-m9-backup-notes.md`, login session regen |
| P3-M10 Feedback | complete | `api/feedback.php`, `feedback_test.php` |
| P3-M11 Help v1 | complete | `help-manifest.json`, contextual help JS |
| P3-M12 ContentEditor blocks | complete | `blocks/block-manager.js`, html roundtrip tests |
| P3-M13 Birkly-Website | complete | `cursor/p3-m13-website-alpha-24b5` merged locally |
| P3-M14 Documentation | complete | BYOAI-ARCHITECTURE, Birkly-Docs pack |
| P3-M15 Closure | complete | This checklist, UX matrix, digest |

## CI / tests

- [x] `php tests/run.php` — **21 suites PASS**
- [x] `./scripts/ci-check.sh` — **PASS**
- [x] Suites: `ai_permissions`, `ai_context_pack`, `ai_tool_registry`, `automation_plugin`, `user_plugin_permissions`, `feedback`

## UX matrix

- [x] P3 rows updated in `docs/reviews/ux-flow-matrix.md` (2026-06-25)

## Walkthrough videos (V7–V14)

| ID | Topic | File |
|----|-------|------|
| V7 | AI connect + role + default approval | `docs/reviews/artifacts/V7.mp4` |
| V8 | AI chat read + write approval | `docs/reviews/artifacts/V8.mp4` |
| V9 | MCP external connect | `docs/reviews/artifacts/V9.mp4` |
| V10 | Automation create → trigger → log | `docs/reviews/artifacts/V10.mp4` |
| V11 | User plugin register → entry | `docs/reviews/artifacts/V11.mp4` |
| V12 | Feedback submit → admin | `docs/reviews/artifacts/V12.mp4` |
| V13 | Help search + contextual link | `docs/reviews/artifacts/V13.mp4` |
| V14 | ContentEditor block insert round-trip | `docs/reviews/artifacts/V14.mp4` |

## Deferred (human)

- Kekaba production deploy
- Kekaba new pages/collections
- M1-T8 Resend secrets
- M1-T12 branch protection

## Human notification

- [x] Daily digest with Phase 3 COMPLETE section (`php scripts/send-agent-digest.php`)
