# BYOAI architecture (internal)

Internal reference for Phase 3 **Bring Your Own AI**. User-facing guides live in Birkly-Docs (`01-admin/ai-*.md`).

## Principles (locked)

| Decision | Choice |
|----------|--------|
| Provider | User connects OpenAI, Anthropic, Google, OpenRouter, Ollama, custom HTTP, or MCP |
| Identity | Each connection is a **real CMS user** with an assigned **role** |
| Enforcement | `checkPermission()` on every AI/MCP action at execution time (`core/ai_permissions.php`) |
| Default approval | New connections: `require_approval` **true** |
| MCP keys | **One key per AI connection**, scoped to that connection's role |
| Context | Curated pack (`docs/ai/birkly-context-pack.md`) — not full docs dump |

## Component map

```
Admin UI (floating chat, Settings → AI Connections)
    │
    ▼
api/ai_connections.php     CRUD, encryption, sharing, MCP URL/key generation
api/ai_chat.php            Chat loop, system prompt, structured tool execution
api/ai_approvals.php       Pending write queue, approve/reject → execute
api/ai_helpers.php         Provider metadata, context loader
api/mcp_server.php         HTTP MCP bridge (tools mirror chat actions)
    │
    ▼
core/ai_permissions.php    Action → permission map, birkly_ai_require_permission()
    │
    ▼
users/{id}.json            Connection storage (type: ai, encrypted api_key)
data/ai_conversations/     Chat history per session
data/ai_approvals/         Pending approval payloads
```

## Connection lifecycle

1. Admin creates connection in **Settings → AI Connections** (`action=create`).
2. Backend stores JSON user record: `provider`, `model`, `role`, `shared_type`, `allowed_users`, `require_approval`, encrypted `api_key` (except Ollama/MCP).
3. For **MCP** provider: generates `mcp_api_key` and exposes `api/mcp_server.php` URL; no in-admin chat for that row.
4. Chat or MCP client selects connection → backend resolves role → builds system prompt from curated context + permission summary.
5. **Read actions** execute immediately if role permits.
6. **Write actions** enqueue to `data/ai_approvals/` when `require_approval` is true; approver's session runs `executeAiAction()` on approve.

## Permission enforcement (P3-M2)

`birkly_ai_action_permission_map()` maps each action type (e.g. `create_entry`, `list_collections`, MCP `get_collection`) to flat permissions checked via `checkPermission()`. No prompt-only gating.

MCP and admin chat share the same map — external MCP clients cannot exceed the connection role.

## System prompt assembly

1. **Curated context** — `docs/ai/birkly-context-pack.md` (rules, terminology, safe action patterns).
2. **Live schema summary** — collection list + field types (bounded size).
3. **Role capabilities** — derived from assigned role, not from model self-assessment.
4. **Tool definitions** — structured JSON schema for chat; MCP tools list mirrors allowed actions.

Avoid dumping entire `docs/` or all entry bodies into the prompt.

## Providers

| Provider | Chat in admin | API key | Notes |
|----------|---------------|---------|-------|
| openai | Yes | Required | OpenAI-compatible request format |
| anthropic | Yes | Required | Messages API |
| google | Yes | Required | Gemini |
| openrouter | Yes | Required | OpenAI-compatible proxy |
| ollama | Yes | No | Default `http://localhost:11434/api/chat` |
| custom | Yes | Optional | User-supplied URL |
| mcp | No | N/A | External MCP clients only |

Keys encrypted with AES-256-CBC; key material in `settings/security.json`.

## MCP (per-connection)

- Each MCP connection row has its own `mcp_api_key`.
- Requests: `POST /api/mcp_server.php` with `X-MCP-API-Key` or `api_key` query param.
- Bridge script: `mcp-bridge.js` (repo root) for stdio clients.
- Public guide: `docs/mcp-setup-guide.md`; product doc: Birkly-Docs `01-admin/mcp-connections.md`.

## Related files

- `api/ai_chat.php` — main chat + tool execution
- `api/ai_approvals.php` — approval queue
- `core/ai_permissions.php` — RBAC map
- `docs/mcp-setup-guide.md` — Claude Desktop setup
- `docs/reviews/p3-m1-ai-automation-audit.md` — baseline audit

## Values check

- Dependency-free core: outbound HTTP only to user-configured providers
- UI decoupled: connections configured in admin templates; execution in API layer
- Security by default: approval on, permissions enforced at execute time
