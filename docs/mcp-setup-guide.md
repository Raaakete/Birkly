# MCP Setup Guide for Birkly

Connect external AI clients to your Birkly instance via **inbound MCP** (Model Context Protocol). This path is separate from **outbound BYOAI** (admin chat with your provider API keys).

## Two paths (don't mix them up)

| Goal | Where to configure |
|------|-------------------|
| Chat **inside** Birkly admin with OpenAI, Claude API, Ollama, etc. | **Settings → AI Connections** (outbound; requires provider **API key**) |
| **Claude Desktop**, Cursor, or scripts calling Birkly tools | **Settings → MCP Connections** (inbound; Birkly-issued **MCP endpoint key** only) |

Consumer subscriptions (ChatGPT Plus, Claude Pro) do **not** replace API keys for outbound chat. For non-technical users, inbound MCP is often the simpler path.

## What is MCP?

MCP lets AI assistants connect to external tools and data sources. Birkly exposes CMS tools at `POST /api/mcp_server.php`, scoped by each endpoint's role.

**Client support (see `docs/reviews/p4-mcp-client-matrix.md`):**

- ✅ **Claude Desktop** — verified (stdio bridge)
- ⚠️ **Cursor** — experimental
- 🔜 **OpenAI MCP** — emerging
- ✅ **curl / HTTP** — verified for testing

## Step 1: Create an MCP endpoint in Birkly

1. Open **Settings → MCP Connections** (dedicated tab — not AI Connections).
2. If the list is empty, use **Add MCP endpoint** (keys are never auto-generated on install).
3. Fill in:
   - **Name** — e.g. "Claude Desktop — Editor"
   - **Role** — Viewer (read-only) or Editor/Author for writes
   - **Require approval** — recommended for production (writes go to approval queue)
4. Save and **copy the MCP API key** when shown (you can rotate later; previous key has 24h grace unless revoked).

The server URL is your Birkly base URL + `/api/mcp_server.php`.

### Upgrading from Phase 3 (legacy per-AI-connection MCP keys)

If you previously used provider **MCP** under AI Connections, migrate to standalone endpoints:

```bash
# From Birkly repo root
php scripts/migrate-mcp-endpoints.php --dry-run   # preview
php scripts/migrate-mcp-endpoints.php             # migrate
```

This copies legacy `mcp_api_key` values from AI connection rows into `settings/mcp_endpoints.json`. Old keys keep working for **90 days** (dual-resolve), then use MCP Connections only.

## Step 2: Install Claude Desktop (recommended client)

1. Download from https://claude.ai/download
2. Install and sign in with your Anthropic account (this is for the **desktop app**, not a Birkly API key)

## Step 3: Configure the bridge

Birkly ships `mcp-bridge.js` at the CMS root. It translates Claude's stdio MCP to Birkly's HTTP endpoint.

### Environment variables

| Variable | Purpose |
|----------|---------|
| `BIRKLY_URL` | Your Birkly base URL (e.g. `http://localhost:8080`) — **preferred name** |
| `BIRKLY_MCP_API_KEY` | Endpoint key from MCP Connections |
| `HARUCMS_URL` / `HARUCMS_API_KEY` | Legacy aliases; still accepted |

### Claude Desktop config

**macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`  
**Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "birkly": {
      "command": "node",
      "args": ["/absolute/path/to/mcp-bridge.js"],
      "env": {
        "BIRKLY_URL": "http://localhost:8080",
        "BIRKLY_MCP_API_KEY": "YOUR_MCP_ENDPOINT_KEY"
      }
    }
  }
}
```

Use the path to `mcp-bridge.js` inside your Birkly installation. On a production server, run the bridge on a machine that can reach `BIRKLY_URL`.

### Step 4: Restart Claude Desktop

Restart completely after editing the config. The MCP server should connect on startup.

### Step 5: Verify

1. Open Claude Desktop → new conversation
2. Ask: "What tools do you have access to?"
3. Expect Birkly tools: `list_collections`, `get_collection`, `list_entries`, `create_entry`, `update_entry`, `get_system_context`, etc.

## Cursor (experimental)

Some Cursor builds support MCP via stdio or HTTP. Try the same `mcp-bridge.js` command as Claude Desktop, or configure HTTP to `{BIRKLY_URL}/api/mcp_server.php` with header `X-MCP-API-Key`. Cursor support is **experimental** — see `docs/reviews/p4-mcp-client-matrix.md`.

## HTTP testing with curl

```bash
export BIRKLY_URL="http://localhost:8080"
export BIRKLY_MCP_API_KEY="your_key"

curl -sS -X POST "${BIRKLY_URL}/api/mcp_server.php" \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: ${BIRKLY_MCP_API_KEY}" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'
```

## Using ChatGPT instead of MCP

ChatGPT does not support Birkly MCP. For in-admin chat:

1. **Settings → AI Connections** → Add **OpenAI** (or another provider)
2. Enter your **OpenAI API key** from the developer dashboard (not ChatGPT Plus login)
3. Use the floating AI button in the admin panel

## Troubleshooting

### MCP server not connecting

1. **Key** — from **MCP Connections**, not AI Connections
2. **URL** — `BIRKLY_URL` must reach your instance (no trailing path except what the bridge adds)
3. **Network** — desktop app must reach the server (firewall, HTTPS cert)
4. **Logs** — `logs/php_errors.log`; MCP audit under `data/mcp_audit/` (no secrets in entries)

### Claude can't see tools

1. Restart Claude Desktop fully
2. Validate JSON config syntax
3. Confirm `node` can run `mcp-bridge.js`
4. Re-copy or rotate MCP key in admin

### Permission errors

1. Check endpoint **role** in MCP Connections
2. Writes with **require approval** appear in the approval queue (same as admin chat)
3. Viewer role cannot call write tools

## Available MCP tools

- **list_collections** — List content collections
- **get_collection** — Collection schema/details
- **list_entries** — Entries in a collection
- **create_entry** / **update_entry** — Writes (may require approval)
- **get_system_context** — Curated CMS context for the model

## Example prompts (Claude Desktop)

- "List all my content collections"
- "Show entries in the blog collection"
- "Create a draft blog post titled Hello World"
- "What fields does the products collection have?"

## Related docs

- `docs/reviews/p4-mcp-client-matrix.md` — client compatibility
- `docs/ai/BYOAI-ARCHITECTURE.md` — inbound vs outbound architecture
- Birkly-Docs: `01-admin/mcp-connections.md`
