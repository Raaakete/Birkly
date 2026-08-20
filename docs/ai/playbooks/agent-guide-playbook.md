# Agent Guide playbook

Use for: publishing brand/product facts for external AI, `/.well-known/agent-guide.json`, `/llms.txt`, grounded claims with evidence, Agent Guide hub (Guide · Tools · Publish).

## What it is

**Agent Guide** is structured brand context: sources of truth, identity, products, supported claims with evidence. Published on your **public site domain** at:

- `/.well-known/agent-guide.json` (primary)
- `/llms.txt` (Markdown discovery map)
- `/api/public/ai_presence.php` (JSON API with views)

**Hub tabs:** Guide · Tools · Publish only. **CMS AI connections** live under **Settings → AI** — never re-add a Connections tab to Agent Guide.

External AI reads via MCP:

- `get_agent_guide_context`, `get_agent_guide_product`, `get_agent_guide_claim`
- MCP resource `birkly://brand-context`

Admin agents with `manage_settings` may also use `cms_admin_action` → `agent_guide.*` (publish, unpublish, save_draft, etc.).

## Hub map

| Tab | Admin path | Purpose |
|-----|------------|---------|
| **Guide** | `?page=agent-guide&zone=guide` | Sources, context, products, claims, media |
| **Tools** | `?page=agent-guide&zone=tools` | WebMCP website tools — see `webmcp-tools` playbook |
| **Publish** | `?page=agent-guide&zone=publish` | Go live, hosting modes, unpublish, download JSON |

**Essentials vs Advanced:** Essentials is the default one-page view; Advanced exposes full tabs (Overview, Sources, Context, Products, Claims, Media).

## Flow: first publish (`agent-guide-first-publish`)

1. Run wizard from status strip **Set up guide** (optional).
2. **Sources** — Add ≥1 **narrative** source of truth (CMS entry, website, document).
3. **Context** — Official name, short description (use Generate from sources when AI connected).
4. **Products** — ≥1 offering with canonical source link.
5. **Claims** — 2–3 statements + evidence URLs; mark reviewed as **Supported**.
6. **Publish tab** — Enable publishing → **Publish**.
7. Verify:
   - `curl -s https://YOUR-SITE/.well-known/agent-guide.json | jq .published_version`
   - `curl -s https://YOUR-SITE/llms.txt | head`
   - `get_agent_guide_context({ view: "summary" })` via MCP

## Flow: sources and context (`agent-guide-sources-and-context`)

1. Declare `sources_of_truth[]` before writing summaries (narrative, proof, reference, archive roles).
2. Edit Context fields to reflect sources — use `derived_from` for grounded short description.
3. Overview → **CMS sync** panel — refresh from CMS when drift detected.
4. **Check all links** on Overview before republish.

## Flow: claims and evidence (`agent-guide-claims-evidence`)

1. Write claim → status `pending_review` until reviewed.
2. Attach evidence (URL, CMS ref, marketplace, certification).
3. Mark evidence **user_verified** after checking URL.
4. Set claim status **supported** (may auto-promote when ≥2 evidence — still review manually).
5. Only **supported** claims export to summary/public/MCP claim tools.
6. Run **Check all links** — fix broken/redirect evidence before publish.

**Important:** Marketing claims in Agent Guide are curated truth — verify against codebase/product reality before marking supported (e.g. dependency-free core, draft/review behavior).

## Flow: publish and hosting (`agent-guide-publish-hosting`)

1. Set **Settings → Site delivery** public site URL.
2. Choose hosting mode on Publish tab (Birkly `project/` vs external vs none).
3. Publish → snapshot increments `published_version`.
4. External hosting: download JSON or reverse-proxy well-known path.
5. **Unpublish** when public endpoints should 404 but draft must remain.
6. Republish after material CMS or evidence changes (watch staleness warnings).

## Flow: MCP read (`agent-guide-mcp-read`)

1. `get_agent_guide_context({ view: "summary" })` — identity, supported claims (with `evidence_ids`), `sources_of_truth`.
2. Claim question → **`get_agent_guide_claim({ id })`** — returns **claim graph**: `{ claim, evidence[], sources[], usage_hints }` with resolved proof URLs (not just `evidence_ids`).
3. All supported claims + evidence in one call → `get_agent_guide_context({ view: "claims" })` — includes `claims[]` and linked `evidence[]`.
4. Product question → `get_agent_guide_product({ id })` → follow `sources[].url`.
5. Depth → read `sources_of_truth` URLs or `read_entry` for CMS refs.

**Evidence rule:** Summary lists claim statements and `evidence_ids` only. Always call `get_agent_guide_claim` (or `view: "claims"`) before citing proof URLs.

## Flow: AI Assist (`agent-guide-ai-assist`)

1. Requires AI connection under **Settings → AI** (BYOAI).
2. Copilot / Fill gaps / Guide Studio suggest draft fields — **never auto-saved**.
3. User accepts suggestions → **Save draft** → **Publish**.
4. AI cannot skip human review for Agent Guide writes unless admin explicitly uses `agent_guide.save_draft` via MCP with approval.

## When user asks AI to "set up Agent Guide"

1. `read_birkly_docs` `agent-guide`
2. `get_agent_guide_context` — see current published state (if any)
3. Walk through flow **first publish** above in admin UI order
4. Optionally `list_collections` / `list_entries` to suggest CMS sources
5. Do **not** promise AI can publish without admin unless `manage_settings` + explicit publish action

## AI read tools (published data only)

| User request | AI action |
|--------------|-----------|
| "What's in our Agent Guide?" | `get_agent_guide_context` |
| "List our products for AI" | `list_agent_guide_products` or context view |
| "Verify claim X" | `get_agent_guide_claim({ id })` — returns `evidence[]` with URLs |
| "List all claims with proof" | `get_agent_guide_context({ view: "claims" })` |
| "Add a new product" | Admin UI or `agent_guide.save_draft` — not a standalone MCP read tool |

## Coexistence with other AI layers

| Layer | Playbook |
|-------|----------|
| Inbound MCP (CMS content) | `mcp-setup` |
| Built-in admin chat | `ai-connections` |
| Visitor website actions | `webmcp-tools` |
| Brand truth export | `agent-guide` (this file) |

## Anti-patterns

- Promising AI can publish Agent Guide without admin or `manage_settings`
- Inventing claims without evidence URLs
- Confusing Agent Guide with generic system prompt or CMS MCP
- Marking claims supported without verification
- Re-adding Connections tab to Agent Guide IA

## Related playbooks

- `webmcp-tools` — Agent Guide Tools tab / WebMCP
- `mcp-setup` — connect external AI to CMS
- `content-model` — CMS entries used as sources
- `ai-connections` — built-in assistant and approvals (separate from Agent Guide publish)
