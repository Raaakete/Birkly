# MCP connections (inbound)

Connect **external** AI clients (Claude Desktop, experimental Cursor, HTTP scripts) to Birkly tools.

## Where

**Settings → MCP Connections** — standalone tab; not tied to outbound AI Connections.

## Setup

1. **Add MCP endpoint** — name, role, require approval.
2. Copy the **MCP API key** when shown (rotate anytime; 24h grace on rotation).
3. Server URL: `{your-birkly-url}/api/mcp_server.php`

## Bridge (Claude Desktop)

Use `mcp-bridge.js` at the CMS root:

- `BIRKLY_URL` — your instance URL
- `BIRKLY_MCP_API_KEY` — endpoint key from this tab

See platform `docs/mcp-setup-guide.md` for full config.

## Legacy migration

Upgrading from per-AI-connection MCP keys:

```bash
php scripts/migrate-mcp-endpoints.php
```

Old keys dual-resolve for 90 days.

## Security

- One key per endpoint; lowest role that fits
- Approval queue applies to writes (same as admin chat)
- Audit log records tool calls (no secrets)

## Not for in-admin chat

Use **AI Connections** with OpenAI/Anthropic/Ollama for the floating chat button.
