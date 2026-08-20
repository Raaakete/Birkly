# Built-in AI assistant playbook

Use for: floating chat in admin, API key providers, roles, approvals, playbooks in chat.

Playbooks are tips for better outcomes. **Role** and **Require approval** on the connection are what actually gate writes.

## Setup

1. **Settings → AI → Built-in assistant** → Quick connect
2. Provider: OpenAI, Anthropic, Google, OpenRouter, Ollama, Custom
3. API key (skip for local Ollama)
4. Test connection → Connect
5. Set **Role** (Viewer / Author / Editor / Admin) — maps to tool permissions
6. **Require approval** — recommended; writes queue in chat **Approvals** tab

**Note:** ChatGPT Plus / Claude Pro subscriptions ≠ API access. Need developer API key.

## How chat uses playbooks

System prompt includes playbook catalog. For complex tasks AI should call `read_birkly_docs` with topic slug before acting.

Site builds: read `site-building` once, avoid discovery loops (see loop fixes in orchestration).

## Approvals flow

1. User asks for change
2. AI emits write actions (JSON or native tools)
3. If `require_approval`: actions appear in **Approvals** tab
4. User approves → execution
5. Session notes appended to conversation (files written, collections updated)

Admin role with `require_approval: false` can execute writes immediately (use carefully).

## Roles & permissions

| Role | Typical access |
|------|----------------|
| Viewer | Read-only tools |
| Author | Create/edit own content |
| Editor | Manage content, media |
| Admin | Full tools including settings, plugins, project site |

Permission map: `core/ai_permissions.php`.

## Slug normalization

AI-created slugs: lowercase, hyphens, `[a-z0-9-]`. Underscores auto-normalized.

## Tool coverage (reference)

Some tasks are common in admin UI; many also have MCP/chat tools when the role allows:

| Task | Tools (when permitted) | Admin UI |
|------|------------------------|----------|
| Admin users | `cms_create_user`, `users.*` | Settings → Users |
| Public API keys | `update_collection` (`entry_method`, `api_settings`) | Collection settings |
| Automation | `automation_*`, `automation_admin_action` | Automation admin |
| Agent Guide | `cms_admin_action` → `agent_guide.*` | Agent Guide |

If a tool is not in `tools/list` for this connection, use the admin path or ask the owner to adjust the role.

## Troubleshooting

| Issue | Check |
|-------|-------|
| 401 Unauthorized | Session, CSRF, connection id |
| Actions not executing | Approvals tab pending |
| Loops on project reads | Read `site-building` tips in playbooks-index |
| Wrong tool for site build | Use `write_project_file` not `create_page` |

## Related

- `mcp-setup` — external AI (different approval UX)
- `playbooks-index` — task routing
- `settings-roles` — custom roles
