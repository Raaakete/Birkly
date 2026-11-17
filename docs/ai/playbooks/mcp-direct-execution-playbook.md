# MCP direct execution playbook

Use when connected via **inbound MCP** (Cursor, Claude, ChatGPT) and you are about to change content, collections, or `project/` files.

Playbooks are tips; **role permissions + client approval** govern what runs. This playbook governs **how** to run allowed work efficiently.

## Golden rule

**One MCP tool call = one platform action.** Repeat `create_entry`, `write_project_file`, or `cms_admin_action` as many times as needed. That is cheaper and more reliable than writing a script.

Inbound MCP is the write path. Do **not** substitute shell, PHP runners, curl loops, or direct edits under `content/` for normal site and CMS tasks.

## When you are on MCP (vs repo engineer)

| Situation | Do this |
|-----------|---------|
| User wants site content, collections, `project/` templates | MCP tools only |
| User wants Birkly **product** code (core PHP, tests, plugins in git) | Repo checkout + normal dev workflow |
| Workspace has **both** git clone and MCP | Tenant work → MCP; product work → git |

## Anti-patterns (inbound MCP)

| Do not | Do instead |
|--------|------------|
| PHP/bash “seed” script looping over posts or products | `create_entry` per item, or `cms_bulk_entry_action` / `entries.bulk` when truly bulk |
| Edit `content/collections/**/entries/*.json` on disk | `create_entry` / `update_entry` |
| curl/fetch to public API to create admin content | `create_entry` with `status: published` |
| Python/Node “dispatcher” around `cms_admin_action` | Call `cms_admin_action` (or named tool) once per step |
| `cms_list_admin_actions` then generate a local runner | Read catalog once, then call actions directly |
| Build a “harness” for a bakery/portfolio site | Follow `site-building` tool sequence (unless auditing MCP — see `mcp-qa`) |

**On-disk paths** in `content-model` and the context pack describe storage layout for humans and self-host debugging. They are **not** an invitation to bypass MCP tools.

## Built-in chat vs MCP

**Built-in admin chat** can batch internal steps via structured `actions[]` JSON in the chat executor.

**Inbound MCP** uses `tools/call` — **one tool per invocation**. There is no batch envelope; use platform bulk tools when you need many rows at once.

## Bulk (when repetition is real bulk)

| Need | Tool |
|------|------|
| Many entries publish/unpublish/trash | `cms_bulk_entry_action` or `cms_admin_action` → `entries.bulk` |
| Many media variants | `cms_admin_action` → `media.bulk_create_variants` (limits apply) |

Do not write a script “because there are five menu items.” Five `create_entry` calls are correct.

## Happy path (site build)

Same as **`site-building`** — execute with tools, not scripts:

1. `get_project_info` + `list_collections` (once)
2. `create_collection` / `update_collection` (full schema)
3. `create_entry` (published seed data)
4. `write_project_file` (`index.html`, `css/style.css`, …)
5. Verify in browser — `birkly-client.js` on every templated page

Plugin shops/workflows: `list_plugins` → `commerce_*` / `automation_*` (see **`mcp-capabilities`**).

## When scripting **is** appropriate

- **`mcp-qa`** audit on staging (`mcp-qa-harness`, matrix JSON, `php tests/cms_mcp_admin_test.php`)
- Plugin development: `php scripts/scaffold-plugin.php` (user asked for plugin dev)
- CMS core development in the Birkly git repo (not tenant content)

## If stuck

1. `read_birkly_docs` → `mcp-capabilities` or task-specific playbook (once)
2. `read_collection` / `get_project_info` — ground truth
3. Call the tool — do not add automation layers

## Related

- `mcp-capabilities` — tool families, admin actions, plugins
- `ai-quality-guide` — schema before entries before HTML
- `mcp-qa` — full-tool audit harness (not for casual builds)
- `site-building` — project site tool sequence