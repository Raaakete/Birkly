# P4 lead-engineer handoff

**Phase:** 4  
**Approved:** 2026-06-25 (Keven)  
**Lead:** lead-engineer (autonomous execution P4-M1→P4-M11)

## Human model

- Keven approved Phase 4 **once**. No per-milestone or per-PR approval.
- Keven reviews the **finished Phase 4 deliverable** after internal review.
- Daily digests are background only — **not** the handoff signal.
- Handoff signal = **P4-M11-T5** after qa-verifier + design-reviewer sign off the whole phase.

## Locked decisions

| Area | Decision |
|------|----------|
| BYOAI outbound | Unchanged — user API keys; Birkly calls provider |
| Security | `require_approval` default true; role enforcement at execution |
| MCP inbound | `settings/mcp_endpoints.json` + `core/mcp_endpoints.php` |
| Legacy keys | 90-day dual-resolve after `migrate-mcp-endpoints.php` |
| Settings | Dedicated MCP Connections tab; empty default; no install-time keys |
| OAuth | Spike only (P4-M8); no production code without P5 |
| Kekaba | Deferred |

## Architecture

```
Outbound:  admin chat → api/ai_chat.php → provider API
Inbound:   external client → api/mcp_server.php → MCP endpoint profile
Shared:    core/ai_permissions.php, core/ai_tool_registry.php, approval queue
```

## Execution order

| Milestone | Focus |
|-----------|--------|
| P4-M2 | Alpha UX hardening (onboarding E2E, routing HTTP, AI stale connection) |
| P4-M3 | MCP endpoint model + migration |
| P4-M4 | MCP Settings UI |
| P4-M5 | MCP hardening (rate limits, audit, rotation) |
| P4-M6 | MCP client guides + V16 |
| P4-M7 | BYOAI wizard + V17 |
| P4-M8 | OAuth feasibility doc |
| P4-M9 | Docs split + Help v2 |
| P4-M10 | Birkly-Website alpha copy |
| P4-M11 | Closure, matrix, V15–V17, notify human once |

## Branch / PR conventions

- Branch: `cursor/p4-m[N]-<slug>-24b5`
- Title: `[P4-M[N]-T[X]] Description`
- Gates: `./scripts/ci-check.sh`, `php tests/run.php`, qa-verifier, design-reviewer (UI)

## Internal review before P4-M11-T5

- qa-verifier: full matrix browser re-run + all PHPUnit suites
- design-reviewer: P4-M4 MCP tab, P4-M7 wizard copy
- computerUse: V15 onboarding, V16 MCP inbound, V17 BYOAI wizard
- `docs/reviews/p4-phase4-closure-checklist.md` complete

## Current status

| Milestone | Status |
|-----------|--------|
| P4-M1 | complete |
| P4-M2 | complete — HTTP regression suites (T1–T4) |
