# Phase 5 — lead-engineer execution handoff

**Start after:** Phase 4.1 complete; human approved Phase 5 scope (2026-06-18).

**Human rule:** Birkly CMS always first. **Kekaba deferred.** Human does **not** approve during P5 — they review the **end result only** after internal QA and integration testing.

**Autonomy:** Execute P5-M0→P5-M7 autonomously. Merge when CI + qa-verifier pass. **Do not email or flag the human for routine PRs, milestones, or mid-phase demos.** Notify human **once** at P5-M7-T5 after: full integration matrix green, videos, closure checklist, qa-verifier phase sign-off, design-reviewer on UI milestones.

**Context:** Phase 4.1 shipped MCP OAuth (DCR) and connector-first UX, but Claude requires HTTPS, delete was broken on subdirectory installs, and the MCP transport URL (`/api/mcp_server.php`) is not the spec-canonical Streamable HTTP path. Phase 5 completes OAuth (CIMD), `/mcp` transport, AI agents with roles, and connector E2E with empty OAuth fields in client apps.

---

## Locked decisions

- D1: Canonical Connector URL → `POST /mcp` (Streamable HTTP); keep `/api/mcp_server.php` for backward compat + stdio bridge
- D2: OAuth on Birkly — DCR + CIMD (`client_id_metadata_document_supported: true`)
- D3: AI agents — named identities with CMS roles; each agent owns an OAuth MCP endpoint
- D4: Settings → single **AI** tab; agents primary; built-in assistant secondary
- D5: Claude/ChatGPT/Cursor — paste Connector URL only; **leave Client ID and Client Secret empty**
- D6: `mcp-bridge.js` dev-only; not in user-facing setup
- require_approval default TRUE; role enforcement unchanged
- Kekaba: DEFERRED

---

## Execution order (P5-M0 → P5-M7)

### P5-M0 — Fix AI connection delete
- `manage_settings` + owner can delete; `birkly_url()` + CSRF in admin JS
- `tests/ai_permissions_test.php`

### P5-M1 — OAuth CIMD
- `core/mcp_oauth.php` — CIMD fetch/cache, SSRF-safe; AS metadata flag
- `tests/mcp_cimd_test.php`

### P5-M2 — `/mcp` Streamable HTTP
- `api/mcp.php`, `core/mcp_protocol.php`; router + `.htaccess`
- OAuth resource URL → `/mcp`
- `tests/mcp_streamable_test.php`

### P5-M3 — AI agents with roles
- `core/ai_agents.php`, `api/ai_agents.php`, `settings/ai_agents.json`
- Agent create → OAuth MCP endpoint with role
- `tests/ai_agents_test.php`

### P5-M4 — Settings → AI tab (agents UI)
- Agents list, create modal with role + description
- Connector URL copy; empty OAuth fields copy
- design-reviewer required

### P5-M5 — Claude/ChatGPT connector E2E
- Integration tests: DCR + CIMD + bearer on `/mcp`
- Admin copy: paste URL only, empty OAuth fields

### P5-M6 — Integration matrix + videos
- `docs/reviews/p5-integration-test-matrix.md`
- Videos V21 (AI agents), V22 (connector E2E), V23 (built-in assistant refresh)
- Birkly-Docs sync

### P5-M7 — Closure
- `docs/reviews/p5-phase-closure-checklist.md`
- **Notify human once at M7-T5**

---

## Branch naming

`cursor/p5-m[N]-<slug>-402e`

## Per task

- Run `./scripts/ci-check.sh` before merge
- Update roadmap checkboxes in batch commits
