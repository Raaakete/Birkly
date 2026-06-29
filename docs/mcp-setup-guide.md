# MCP Setup Guide for Birkly

Connect external AI clients to your Birkly instance via **inbound MCP** (Model Context Protocol). This path is separate from **outbound BYOAI** (admin chat with your provider API keys).

## Two paths (don't mix them up)

| Goal | Where to configure |
|------|-------------------|
| Chat **inside** Birkly admin with OpenAI, Claude API, Ollama, etc. | **Settings → AI & Integrations → Outbound** (requires provider **API key**) |
| **Claude Desktop**, Cursor, or scripts calling Birkly tools | **Settings → AI & Integrations → Inbound (MCP)** (Birkly-issued **MCP endpoint key** and/or OAuth) |

Consumer subscriptions (ChatGPT Plus, Claude Pro) do **not** replace API keys for outbound chat.

## Inbound MCP: two connection paths

Birkly supports two ways for external clients to reach your CMS tools. Pick **one** per client — they use different auth.

| Path | Best for | Auth | Setup complexity |
|------|----------|------|------------------|
| **Stdio bridge** (recommended) | Claude Desktop, local MCP clients | API key via `mcp-bridge.js` | Low — copy config from admin |
| **Remote connector** | Claude.ai web/app remote MCP | OAuth 2.1 (PKCE + DCR) | Medium — enable OAuth on endpoint |

See `docs/reviews/p4-mcp-client-matrix.md` for client compatibility details.

---

## Path A — Stdio bridge (recommended)

Use this for **Claude Desktop** and any client that runs a local MCP server process. No OAuth required.

### What is the bridge?

`mcp-bridge.js` (CMS repo root) translates the client's stdio JSON-RPC into HTTP calls to `POST /api/mcp_server.php`. Claude Desktop cannot talk HTTP MCP directly; the bridge is the supported adapter.

### Step 1: Create an MCP endpoint

1. Open **Settings → AI & Integrations → Inbound (MCP)**.
2. Click **Add Endpoint**.
3. Set **Name**, **Role** (Viewer for read-only; Editor/Author for writes), and **Require approval** (recommended for production).
4. Leave **Auth mode** at **API key** (default) for the stdio bridge.
5. Save and **copy the MCP API key** when shown.

The admin UI provides a **Claude Desktop config** JSON block — copy it after revealing the key.

### Step 2: Environment variables

| Variable | Purpose |
|----------|---------|
| `BIRKLY_URL` | Your Birkly base URL (e.g. `http://localhost:8080`) — **preferred** |
| `BIRKLY_MCP_API_KEY` | Endpoint key from MCP Connections |
| `HARUCMS_URL` / `HARUCMS_API_KEY` | Legacy aliases; still accepted |

### Step 3: Claude Desktop config

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

- Use the **absolute path** to `mcp-bridge.js` inside your Birkly installation.
- The machine running Claude Desktop must reach `BIRKLY_URL` (localhost for dev; HTTPS URL for production).

### Step 4: Restart and verify

1. Restart Claude Desktop completely.
2. Start a new conversation and ask: "What Birkly tools do you have?"
3. Expect tools such as `list_collections`, `get_collection`, `list_entries`, `create_entry`, `update_entry`, `get_system_context`.

### Cursor (experimental)

Some Cursor builds support MCP via stdio. Use the same bridge config as Claude Desktop, or HTTP with `X-MCP-API-Key` (experimental). See the client matrix.

---

## Path B — Remote connector (OAuth)

Use this when adding Birkly as a **remote MCP server** in Claude's connector settings (claude.ai), without running `mcp-bridge.js` locally.

### Requirements

- Birkly must be reachable over **HTTPS** from the public internet (or Claude's broker).
- The MCP endpoint must have **Auth mode** set to **OAuth** or **Both** in admin.
- Birkly implements RFC 9728 protected-resource metadata, RFC 8414 authorization-server metadata, PKCE, and dynamic client registration (DCR).

### Step 1: Create an OAuth-enabled endpoint

1. **Settings → AI & Integrations → Inbound (MCP)** → **Add Endpoint**.
2. Set **Auth mode** to **OAuth** (remote connector only) or **Both** (bridge + remote).
3. Optionally set **OAuth client ID** if you pre-register a client; otherwise DCR assigns one at connect time.
4. Copy the **MCP Server URL** shown at the top of the inbound section (ends with `/api/mcp_server.php`).

### Step 2: Add remote connector in Claude

1. In Claude (web or app), open **Settings → Connectors** (or Integrations).
2. Add a custom MCP server and paste the **MCP Server URL**.
3. Complete the OAuth flow when prompted — Claude's broker discovers metadata via `WWW-Authenticate` and registers via DCR.
4. Approve access; tools should appear after connection.

### Auth modes reference

| Mode | API key (bridge/curl) | OAuth bearer (remote) |
|------|----------------------|------------------------|
| `api_key` (default) | ✓ | ✗ |
| `oauth` | ✗ | ✓ |
| `both` | ✓ | ✓ |

### Troubleshooting remote connector

| Symptom | Fix |
|---------|-----|
| OAuth fails immediately (`ofid_…` errors) | Endpoint auth mode must be `oauth` or `both`; verify `/.well-known/oauth-protected-resource` returns JSON |
| 401 without OAuth prompt | Server URL wrong or endpoint revoked |
| Tools missing after connect | Check endpoint **role**; Viewer cannot write |
| Subdirectory install | Use full URL from admin (includes base path) |

---

## HTTP testing with curl (API key path)

```bash
export BIRKLY_URL="http://localhost:8080"
export BIRKLY_MCP_API_KEY="your_key"

curl -sS -X POST "${BIRKLY_URL}/api/mcp_server.php" \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: ${BIRKLY_MCP_API_KEY}" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":1}'
```

Requires endpoint auth mode **API key** or **Both**.

## Legacy migration (Phase 3)

If you used MCP keys under old **AI Connections** rows:

```bash
php scripts/migrate-mcp-endpoints.php --dry-run   # preview
php scripts/migrate-mcp-endpoints.php             # migrate
```

Legacy keys dual-resolve for **90 days**. New setups use **Inbound (MCP)** only.

## Using ChatGPT instead of MCP

ChatGPT does not support Birkly MCP. For in-admin chat:

1. **Settings → AI & Integrations → Outbound** → Add **OpenAI** (or another provider)
2. Enter your **OpenAI API key** from the developer dashboard
3. Use the floating AI button in the admin panel

## Troubleshooting (stdio bridge)

| Issue | Check |
|-------|-------|
| MCP not connecting | Key from **Inbound (MCP)**, not outbound AI Connections |
| Wrong URL | `BIRKLY_URL` = base URL only (no `/api/...` suffix) |
| No tools | Restart Claude; validate JSON config; run `node /path/to/mcp-bridge.js` manually |
| Permission errors | Endpoint role; approval queue for writes |

Logs: `logs/php_errors.log`; MCP audit under `data/mcp_audit/` (no secrets).

## Available MCP tools

- **list_collections** — List content collections
- **get_collection** — Collection schema/details
- **list_entries** — Entries in a collection
- **create_entry** / **update_entry** — Writes (may require approval)
- **get_system_context** — Curated CMS context for the model

## Related docs

- `docs/reviews/p4-mcp-client-matrix.md` — stdio vs remote connector matrix
- `docs/ai/BYOAI-ARCHITECTURE.md` — inbound vs outbound architecture
- `docs/reviews/p4.1-mcp-oauth-threat-model.md` — OAuth security model
- Birkly-Docs: `01-admin/mcp-connections.md`
