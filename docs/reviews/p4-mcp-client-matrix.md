# P4-M6 — MCP client compatibility matrix

**Date:** 2026-06-26 (updated P4.1-M5)  
**Endpoint:** `POST /api/mcp_server.php` (standalone MCP endpoint key from Settings → MCP Connections)  
**Bridge:** `mcp-bridge.js` at CMS root (`BIRKLY_URL`, `BIRKLY_MCP_API_KEY`)

## Summary — stdio bridge vs remote connector

| Client | Connection path | Transport | Auth | Status | Notes |
|--------|-----------------|-----------|------|--------|-------|
| **Claude Desktop** | Stdio bridge | `mcp-bridge.js` → HTTP | API key | **Verified** | Primary path; see `docs/mcp-setup-guide.md` Path A |
| **Claude (web/app)** | Remote connector | Direct HTTP MCP | OAuth 2.1 | **Verified** (P4.1-M3) | Paste MCP server URL; DCR + PKCE; see Path B in setup guide |
| **Cursor** | Stdio bridge or HTTP | `mcp-bridge.js` or direct HTTP | API key | **Experimental** | Config varies by version |
| **OpenAI MCP** | Remote connector (future) | HTTP / hosted | TBD | **Emerging** | Spec and clients evolving |
| **curl / scripts** | Direct HTTP | JSON-RPC POST | API key | **Verified** | CI smoke and operator debugging |

## Stdio bridge path (recommended)

**Clients:** Claude Desktop, Cursor (experimental), any stdio MCP host.

**Config:** `claude_desktop_config.json` → `mcpServers.birkly` with `node /path/to/mcp-bridge.js`.

**Env:**

```json
{
  "BIRKLY_URL": "https://your-birkly.example",
  "BIRKLY_MCP_API_KEY": "<endpoint key from MCP Connections tab>"
}
```

Legacy aliases: `HARUCMS_URL`, `HARUCMS_API_KEY`.

**Bridge protocol:** Handles `initialize`, `ping`, and `notifications/initialized` locally; proxies tool calls to `/api/mcp_server.php`.

**Endpoint auth mode:** `api_key` (default) or `both`.

**Smoke test:**

1. Create MCP endpoint (Editor or Viewer role), auth mode **API key**.
2. Copy Claude Desktop config snippet from admin; reveal key.
3. Restart Claude Desktop.
4. Ask: “What Birkly tools do you have?” — expect `list_collections`, `get_collection`, etc.
5. `tools/call` with Viewer key must not create entries; Editor + approval queues writes.

**Evidence:** P4-M6 QA / video V16 (onboarding + MCP inbound); P4.1-M5 bridge polish.

## Remote connector path (OAuth)

**Clients:** Claude.ai custom MCP connectors (web and desktop remote mode).

**Config:** Paste **MCP Server URL** from admin (`…/api/mcp_server.php`) into Claude connector settings. No local bridge.

**Auth:** OAuth bearer token (RFC 9728 + 8414, PKCE, DCR). Endpoint must have auth mode **OAuth** or **Both**.

**Discovery:**

- `GET /.well-known/oauth-protected-resource`
- `GET /.well-known/oauth-authorization-server`
- Unauthenticated MCP requests return `401` + `WWW-Authenticate: Bearer resource_metadata="…"`

**Smoke test:**

1. Create endpoint with auth mode **OAuth** or **Both**.
2. Ensure Birkly is HTTPS-reachable from Claude's broker.
3. Add remote connector in Claude; complete OAuth.
4. `tools/list` via bearer should match API-key tool set for same endpoint role.

**Evidence:** P4.1-M3 `tests/mcp_oauth_test.php`; integration repro Path 3.

**Caveats:**

- `api_key`-only endpoints reject remote connector (by design).
- Subdirectory installs must use full URLs from `birkly_url()`.
- Flat-file token storage — acceptable for self-hosted alpha.

## Cursor — experimental (either path)

Cursor has added MCP server support in some builds.

**Stdio:** Same `mcp-bridge.js` config as Claude Desktop.

**HTTP (experimental):** `{BIRKLY_URL}/api/mcp_server.php` with `X-MCP-API-Key` header (API key path only).

**Caveats:**

- Feature flags and config file locations change between Cursor versions.
- Not guaranteed on cloud VM sandboxes without desktop app.
- Document as **experimental** until a pinned Cursor version is verified.

## OpenAI MCP — emerging

OpenAI and partners are adopting MCP for tool connections. Client availability and auth patterns are **not stable** as of P4.

**Guidance:**

- Treat as **emerging** — retest when OpenAI documents stable HTTP MCP for self-hosted servers.
- Remote connector path likely when OAuth clients mature.
- Do not block P4 on OpenAI client verification.

## curl / HTTP — verified (API key path)

Direct JSON-RPC over HTTP (no bridge):

```bash
export BIRKLY_URL="http://localhost:8080"
export BIRKLY_MCP_API_KEY="your_endpoint_key"

# List tools
curl -sS -X POST "${BIRKLY_URL}/api/mcp_server.php" \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: ${BIRKLY_MCP_API_KEY}" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'
```

**Use cases:** PHPUnit-adjacent smoke, operator debugging, automation without stdio.

Requires endpoint auth mode `api_key` or `both`.

## Auth and errors

| Check | Expected |
|-------|----------|
| Missing key (API key path) | 401 JSON-RPC error |
| Invalid key | 401; lockout after repeated failures (P4-M5) |
| Missing bearer (remote, OAuth mode) | 401 + `WWW-Authenticate` resource metadata |
| Rate limit | 429 after threshold per endpoint |
| Role denied | Tool error; audit log entry without secrets |

## Legacy migration

Installations with MCP keys on old **AI Connections** rows should run:

```bash
php scripts/migrate-mcp-endpoints.php --dry-run
php scripts/migrate-mcp-endpoints.php
```

Legacy keys dual-resolve for 90 days. New setups use **MCP Connections** only.

## Related

- `docs/mcp-setup-guide.md` — Path A (bridge) and Path B (remote connector) walkthroughs
- `01-admin/mcp-connections.md` (Birkly-Docs) — product guide
- `docs/ai/BYOAI-ARCHITECTURE.md` — inbound vs outbound
- `docs/reviews/p4.1-mcp-oauth-threat-model.md` — OAuth threat model
