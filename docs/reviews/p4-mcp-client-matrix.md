# P4-M6 — MCP client compatibility matrix

**Date:** 2026-06-26  
**Endpoint:** `POST /api/mcp_server.php` (standalone MCP endpoint key from Settings → MCP Connections)  
**Bridge:** `mcp-bridge.js` at CMS root (`BIRKLY_URL`, `BIRKLY_MCP_API_KEY`)

## Summary

| Client | Transport | Status | Notes |
|--------|-----------|--------|-------|
| **Claude Desktop** | stdio via `mcp-bridge.js` | **Verified** | Primary alpha path; see `docs/mcp-setup-guide.md` |
| **Cursor** | HTTP MCP (experimental) | **Experimental** | Config varies by version; label unverified in product copy |
| **OpenAI MCP** | HTTP / hosted connectors | **Emerging** | Spec and clients evolving; test before production |
| **curl / scripts** | Direct HTTP JSON-RPC | **Verified** | Useful for CI and debugging |

## Claude Desktop — verified

**Config:** `claude_desktop_config.json` → `mcpServers.birkly` with `node /path/to/mcp-bridge.js`.

**Env:**

```json
{
  "BIRKLY_URL": "https://your-birkly.example",
  "BIRKLY_MCP_API_KEY": "<endpoint key from MCP Connections tab>"
}
```

**Smoke test:**

1. Create MCP endpoint (Editor or Viewer role) in admin.
2. Copy key once; configure bridge.
3. Restart Claude Desktop.
4. Ask: “What Birkly tools do you have?” — expect `list_collections`, `get_collection`, etc.
5. `tools/call` with Viewer key must not create entries; Editor + approval queues writes.

**Evidence:** P4-M6 QA / video V16 (onboarding + MCP inbound).

## Cursor — experimental

Cursor has added MCP server support in some builds (stdio and remote HTTP). Birkly targets **HTTP** at `api/mcp_server.php`.

**Suggested approach (experimental):**

- Prefer `mcp-bridge.js` stdio wrapper if Cursor expects a local command.
- For HTTP-native config, point at `{BIRKLY_URL}/api/mcp_server.php` with `X-MCP-API-Key` header.

**Caveats:**

- Feature flags and config file locations change between Cursor versions.
- Not guaranteed on cloud VM sandboxes without desktop app.
- Document as **experimental** in Birkly-Docs until a pinned Cursor version is verified in matrix.

## OpenAI MCP — emerging

OpenAI and partners are adopting MCP for tool connections. Client availability and auth patterns are **not stable** as of P4.

**Guidance:**

- Treat as **emerging** — retest when OpenAI documents stable HTTP MCP for self-hosted servers.
- Same Birkly endpoint URL and key as other HTTP clients.
- Do not block P4 on OpenAI client verification.

## curl / HTTP — verified

Direct JSON-RPC over HTTP (no bridge):

```bash
export BIRKLY_URL="http://localhost:8080"
export BIRKLY_MCP_API_KEY="your_endpoint_key"

# List tools
curl -sS -X POST "${BIRKLY_URL}/api/mcp_server.php" \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: ${BIRKLY_MCP_API_KEY}" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'

# Call tool (example)
curl -sS -X POST "${BIRKLY_URL}/api/mcp_server.php" \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: ${BIRKLY_MCP_API_KEY}" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"list_collections","arguments":{}},"id":2}'
```

**Use cases:** PHPUnit-adjacent smoke, operator debugging, automation without stdio.

## Auth and errors

| Check | Expected |
|-------|----------|
| Missing key | 401 JSON-RPC error |
| Invalid key | 401; lockout after repeated failures (P4-M5) |
| Rate limit | 429 after threshold per endpoint |
| Role denied | Tool error; audit log entry without secrets |

## Legacy migration

Installations with MCP keys on old **AI Connections** rows (provider MCP) should run:

```bash
php scripts/migrate-mcp-endpoints.php --dry-run
php scripts/migrate-mcp-endpoints.php
```

Legacy keys dual-resolve for 90 days. New setups use **MCP Connections** only.

## Related

- `docs/mcp-setup-guide.md` — Claude Desktop walkthrough
- `01-admin/mcp-connections.md` (Birkly-Docs) — product guide
- `docs/ai/BYOAI-ARCHITECTURE.md` — inbound vs outbound
