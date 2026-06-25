# P3-M1 AI & automation audit — 2026-06-25

Baseline for Phase 3 reprioritization (BYOAI + automation plugin). Complements `docs/reviews/2026-06-25-alpha-readiness-register.md`.

## BYOAI — current state

| Area | Status | Notes |
|------|--------|-------|
| Connection CRUD | **Implemented** | `api/ai_connections.php`, Settings → AI Connections |
| Providers | **Implemented** | OpenAI, Anthropic, Google, OpenRouter, Ollama, custom URL, MCP |
| AI as user | **Partial** | `type: "ai"` in `users/*.json`; role stored |
| Role permissions | **Broken** | Prompt-only; `executeReadAction` / `executeAiAction` / MCP do not call `checkPermission()` |
| Admin chat | **Implemented** | `admin/js/ai-chat-button.js` (floating); dead code in `ai-connections.js` |
| Approval queue | **Implemented** | `api/ai_approvals.php`; weak ACL (any auth user can approve) |
| Curated context | **Missing** | `generateSystemContext()` dumps collections + all `docs/*.md` — noisy, not rules-first |
| Structured tools | **Missing** | JSON-in-text + regex fallbacks in `ai_chat.php` (~2.4k lines) |
| MCP inbound | **Partial** | `api/mcp_server.php` HTTP shim; `mcp-bridge.js` for Claude Desktop |
| MCP role scope | **Broken** | API key auth only; no role enforcement on tools |
| Shared connections | **Bug** | `shared` vs `shared_type` mismatch in `ai_chat.php` |
| Tests / CI | **Missing** | No `tests/ai_*` suites |
| Roadmap (pre-edit) | **Missing** | Not tracked until Phase 3 reprioritization |

## Automation — current state

| Area | Status | Notes |
|------|--------|-------|
| Execution engine | **~88%** | `core/automation/` — queue, executor, blocks, webhooks, scheduler |
| REST API | **~90%** | `api/automation.php`, `api/automation_webhook.php` |
| Admin UI | **~35%** | `automation.html` exists but **`?page=automation` not routed** in `admin/index.php` |
| Plugin packaging | **0%** | Still core; loaded in `core/plugins.php` before plugins |
| Plugin extensibility | **~40%** | Hooks exist; no plugin registers blocks; `$haru_plugins` bug in registry |
| Entry triggers | **Wired** | `api/admin_entries.php` → `enqueue_automation()` |
| Secure cron | **Weak** | `process_queue` publicly callable |
| Tests / CI | **Missing** | No automation test suite |
| Collection editor tab | **Stub** | “Coming soon” placeholder |

## P3 build order (approved direction)

1. **P3-M2** — Enforce AI RBAC + ACL fixes (security gate before expanding features)
2. **P3-M3–M5** — BYOAI connections, chat, MCP bidirectional
3. **P3-M6–M7** — Automation plugin extract + full admin/E2E
4. **P3-M8** — Docs
5. **P3-M9** — Closure

## Human decisions needed

- [ ] Approve automation **move to plugin** (deactivatable) vs keep core with plugin-style API only
- [ ] Default `require_approval` for new AI connections: recommend **true**
- [ ] MCP API keys: one key per AI connection or site-wide MCP key?
