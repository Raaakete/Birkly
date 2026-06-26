# BYOAI architecture (internal)

Internal reference for **Bring Your Own AI** (Phase 3+) and **inbound MCP** (Phase 4). User-facing guides live in Birkly-Docs (`01-admin/ai-*.md`, `01-admin/mcp-connections.md`).

## Two-channel model (Phase 4)

Outbound BYOAI and inbound MCP are **independent channels**. They share permissions and tools but use different storage, credentials, and entry points.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         OUTBOUND (BYOAI)                                │
│  Admin floating chat / connection wizard                                │
│       │                                                                 │
│       ▼                                                                 │
│  api/ai_chat.php  ──HTTP──▶  Provider API (OpenAI, Anthropic, Ollama…)  │
│       │                              ▲                                  │
│       │                              │ api_key_encrypted on AI row      │
│  api/ai_connections.php  ◀───────────┘  (users/*.json, type: ai)        │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         INBOUND (MCP)                                   │
│  Claude Desktop / Cursor / curl / other MCP client                    │
│       │                                                                 │
│       ▼                                                                 │
│  mcp-bridge.js (stdio) ──optional──▶  api/mcp_server.php               │
│       │                                      │                          │
│       │                                      ▼                          │
│       │                         settings/mcp_endpoints.json             │
│       │                         (standalone endpoint profiles)          │
│       │                         core/mcp_endpoints.php                    │
│       │                         api/mcp_endpoints.php (admin CRUD)        │
└─────────────────────────────────────────────────────────────────────────┘

         ┌──────────────────────────────────────────┐
         │  SHARED EXECUTION LAYER                  │
         │  core/ai_permissions.php                 │
         │  core/ai_tool_registry.php               │
         │  data/ai_approvals/ (write queue)        │
         └──────────────────────────────────────────┘
```

| Channel | Direction | Credential | Storage | Admin UI |
|---------|-----------|------------|---------|----------|
| **Outbound BYOAI** | Birkly → provider | Provider API key (encrypted) | `users/*.json` (`type: ai`) | Settings → **AI Connections** |
| **Inbound MCP** | External client → Birkly | `mcp_api_key` per endpoint | `settings/mcp_endpoints.json` | Settings → **MCP Connections** |

**No outbound provider key is required for inbound MCP.** A Claude Desktop user does not need an OpenAI or Anthropic API key on the Birkly instance — only an MCP endpoint key.

## Principles (locked)

| Decision | Choice |
|----------|--------|
| Provider (outbound) | User connects OpenAI, Anthropic, Google, OpenRouter, Ollama, or custom HTTP |
| MCP (inbound) | Standalone endpoint profiles — **not** tied to outbound AI connection rows |
| Identity | Outbound: AI user per connection. Inbound: synthetic `mcp_endpoint` user per profile |
| Enforcement | `checkPermission()` on every AI/MCP action at execution time (`core/ai_permissions.php`) |
| Default approval | New connections and endpoints: `require_approval` **true** |
| Legacy MCP keys | 90-day dual-resolve after `scripts/migrate-mcp-endpoints.php` |
| Context | Curated pack (`docs/ai/birkly-context-pack.md`) — not full docs dump |

## Component map

```
Admin UI
  Settings → AI Connections     outbound wizard, chat provider rows
  Settings → MCP Connections    inbound endpoint profiles (empty state until created)
  Floating chat                 connection picker in header
    │
    ▼
api/ai_connections.php     Outbound CRUD, encryption, sharing, test call
api/ai_chat.php            Chat loop, system prompt, structured tool execution
api/ai_approvals.php       Pending write queue, approve/reject → execute
api/ai_helpers.php         Provider metadata, context loader
api/mcp_endpoints.php      Inbound endpoint CRUD, rotate, revoke (auth + CSRF)
api/mcp_server.php         HTTP MCP bridge (tools mirror chat actions)
    │
    ▼
core/mcp_endpoints.php     Load/save settings/mcp_endpoints.json, auth, audit, rate limits
core/ai_permissions.php    Action → permission map, birkly_ai_require_permission()
    │
    ▼
users/{id}.json            Outbound AI connection storage (type: ai)
settings/mcp_endpoints.json  Inbound endpoint profiles (id, name, role, mcp_api_key, …)
data/ai_conversations/     Chat history per session
data/ai_approvals/         Pending approval payloads
data/mcp_audit/            Inbound tool call audit (no secrets)
```

## Outbound connection lifecycle

1. Admin creates connection in **Settings → AI Connections** (`action=create`).
2. Backend stores JSON user record: `provider`, `model`, `role`, `shared_type`, `allowed_users`, `require_approval`, encrypted `api_key` (except Ollama).
3. Chat selects connection via header picker → backend resolves role → builds system prompt from curated context + permission summary.
4. **Read actions** execute immediately if role permits.
5. **Write actions** enqueue to `data/ai_approvals/` when `require_approval` is true.

**API key vs subscription:** ChatGPT Plus, Claude Pro, and similar consumer subscriptions are **not** API access. Outbound BYOAI requires a provider **API key** (or local Ollama with no key).

## Inbound MCP endpoint lifecycle

1. Admin opens **Settings → MCP Connections** (dedicated tab; no auto-generated keys on install).
2. Creates endpoint: name, role, `require_approval`. Backend writes to `settings/mcp_endpoints.json` and returns `mcp_api_key` once.
3. External client calls `POST /api/mcp_server.php` with `X-MCP-API-Key` or `api_key` query param.
4. `api/mcp_server.php` resolves endpoint profile → maps to synthetic user → same permission map as chat.
5. Writes respect approval queue when enabled. Calls logged to `data/mcp_audit/`.

**Migration:** Legacy per-AI-connection `mcp_api_key` values in `users/*.json` are migrated with:

```bash
php scripts/migrate-mcp-endpoints.php          # run once after upgrade
php scripts/migrate-mcp-endpoints.php --dry-run  # preview only
```

Dual-resolve of legacy keys lasts 90 days (`legacy_dual_resolve_until` in store).

## Permission enforcement

`birkly_ai_action_permission_map()` maps each action type (e.g. `create_entry`, `list_collections`, MCP `get_collection`) to flat permissions checked via `checkPermission()`. No prompt-only gating.

MCP and admin chat share the same map — external MCP clients cannot exceed the endpoint role.

## System prompt assembly (outbound only)

1. **Curated context** — `docs/ai/birkly-context-pack.md` (rules, terminology, safe action patterns).
2. **Live schema summary** — collection list + field types (bounded size).
3. **Role capabilities** — derived from assigned role, not from model self-assessment.
4. **Tool definitions** — structured JSON schema for chat; MCP tools list mirrors allowed actions.

## Outbound providers

| Provider | Chat in admin | API key | Notes |
|----------|---------------|---------|-------|
| openai | Yes | Required | OpenAI-compatible request format |
| anthropic | Yes | Required | Messages API |
| google | Yes | Required | Gemini |
| openrouter | Yes | Required | OpenAI-compatible proxy |
| ollama | Yes | No | Default `http://localhost:11434/api/chat` |
| custom | Yes | Optional | User-supplied URL |

Keys encrypted with AES-256-CBC; key material in `settings/security.json`.

## MCP clients

- Bridge: `mcp-bridge.js` (repo root) — env `BIRKLY_URL`, `BIRKLY_MCP_API_KEY` (legacy `HARUCMS_*` still accepted).
- Setup guide: `docs/mcp-setup-guide.md`
- Client matrix: `docs/reviews/p4-mcp-client-matrix.md`
- Product doc: Birkly-Docs `01-admin/mcp-connections.md`

## Related files

- `api/ai_chat.php` — outbound chat + tool execution
- `api/mcp_endpoints.php` — inbound endpoint admin API
- `core/mcp_endpoints.php` — endpoint store + auth helpers
- `api/ai_approvals.php` — approval queue
- `core/ai_permissions.php` — RBAC map
- `docs/reviews/p4-oauth-spike.md` — OAuth go/no-go (P4 docs only)
- `docs/reviews/p3-m1-ai-automation-audit.md` — Phase 3 baseline audit

## Values check

- Dependency-free core: outbound HTTP only to user-configured providers; inbound is plain HTTP MCP
- UI decoupled: separate Settings tabs for outbound vs inbound
- Security by default: approval on, permissions enforced at execute time, MCP audit log without secrets
