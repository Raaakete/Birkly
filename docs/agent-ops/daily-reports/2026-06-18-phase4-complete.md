# Birkly daily — 2026-06-18

## Phase 4 COMPLETE — ready for human review

Phase 4 (P4-M1 through P4-M11) is fully delivered and merged to `main`.

### What shipped

**MCP inbound (decoupled from outbound BYOAI)**
- Standalone endpoint profiles in `settings/mcp_endpoints.json` + `core/mcp_endpoints.php`
- Settings → **MCP Connections** tab (create / rotate / revoke; role + require_approval per endpoint)
- `api/mcp_server.php` resolves endpoint profiles; 90-day dual-resolve for legacy `users/*.json` `mcp_api_key`
- Hardening: per-endpoint rate limits, audit log, 24h rotation grace, failed-auth lockout
- Migration: `scripts/migrate-mcp-endpoints.php`

**BYOAI outbound UX**
- 3-step connection wizard (provider → key → test → role/approval)
- Clear copy: ChatGPT Plus / Claude Pro ≠ API access
- MCP removed from outbound AI Connections add flow

**Docs & website**
- Birkly: `docs/ai/BYOAI-ARCHITECTURE.md`, Help manifest v2, MCP setup guide
- Birkly-Docs PR #4: split `mcp-connections.md` / `ai-connections.md`
- Birkly-Website PR #4: beta copy + privacy audit note

**OAuth**
- Spike doc only (`docs/reviews/p4-oauth-spike.md`) — **no-go** for P4 production OAuth; propose P5 if revisited

### CI / tests

- `./scripts/ci-check.sh` — PASS
- `php tests/run.php` — **29 suites** PASS
- HTTP E2E: onboarding, admin routing, AI delete, MCP initialize/tools/list

### Review artifacts

| Artifact | Path |
|----------|------|
| Closure checklist | `docs/reviews/p4-phase4-closure-checklist.md` |
| UX matrix (28 P4 rows pass) | `docs/reviews/ux-flow-matrix.md` |
| V15 Onboarding | `docs/reviews/artifacts/V15.mp4` |
| V16 MCP inbound | `docs/reviews/artifacts/V16.mp4` |
| V17 BYOAI wizard | `docs/reviews/artifacts/V17.mp4` |
| OAuth spike | `docs/reviews/p4-oauth-spike.md` |

### PRs merged

- Birkly [#38](https://github.com/Raaakete/Birkly/pull/38) — P4-M3–M11 (on top of #37 P4-M2)
- Birkly-Docs [#4](https://github.com/Raaakete/Birkly-Docs/pull/4) — admin docs split
- Birkly-Website [#4](https://github.com/Raaakete/Birkly-Website/pull/4) — beta copy

### Deferred (unchanged)

- Kekaba content + production deploy
- OAuth implementation (P5 if spike revisits)
- Consumer subscription bridge without API scope

---
**Values check:** ✅ aligned — dependency-free core; MCP inbound decoupled; `require_approval` default preserved  
**Blocked / needs you:** Review end result when convenient; configure M1-T8 Resend secrets for automated email digests  
**Today:** Phase 4 complete — awaiting human review
