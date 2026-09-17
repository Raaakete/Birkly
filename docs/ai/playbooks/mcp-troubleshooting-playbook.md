# MCP troubleshooting playbook

Use when OAuth, tool lists, permissions, or write prompts block MCP work.

## Two approval channels (do not mix them)

| Channel | Where writes are confirmed | Birkly setting |
|---------|---------------------------|----------------|
| **Inbound MCP** (Cursor, Claude Desktop, ChatGPT) | Your **AI app** tool-permission UI | Personal connector has no Birkly “never ask” toggle |
| **Built-in assistant** (admin chat) | **Settings → AI → Approvals** tab | **Require approval** on the API-key connection |

Symptoms: “I’m admin but still get prompts” → configure **Always allow** (or equivalent) for Birkly tools in Cursor/Claude, not the Approvals tab.

## Connection checklist

1. **Localhost** — Cloud connector UIs reject `http://localhost`. On the same machine, use **`mcp-remote`** + a **service agent** URL from Settings → AI (stdio → `http://localhost:PORT/mcp/agents/…`). Otherwise use an HTTPS tunnel.
2. **Personal connector URL** — Copy from **Settings → AI** exactly: `/mcp/agents/ai_agent_personal` (not a stale site-wide `/mcp` URL unless using API-key bridge).
3. **Service agents** — Your user/role must appear under **Who can use this agent** before OAuth succeeds.
4. **Role** — Personal connector uses your human role; service agents use the agent’s fixed role.

## Stale tools after upgrade

MCP clients cache `tools/list` from `initialize`.

| Signal | Fix |
|--------|-----|
| New `media.*` or `cms_admin_action` actions missing | Reconnect MCP or restart the AI app |
| `capabilities_version` changed in initialize | Refresh connector; run `cms_list_admin_actions` to see current catalog |

## Common errors

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| 401 after connect | Token bound to wrong agent URL | Remove connector; re-add using URL from Settings → AI |
| OAuth loop / login again | Session or redirect mismatch | Complete login in same browser; retry authorize |
| Permission denied on write | Role lacks capability | Check role or service-agent fixed role |
| `Unknown CMS admin action` | Typo or old client cache | `cms_list_admin_actions`; use `domain.subaction` ids |
| Media rename no-op | Legacy tool shape | Prefer `media.update` or `cms_admin_action` `media.update` |
| Raw `{for each…}` on site | Missing client script | Add `<script src="/birkly-client.js"></script>` |

## Cursor-specific

1. Open MCP server settings for Birkly.
2. Enable **Always allow** (or approve once per tool family) for write tools you trust.
3. After CMS upgrade: disconnect and reconnect, or use “Refresh tools” if available.

## Verify connectivity

1. `get_project_info` — confirms read access and site prefix.
2. `cms_list_admin_actions` — confirms admin catalog for your role.
3. `connections_health` — optional Connections smoke test.

## Related

- `mcp-setup` — first-time OAuth
- `mcp-capabilities` — full tool families and catalog groups
- `ai-connections` — built-in assistant only
