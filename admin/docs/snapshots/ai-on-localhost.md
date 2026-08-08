# AI on localhost

## Summary

Running Birkly on `http://localhost` works for day-to-day admin and the **built-in assistant**, but **remote AI connectors** (Claude, ChatGPT, and similar) usually **cannot** call `http://localhost/mcp` — they need a **public HTTPS** URL. For local development, use the **mcp-bridge** (stdio) or a **tunnel** (`cloudflared` / ngrok) to expose HTTPS to `/mcp`. Open **Settings → AI** for the same connector controls and local-project guidance.

> **Warning:** Do **not** expose your admin panel to the open internet as a “quick fix.” Prefer mcp-bridge for local tools, or a short-lived tunnel only for the MCP endpoint, with your usual auth in place.

## Beginner

### What works on localhost

| Path | Works? | Notes |
|------|--------|-------|
| Admin + **built-in assistant** on localhost | **Yes** | Outbound BYOAI (API keys) works from your machine. **Ollama** on `http://localhost:11434` is fine. |
| Remote **connectors** (Claude / ChatGPT / similar) → `http://localhost/mcp` | **Usually no** | Those apps need a **public HTTPS** Connector URL ending in `/mcp`. |
| Local AI tools via **mcp-bridge** (stdio → local Birkly) | **Yes** | Recommended for Claude Desktop and similar local MCP hosts. |
| **Tunnel** (`cloudflared` / ngrok) → HTTPS → `/mcp` | **Yes** | Supported pattern so remote connectors can reach your local CMS. |

Where to configure connectors: **Settings → AI** (Settings → AI is the home for connectors). See [Connect your AI app](../01-admin/mcp-connections.md).

### Option A — mcp-bridge (recommended for local tools)

Use when your AI app runs on the same machine and supports **stdio** MCP servers (for example Claude Desktop).

1. Open **Settings → AI**.
2. Expand **Developer / debug** (or the **Local project** card when shown).
3. Follow the download / copy instructions for `mcp-bridge.js`.
4. Point your AI app’s MCP config at that bridge so it talks to your local Birkly over stdio — not over a public URL.

Normal site owners using only cloud Claude/ChatGPT connectors do not need the bridge.

### Option B — Tunnel to public HTTPS

Use when the connector **must** be a public HTTPS URL (most browser or cloud Claude/ChatGPT connector UIs).

1. Run Birkly locally (for example `http://localhost:8080`).
2. Start a tunnel that terminates **HTTPS** and forwards to your local CMS port:

**cloudflared (example):**

```bash
cloudflared tunnel --url http://localhost:8080
```

**ngrok (example):**

```bash
ngrok http 8080
```

3. Copy the HTTPS origin the tunnel prints (for example `https://abc123.trycloudflare.com`).
4. In **Settings → AI**, use a Connector URL of the form:

```text
https://YOUR-TUNNEL-HOST/mcp
```

5. Paste that URL into Claude, ChatGPT, or Cursor. Leave Client ID / Client Secret empty. Sign in when prompted.

Keep the tunnel process running while you work. Treat the temporary URL like a secret — anyone who reaches `/mcp` still goes through Birkly auth, but you should not leave unused tunnels running.

### Security reminders

- Prefer **mcp-bridge** for purely local workflows — no public URL.
- Tunnels are for development; shut them down when finished.
- Never set Birkly to “open admin with no auth” to make AI connect.
- Production sites should already be on public HTTPS — use the normal Connector URL from Connections; no tunnel needed.

## Advanced Users

**Why remote connectors fail on localhost:** Cloud AI apps open OAuth and then call your MCP HTTP endpoint from their servers. Those servers cannot reach your laptop’s `127.0.0.1`. A tunnel provides a reachable HTTPS hostname; mcp-bridge avoids HTTP entirely by using stdio on the local machine.

**Ollama:** Built-in assistant provider `ollama` defaults to `http://localhost:11434/api/chat`. That path does not require a public URL because the request is **outbound from PHP** on the same host.

**Connector URL shape:** Always HTTPS in production and for tunneled local use; path ends with `/mcp`. See [Connect your AI app](../01-admin/mcp-connections.md#requirements).

**Related surfaces:** Website tools ([WebMCP](webmcp-tools.md)) are a different layer — browser agents on your **public site**, not the CMS MCP connector. Localhost WebMCP still needs a secure context (HTTPS or localhost).

## Further reading

- [Connect your AI app](../01-admin/mcp-connections.md) — personal connector, service agents, OAuth
- [Built-in assistant](../01-admin/ai-connections.md) — outbound API keys / Ollama
- [Agent Guide](../02-content/agent-guide.md) — Guide · Tools · Publish (connectors under **Settings → AI**)
- [Website tools (WebMCP)](webmcp-tools.md)
- [Developer mode](../01-admin/developer-mode.md) — system info and MCP smoke helpers
