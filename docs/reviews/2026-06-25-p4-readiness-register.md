# P4 readiness register — 2026-06-25

Phase 4 gate (P4-M1). Human approved scope 2026-06-25.

## Locked decisions

| ID | Decision |
|----|----------|
| P4-D01 | MCP inbound decoupled from outbound BYOAI |
| P4-D02 | Storage: `settings/mcp_endpoints.json` + `core/mcp_endpoints.php` |
| P4-D03 | Legacy per-connection `mcp_api_key`: 90-day dual-resolve |
| P4-D04 | Settings → MCP Connections tab; empty state; no auto keys |
| P4-D05 | Multi-profile MCP (role per endpoint) |
| P4-D06 | OAuth = spike only; no P4 production OAuth without P5 approval |
| P4-D07 | Human notified **only at P4-M11** after full internal QA |
| P4-D08 | Kekaba deferred |

## Findings to address in P4

| ID | Area | Finding | Severity | Milestone |
|----|------|---------|----------|-----------|
| P4-R01 | MCP UX | MCP buried in AI Connections; keys tied to outbound rows | P1 | P4-M3, P4-M4 |
| P4-R02 | MCP | Non-technical users expect subscription login = API access | P1 | P4-M7, P4-M9 |
| P4-R03 | UX | Onboarding browser QA was thin; CSRF/CSS hotfixes (#34) | P1 | P4-M2 |
| P4-R04 | UX | Admin routing regression risk (#33) | P1 | P4-M2 |
| P4-R05 | BYOAI | AI chat stuck on deleted connections (fixed #35 — regression test) | P2 | P4-M2 |
| P4-R06 | MCP | No audit log / rotation grace for inbound keys | P2 | P4-M5 |
| P4-R07 | Docs | Inbound vs outbound not split in Birkly-Docs | P2 | P4-M9 |
| P4-R08 | Marketing | Birkly-Website may overstate consumer AI access | P2 | P4-M10 |
| P4-R09 | OAuth | Consumer OAuth bridge speculative | P3 | P4-M8 spike |
| P4-R10 | Clients | Cursor/Windsurf MCP compatibility unverified | P3 | P4-M6 |

## Human sign-off

- [x] Phase 4 scope approved
- [x] End-result-only review model (no per-PR human approval)
- [ ] Kekaba (deferred): ___________

**Reviewer:** Keven **Date:** 2026-06-25
