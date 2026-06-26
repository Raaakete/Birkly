# P4 readiness register

**Date:** 2026-06-25  
**Phase:** 4 — MCP decoupling + alpha UX hardening  
**Human approval:** Keven (2026-06-25)

## Baseline

| Item | Status |
|------|--------|
| Phase 3 complete on `main` | ✅ |
| CI / `php tests/run.php` | ✅ 23 suites (pre-P4-M2) |
| Post-P3 hotfixes merged | ✅ Onboarding CSRF (#34), admin routing (#33), AI chat picker (#35) |
| UX matrix | 22/22 P3 rows pass; P4 rows added below |

## P4 entry criteria

| Criterion | Met |
|-----------|-----|
| Human approved Phase 4 scope once | ✅ |
| Locked decisions documented in ROADMAP | ✅ |
| Lead handoff doc present | ✅ |
| No blocking P0 from Phase 3 | ✅ |

## Known gaps entering P4

| Gap | Target milestone |
|-----|------------------|
| Onboarding lacked HTTP E2E (CSRF regression) | P4-M2 |
| Admin routing HTTP not in CI | P4-M2 |
| AI chat stale connection after delete | P4-M2 + #35 |
| MCP coupled to outbound AI connection rows | P4-M3/M4 |
| Consumer OAuth UX undefined | P4-M8 spike |

## P4 success criteria (exit)

- Standalone MCP endpoint profiles in Settings
- Legacy MCP keys migrated with 90-day dual-resolve
- Onboarding + routing + AI connection regressions in CI
- Docs split inbound MCP vs outbound BYOAI
- Videos V15–V17; matrix all P4 rows green
- **One human notification** at P4-M11-T5

## Values check

✅ aligned — dependency-free core; MCP/BYOAI logic in API layer; no OAuth ship without explicit P5 approval.
