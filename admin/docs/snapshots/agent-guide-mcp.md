# Agent Guide — MCP & agents

## Summary

When Agent Guide is **published** and **publishing enabled**, Birkly exposes guide context to connected AI agents via MCP tools and a dedicated MCP resource. Agents with `read` permission can retrieve summaries, products, claims, product graphs, and the sources-of-truth registry without scraping your site.

This complements — does not replace — the public HTTP API and well-known JSON file.

## Prerequisites

1. Agent Guide published (`published_version` ≥ 1)
2. `publication_settings.enabled` = true
3. MCP connection configured ([MCP connections](../01-admin/mcp-connections.md))
4. Agent role includes `read` permission

Draft content is **never** exposed via MCP.

## MCP resource

| URI | Content |
|-----|---------|
| `birkly://brand-context` | Published Agent Guide, **summary** view JSON (legacy URI name) |

Use for lightweight agent system prompts or context injection. Summary includes `sources_of_truth` and `usage_hints.follow_sources_of_truth`.

## MCP / AI read tools

Registered in Birkly’s AI tool registry (`core/ai_tool_registry.php`):

### get_agent_guide_context

Primary tool. Legacy alias: **`get_brand_context`**.

```json
{
  "view": "summary | full | products | claims"
}
```

| view | Returns |
|------|---------|
| summary | Identity, positioning, supported claim statements, `evidence_ids`, `sources_of_truth`, usage_hints |
| full | Complete enriched export (includes top-level `evidence[]`) |
| products | `products_services` array with resolved sources |
| claims | Supported `claims[]` plus linked `evidence[]` for all supported claims |

Returns error if not published or disabled.

### list_agent_guide_products

No arguments. Legacy alias: **`list_brand_products`**.

Returns published `products_services[]` (public products only).

### get_agent_guide_claim

```json
{ "id": "claim_x" }
```

Legacy alias: **`get_brand_claim`**.

Returns **claim graph** (same as public API `?action=claim&id=x&graph=1`):

```json
{
  "claim": { "id": "…", "statement": "…", "evidence_ids": ["…"], "sources": [] },
  "evidence": [
    { "id": "…", "type": "external_url", "url": "https://…", "verification_status": "user_verified" }
  ],
  "sources": [],
  "usage_hints": { "repeat_only_with_evidence": true }
}
```

Supported claims only. Use this tool (not summary alone) when you need proof URLs for a claim.

### get_agent_guide_product

```json
{ "id": "prod_x" }
```

Legacy alias: **`get_brand_product`**.

Returns **product graph**:

```json
{
  "product": { … },
  "sources": [ … ],
  "linked_claims": [ … ],
  "usage_hints": {
    "follow_sources_for": "full specs, pricing, purchase links, and proof"
  }
}
```

Product id or name match. Respects `public: false` exclusion.

## HTTP public API parity

MCP tools read the same published snapshot as:

- `/.well-known/agent-guide.json` (default)
- `/.well-known/brand-context.json` (legacy)
- `/api/public/ai_presence.php`

Graph equivalents:

| MCP | HTTP |
|-----|------|
| get_agent_guide_product | `?action=product&id=x&graph=1` |
| get_agent_guide_claim | `?action=claim&id=x&graph=1` |

See [Public API](agent-guide-public-api.md).

## Agent integration patterns

### Pattern 1 — Summary + sources in system prompt

1. Agent session starts
2. Tool call: `get_agent_guide_context({ view: "summary" })`
3. Inject JSON into system message
4. Instruct: “Read `sources_of_truth` first; cite sources[] and evidence URLs for factual claims”

Low token cost; good for general Q&A about the site or organization.

### Pattern 2 — Product lookup

User asks about a specific offering:

1. `list_agent_guide_products()` or summary first
2. `get_agent_guide_product({ id: "prod_widget" })`
3. Follow `sources[].url` for specs/pricing (agent browsing or user handoff)

### Pattern 3 — Claim verification

User asks “Are you SOC2 certified?”:

1. `get_agent_guide_context({ view: "full" })` or claim-specific tool
2. Find supported claim matching topic
3. Resolve evidence URLs from linked evidence
4. Answer with citation links only — do not invent audit details

### Pattern 4 — Sources-first depth

Guide context gives the map; CMS tools give depth:

```
get_agent_guide_context(summary)
  → read sources_of_truth[narrative]
  → user asks product detail
get_agent_guide_product(id)
  → canonical source is cms_entry
read_entry(collection, slug)
  → full specs from CMS
```

Aligns with Agent Guide “index layer + declared sources” design.

## Claude Desktop / stdio bridge

Standard Birkly MCP setup ([MCP connections](../01-admin/mcp-connections.md)) exposes all read tools. Ensure the connecting user’s role has `read`.

Test:

> “Use get_agent_guide_context with view summary and tell me what this organization is known for.”

Legacy tool names (`get_brand_context`, etc.) still work.

## Admin AI chat

The admin **AI chat** may also dispatch read actions via `api/ai_read_actions.php` — same backends as MCP for guide tools.

Standard inbound MCP exposes **read** tools only. Draft edit and publish use admin UI, authenticated `/api/ai_presence.php`, or **`cms_admin_action`** (below).

## MCP admin actions (`cms_admin_action`)

When the connecting user has `manage_settings`, these write actions are available via `cms_admin_action` with `action` set to the slug below:

| Action | Purpose |
|--------|---------|
| `agent_guide.get` | Read draft or published Agent Guide state |
| `agent_guide.save_draft` | Save draft JSON |
| `agent_guide.publish` | Publish draft to public endpoints |
| `agent_guide.unpublish` | Stop serving public endpoints (draft preserved) |
| `agent_guide.cms_refresh` | Sync narrative CMS sources into draft fields |
| `agent_guide.copilot` | AI Assist suggestions (review before save) |
| `agent_guide.wizard_generate` | Generate draft from CMS collections |
| `agent_guide.validate_links` | Run link health check on draft |

Typical admin-agent flow:

1. `agent_guide.get` — inspect current draft
2. User approves edits in admin, or agent calls `agent_guide.save_draft` with validated payload
3. `agent_guide.validate_links` before publish
4. `agent_guide.publish` — go live
5. `agent_guide.unpublish` — take down without losing draft

Draft saves and publish require **`manage_settings`**. Read tools require only **`read`** and return **published** data only.

## Caching & freshness

- Published MCP responses reflect last **Publish** action
- Public HTTP cache: 5 minutes (`Cache-Control: max-age=300`)
- Header `X-Agent-Guide-Version` on HTTP — MCP consumers should note `published_version` in JSON

After republish, agents may need session refresh to pick up new version.

## Security

- Same permission model as other read tools
- No draft leakage
- Published JSON is already public via HTTP — MCP adds authenticated convenience for connected agents, not secret data

## Error handling

| Condition | Tool result |
|-----------|-------------|
| Never published | Error: Agent Guide not published |
| Publishing disabled | Error / null |
| Unknown claim/product id | Error: Not found |
| Unsupported claim id | Error (supported only) |

## Related docs

- [Agent Guide overview](../02-content/agent-guide.md)
- [Public API](agent-guide-public-api.md)
- [MCP connections](../01-admin/mcp-connections.md)
- [AI overview](../01-admin/ai-overview.md)

## Further reading

- [Agent Guide public API](agent-guide-api.md)
- [AI on localhost](ai-on-localhost.md)
- [Choose your stack](choose-your-stack.md)
- [Connections API reference](connections-api-reference.md)
