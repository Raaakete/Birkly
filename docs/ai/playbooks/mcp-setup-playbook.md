# MCP setup playbook (Connect your AI app)

Use for: connecting **Claude**, **ChatGPT**, or **Cursor** to Birkly content via MCP (inbound AI).

## Two AI connection types

| Type | Where | Auth |
|------|-------|------|
| **Built-in assistant** | Floating chat in admin | API key — see `ai-connections` |
| **MCP connector** | External AI app | OAuth — this playbook |

## Personal connector (default)

One URL per site; each team member signs in with **their own** Birkly account and permissions.

### Steps

1. Admin → **Agent Guide → Connections**
2. Copy **Personal connector URL** (HTTPS, ends with `/mcp`)
3. In your AI app paste URL only — **leave Client ID/Secret empty**
   - **Cursor:** Cursor Settings → **Tools & MCP** → Add MCP server → paste URL → **Connect** (no manual `mcp.json` editing)
   - **Claude:** Settings → Connectors → Add
   - **ChatGPT:** Settings → Connectors
   - **Other MCP apps:** add remote/OAuth MCP server with the same URL
4. Sign in with Birkly account when prompted
5. Approve consent (shows your role)

## Service agent (admin)

Shared bot with **fixed role** (e.g. always Editor). Admin creates under Connect your AI app → Add service agent → assign visibility (roles/users) → copy that agent's URL.

## Approvals

**MCP writes are approved in the AI client app** — not in Birkly's Approvals tab (that tab is built-in assistant only).

## What MCP exposes

- **Tools:** Same as AI action registry — collections, entries, project files, media, docs, Agent Guide reads, etc.
- **Resources:**
  - `birkly://documentation` — context pack
  - `birkly://project` — project site info
  - `birkly://brand-context` — Agent Guide summary
  - `birkly://docs/{topic}` — playbooks (site-building, public-forms, commerce, …)
  - `birkly://docs/plugin/{slug}` — active plugin docs

## Recommended first prompt in external AI

> Read birkly://documentation and birkly://docs/playbooks-index. Then list my collections.

## Playbooks via MCP

Use `read_birkly_docs` tool or fetch `birkly://docs/site-building` before complex tasks.

## Anti-patterns

- Putting API keys in MCP connector (use OAuth URL only)
- Expecting Birkly Approvals tab for MCP writes
- Using viewer-role connector for destructive bulk edits
- Sending entire large HTML homepages in one `write_project_file` call from **ChatGPT** — OpenAI may block the tool call client-side with a generic "safety status" error before Birkly receives it. Prefer smaller files (`css/style.css`, `js/site.js`) and incremental HTML updates; verify with `list_project_files` + `read_project_file`.

## Related

- `ai-connections` — built-in chat
- `agent-guide` — brand context resource
- `playbooks-index` — which doc to read when
