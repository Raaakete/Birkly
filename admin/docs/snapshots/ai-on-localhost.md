# AI on localhost

## Summary

Running Birkly on `http://localhost` works for day-to-day admin and the **built-in assistant**, but **cloud connector UIs** usually reject `http://localhost` URLs. For **Claude Desktop** on the same machine, use **`mcp-remote`** (stdio) with a **service agent** URL from **Settings → AI**. Alternatives: a short-lived **HTTPS tunnel**, or the legacy **mcp-bridge** under Developer / debug.

> **Warning:** Do **not** expose your admin panel to the open internet as a “quick fix.” Prefer `mcp-remote` + OAuth service agents for local desktop tools, or a short-lived tunnel only for the MCP endpoint.

## Beginner

### What works on localhost

| Path | Works? | Notes |
|------|--------|-------|
| Admin + **built-in assistant** on localhost | **Yes** | Outbound BYOAI (API keys) works from your machine. **Ollama** on `http://localhost:11434` is fine. |
| Remote **connectors** (Claude / ChatGPT / similar) → `http://localhost/mcp` | **Usually no** | Those apps need a **public HTTPS** Connector URL ending in `/mcp`. |
| **Claude Desktop** via **`mcp-remote`** + service agent URL | **Yes** | Recommended — stdio on your Mac/PC proxies to `http://localhost:PORT/mcp/agents/…` with OAuth. |
| **Tunnel** (`cloudflared` / ngrok) → HTTPS → `/mcp` | **Yes** | When the connector UI requires a public HTTPS URL. |
| **mcp-bridge** (legacy API-key stdio) | **Yes** | Developer / debug only — see Settings → AI. |

Where to configure connectors: **Settings → AI**. See [Connect your AI app](../01-admin/mcp-connections.md).

### Option A — mcp-remote + service agent (recommended for Claude Desktop)

1. **Settings → AI → Service agents → Add agent** — copy the connector URL (`http://localhost:8080/mcp/agents/ai_agent_…`).
2. Claude Desktop `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "birkly-local": {
      "command": "/absolute/path/to/npx",
      "args": ["-y", "mcp-remote", "http://localhost:8080/mcp/agents/ai_agent_YOUR_ID"]
    }
  }
}
```

3. Quit Claude completely; complete OAuth on first connect.
4. Restart Claude after plugin installs so tools refresh.

### Option B — Tunnel to public HTTPS

See Birkly-Docs `04-integration/ai-on-localhost.md` for cloudflared/ngrok steps.

### Option C — mcp-bridge (Developer / debug)

Legacy API-key stdio bridge. OAuth service agents are preferred.

## Advanced Users

**Why remote connectors fail on localhost:** Cloud AI apps call MCP from their servers; they cannot reach `127.0.0.1`. Use `mcp-remote` on the same machine or an HTTPS tunnel.

**Connector URL shape:** Production and tunneled URLs use HTTPS and end with `/mcp` or `/mcp/agents/…` for service agents.
