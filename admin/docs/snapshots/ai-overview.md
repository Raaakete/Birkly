# AI Overview

Birkly includes a built-in AI assistant that can help you create and edit content, answer questions about your collections, and perform actions in the CMS—all from a chat interface in the admin panel. You connect one or more AI providers (e.g. OpenAI, Ollama, Anthropic), and optionally require human approval before the AI's write actions are executed. External AI tools can also connect *to* Birkly via inbound MCP.

## Summary

The **AI system** has two channels under **Settings → AI**: **Built-in assistant** (outbound API keys for in-admin chat) and **Connect your AI app** (inbound MCP for Claude, ChatGPT, Cursor). **Approvals** for writes apply to the built-in assistant when enabled; MCP clients approve writes in the client app. Open the floating AI button to chat; copy a connector URL for external tools. Supported outbound providers include OpenAI (default model `gpt-4o-mini`), Anthropic, Google (Gemini), OpenRouter, Ollama (local), and custom endpoints.

## Beginner

**What is the Birkly AI?**

The AI is an assistant that lives inside your admin panel. You open it by clicking the **floating AI button** (usually in the bottom-right corner). You type questions or requests in natural language; the AI can look up your content, create or edit entries, and run other actions—all according to the permissions you gave the outbound connection.

Separately, **inbound MCP** lets tools like Claude Desktop call Birkly from outside the admin UI.

**Where do I see it?**

- **Floating button** — On any admin page, a button (often yellow or with an AI icon) in the corner. Click it to open the chat window.
- **Settings → AI & Integrations** — One tab with:
  - **Approval queue** — Pending write actions from outbound chat and inbound MCP.
  - **Outbound (BYOAI)** — Provider connections for in-admin chat (API keys).
  - **Inbound (MCP)** — Endpoints for external MCP clients (API key and/or OAuth).
- Deep links: `#ai-integrations` (tab), `#ai-connections` / `#outbound-connections` (outbound), `#mcp-connections` / `#inbound-mcp` (inbound).

**What can the AI do?**

- **Read** — List collections, list or read entries, read media, users, settings, pages, roles, plugins. The AI uses this to answer questions like “How many blog posts are there?” or “What fields does the Products collection have?”
- **Write** (if the connection's or endpoint's role allows) — Create or update entries, create or update collections, upload or manage media, update settings, create or update pages, manage users/roles/plugins. You can require that these write actions go to the **approval queue** first, so nothing changes until you approve.

**Do I need to pay for an API?**

It depends on the provider. **Ollama** runs on your own machine and is free. **OpenAI, Anthropic, Google, OpenRouter** use your API keys and their pricing. Consumer subscriptions (ChatGPT Plus, Claude Pro) do **not** include API access for outbound chat. You only need one outbound connection to start; many users begin with Ollama for local, free use.

**Next steps**

- [AI connections](ai-connections.md) — Outbound providers (e.g. Ollama or OpenAI).
- [MCP connections](mcp-connections.md) — Inbound MCP (stdio bridge or remote connector OAuth).
- [AI chat and actions](ai-chat-and-actions.md) — How to use the chat and what actions the AI can run.
- [AI approvals](ai-approvals.md) — How the shared approval queue works.

## Advanced Users

**Architecture**

- **Outbound connections** — Stored as special “AI” users in user storage. Each connection has: provider, API key (encrypted), model, role (maps to permissions), sharing (private / all / specific users), and optional “require approval” for write actions.
- **Inbound MCP endpoints** — Stored in settings; each has api_key hash, role, require_approval, auth_mode (`api_key` | `oauth` | `both`). Protocol via `api/mcp_server.php`; OAuth via `core/mcp_oauth.php`.
- **Chat** — Admin sends messages to `api/ai_chat.php`. Backend builds a system prompt from `generateSystemContext()` (collections, schemas, docs) and `buildSystemPrompt()` (permissions, action list). Conversation history is stored under `data/ai_conversations/`. The backend calls the provider (OpenAI-compatible, Anthropic, Ollama, etc.), parses the response for JSON action blocks, and either executes (if no approval) or creates an approval request.
- **Approvals** — Pending actions from **outbound built-in chat** are stored in `data/ai_approvals/`. Admin UI calls `api/ai_approvals.php` to list, approve, or reject. MCP tool writes are not queued here.

**Outbound providers**

| Provider   | Type       | API key | Notes |
|-----------|------------|--------|-------|
| OpenAI    | openai     | Required | gpt-4, gpt-3.5-turbo, etc. |
| Anthropic | anthropic  | Required | Claude models |
| Google    | google     | Required | Gemini models |
| OpenRouter| openrouter| Required | Multi-model, OpenAI-compatible endpoint |
| Ollama    | ollama     | Not used | Local; default `http://localhost:11434/api/chat` |
| Custom    | custom     | Optional | User-supplied URL; OpenAI-compatible format |

Inbound MCP is configured via MCP endpoints, not outbound provider rows. See [MCP connections](mcp-connections.md).

**Permissions**

- Each outbound connection or inbound endpoint has a **role** (e.g. viewer, author, editor, admin). Role is resolved via `getPermissionsForRole()` to a flat list (read, write, manage_content, manage_media, manage_settings, manage_users, manage_plugins, etc.). The system prompt tells the model which actions it may perform; the backend enforces the same before executing or creating an approval.

**Security**

- Outbound API keys stored encrypted (AES-256-CBC) using a key in settings. Ollama does not store API keys. MCP endpoint keys are issued per endpoint; OAuth bearer tokens are short-lived. Access to outbound connections is restricted to owner, or all users, or a specific list of user IDs depending on `shared_type` and `allowed_users`.

## Related

- [AI connections](ai-connections.md)
- [MCP connections](mcp-connections.md)
- [AI chat and actions](ai-chat-and-actions.md)
- [AI approvals](ai-approvals.md)
- [Settings](settings.md)
- [Roles and permissions](roles-and-permissions.md)
