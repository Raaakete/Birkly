# Agent Guide playbook

Use for: publishing brand/product facts for external AI (Claude, ChatGPT, crawlers), `/.well-known/agent-guide.json`, grounded claims with evidence.

Playbooks are guidance only — **role permissions** and **MCP approval settings** decide what your connection can do.

## What it is

**Agent Guide** (AI Presence) is structured brand context: who you are, products, supported claims with evidence URLs. Published at `/.well-known/agent-guide.json` (and brand context variant).

**Hub tabs:** Guide · Tools · Publish only. **CMS AI connections** (MCP / built-in assistant keys) live under **Settings → AI** — never re-add a Connections tab to Agent Guide.

External AI and MCP clients read via:
- `get_agent_guide_context`, `get_agent_guide_product`, `get_agent_guide_claim`
- MCP resource `birkly://brand-context`

## Tools (capability map)

**Published read** (typical `read` permission):

- `get_agent_guide_context`, `get_agent_guide_product`, `get_agent_guide_claim`, `list_agent_guide_products`
- MCP resource `birkly://brand-context`

These return **published** Agent Guide data, not the editable draft.

**Draft / admin** (when the connection role includes the needed permissions, e.g. `manage_settings`):

- `cms_list_admin_actions` → group `agent_guide`
- `cms_admin_action` with `agent_guide.save_draft`, `agent_guide.publish`, `agent_guide.unpublish`, `agent_guide.cms_refresh`, `agent_guide.copilot`, `agent_guide.wizard_generate`, `agent_guide.validate_links`, …

`agent_guide.save_draft` replaces list fields (e.g. `products_services`) entirely when you send a full array — match the shape from `agent_guide.get`.

Whether to use admin UI or MCP is up to the site owner and what their connection allows — not something playbooks restrict.

## Setup workflow (guide the user)

### 1. Sources of truth
Add where facts come from:
- CMS entry or collection
- Website URL
- Uploaded document

Admin: Agent Guide → **Sources** tab.

### 2. Context / identity
Fill brand name, positioning, tone, audience. **Context** tab.

### 3. Products
Each product: name, description, links to grounded sources. **Products** tab.

### 4. Claims
Supported statements with evidence URLs (must trace to sources). **Claims** tab.

### 5. Publish
Publish when ready → live at `/.well-known/agent-guide.json`.

## When user asks AI to "set up Agent Guide"

1. `read_birkly_docs` `agent-guide`
2. `get_agent_guide_context` — see current published state
3. Explain step-by-step admin path (Sources → Context → Products → Claims → Publish)
4. Optionally `list_collections` / `list_entries` to suggest which CMS content to link as sources

## AI assistant use cases

| User request | AI action |
|--------------|-----------|
| "What's in our Agent Guide?" | `get_agent_guide_context` |
| "List our products for AI" | `list_agent_guide_products` or context view |
| "Verify claim X" | `get_agent_guide_claim` |
| "Add a new product" | `agent_guide.save_draft` (if permitted), or Admin → Agent Guide → Products |

## Coexistence with MCP

Inbound MCP (`mcp-setup`) exposes tools + `birkly://brand-context`. Agent Guide is **outbound-facing brand truth**; MCP is **inbound CMS access**.

## Tips (quality, not policy)

- Ground claims with evidence URLs — easier to publish and trust
- `get_agent_guide_context` before large edits — know what's already live
- Agent Guide is brand truth for external AI; separate from generic chat system prompts

## Related playbooks

- `mcp-setup` — connect external AI to CMS
- `content-model` — CMS entries used as sources
