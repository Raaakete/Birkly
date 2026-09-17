# AI on localhost

## Summary

When Birkly runs on a **local URL** (for example `http://localhost:8080`), some AI connection paths behave differently than on a **public HTTPS** site. The built-in admin assistant still works. **External MCP connectors** (AI apps that connect *into* Birkly) usually cannot use a plain `http://localhost/...` URL in cloud-based setup UIs.

## Built-in assistant (admin chat)

The assistant uses **outbound** API calls from your Birkly server to your provider. That works on local installs the same as on production — configure it under **Built-in assistant** on this page.

## External AI apps (MCP connectors)

These apps connect **to** Birkly (personal connector or service agent URLs).

### Public HTTPS site

Copy the Connector URL from this page (`https://…/mcp` or `…/mcp/agents/…`). Paste it in your AI app and sign in with OAuth. No extra tooling.

### Local HTTP site — same computer as Birkly

Many **desktop** MCP clients speak **stdio**, not HTTP. They cannot paste `http://localhost/...` directly.

**mcp-remote** is a small open-source helper (install via `npx`) that runs on your computer, bridges stdio ↔ HTTP, and forwards to your Birkly MCP URL.

1. **Service agents → Add agent** on this page. Copy the agent **Connector URL** (it may show `http://localhost:PORT/mcp/agents/…`).
2. Install [Node.js](https://nodejs.org/) if needed (`npx` comes with it).
3. In your desktop MCP app’s config, add a server that runs: `npx -y mcp-remote YOUR_CONNECTOR_URL` (use the full URL from step 1). Some apps want the absolute path to `npx` — run `which npx` in a terminal.
4. Restart the app. On first connect, complete **OAuth** in the browser when Birkly prompts you.

Example (Claude Desktop `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "birkly": {
      "command": "/absolute/path/to/npx",
      "args": ["-y", "mcp-remote", "http://localhost:8080/mcp/agents/ai_agent_YOUR_ID"]
    }
  }
}
```

### Local HTTP site — connector UI requires HTTPS

Some AI products only accept an **HTTPS** Connector URL and call it from the cloud. Use a **short-lived tunnel** (for example `cloudflared` or ngrok) to your Birkly port, then paste `https://YOUR-TUNNEL-HOST/mcp` (or the service-agent path) in the connector UI. Shut the tunnel down when finished.

### mcp-bridge (Developer / debug)

Under **Developer / debug** on this page, `mcp-bridge.js` is an optional stdio bridge that uses an **MCP API key** instead of OAuth service agents. Use only if your workflow requires it.

## Security

Do not expose the full admin panel to the public internet as a shortcut. Prefer **mcp-remote on the same machine**, or a **tunnel limited to MCP**, for local development.
