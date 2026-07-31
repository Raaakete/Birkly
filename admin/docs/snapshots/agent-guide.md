# Agent Guide

## Summary

**Agent Guide** is Birkly’s structured context layer for AI systems and connected agents. It sits on top of your existing CMS content, Media library, and other canonical sources — it does **not** replace them. You declare **sources of truth** first, then build concise summaries (identity, offerings, claims, voice), and **publish** a versioned, machine-readable snapshot on your **public site domain** so agents, crawlers, and integrations can discover and use it responsibly.

Works for individuals, teams, enterprises, nonprofits, and any site that wants a single authoritative guide for automated assistants.

**Product promise:** Publish an authoritative, structured, evidence-aware representation that is easier for AI systems to discover and use. This is **not** a guarantee that every AI model will ingest your guide, and it is **not** a chatbot builder.

## What problem does it solve?

When someone asks an assistant “What does this organization do?” or “Is their offering a good fit for small teams?”, the answer often comes from scattered pages, outdated summaries, or guesswork. Agent Guide gives you one place to:

1. **Declare sources of truth** — CMS entries, websites, documents, media, marketplaces — where agents should read first.
2. **Curate** the story you want AI to tell (identity, positioning, products, voice).
3. **Ground** that story in evidence (URLs, certifications, review pages, CMS refs).
4. **Publish** a versioned JSON snapshot agents can read without scraping your whole site.
5. **Link** summaries to deeper sources so agents know where to verify details.

Think of Agent Guide as the **index and authority map** — not the full encyclopedia.

## Mental model

```
┌─────────────────────────────────────────────────────────────┐
│  Sources of truth (registry)                                │
│  CMS entries/collections · websites · documents · media     │
└───────────────────────────┬─────────────────────────────────┘
                            │ ground summaries & AI Assist
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  CMS Content & Media (underlying content)                   │
│  About pages, product entries, images, blog posts           │
└───────────────────────────┬─────────────────────────────────┘
                            │ references (IDs, slugs, URLs)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  Agent Guide (draft → publish)                              │
│  Context · products · claims · evidence · cross-links       │
└───────────────────────────┬─────────────────────────────────┘
                            │ publish
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  Public export (on your site domain)                        │
│  /.well-known/agent-guide.json                              │
│  /api/public/ai_presence.php                                │
│  Optional JSON-LD · MCP tools · graph views                 │
└─────────────────────────────────────────────────────────────┘
```

**Draft vs published:** All edits save to a **draft**. Public endpoints serve the **published** snapshot only until you publish again. Iterate safely without affecting what agents see.

## Recommended workflow

Follow the admin workflow strip on the Overview tab:

1. **Sources** — Declare where agents should read the truth (start here).
2. **Context** — Identity, positioning, terminology, relationships.
3. **Products & Services** — Structured catalog with source links.
4. **Claims & Evidence** — Factual statements and proof.
5. **Publish** — Enable endpoints, preview, go live.

Summaries in Context and Products should **reflect** your declared sources — not invent parallel truth.

## Where to find it

Admin sidebar → **Agent Guide** (between Media and Marketplace).

**URLs:** `/admin/?page=agent-guide` (legacy alias: `?page=ai-presence`).

**Permissions:**

| Action | Permission |
|--------|------------|
| View Agent Guide | `read` |
| Edit, publish | `manage_settings` |

## Admin tabs (at a glance)

| Tab | Purpose |
|-----|---------|
| **Overview** | Completeness score, source health, CMS sync drift, link checks, public URLs |
| **Sources** | `sources_of_truth[]` registry — CMS, websites, documents, media, marketplaces |
| **Context** | Identity, positioning, terminology, relationships |
| **Products & Services** | Structured catalog with typed source links |
| **Claims & Evidence** | Factual statements, proof sources, review workflow |
| **Media** | Per-asset context (what it represents, intended use, restrictions) |
| **Publish** | Enable publishing, version history, staleness warnings, AI preview |

## AI Assist sidebar

The sticky save bar and right-hand **AI Assist** panel help you fill gaps without auto-saving:

- **Auto-fill / Fill gaps / Fill 100%** — AI suggestions you approve one-by-one or in bulk
- **Suggest sources / cross-links** — Propose `sources_of_truth` entries, product→CMS links, evidence URLs
- **Guide Studio** (Advanced) — Multi-collection wizard from CMS sources
- Per-field **Generate** on Context fields — Targeted suggestions applied directly to the field

Suggestions are **never auto-saved** until you accept them. AI Assist prefers declared **sources of truth** before inferring.

## Documentation map

| Doc | Audience | Contents |
|-----|----------|----------|
| [Getting started](agent-guide-getting-started.md) | Beginners | First publish in ~30 minutes |
| [Workflows](agent-guide-workflows.md) | Editors | Tab-by-tab how-to |
| [Claims, evidence & cross-links](agent-guide-claims-evidence-and-links.md) | Editors + strategists | Proof, marketplace/review links, source roles |
| [AI Assist](agent-guide-ai-assist.md) | Power users | Copilot modes, Guide Studio, field AI |
| [Best practices](agent-guide-best-practices.md) | Everyone | Quality, governance, what not to do |
| [Data model](agent-guide-data-model.md) | Developers | Schema, storage, completeness scoring |
| [Public API](../04-integration/agent-guide-public-api.md) | Integrators | Endpoints, views, graph, caching |
| [MCP & agents](../04-integration/agent-guide-mcp-and-agents.md) | Developers | MCP tools, resources, agent patterns |

## Quick start (5 steps)

1. **Sources** — Add at least one **narrative** source of truth (CMS entry, website, or document).
2. **Context** — Official name, short description; link summaries to sources via `derived_from` where helpful.
3. **Products** — Add at least one product/service with a **canonical source link** (CMS entry or public URL).
4. **Claims** — Add 2–3 factual claims; attach evidence with verifiable URLs; mark reviewed claims as **Supported**.
5. **Publish** — Enable publishing, click **Publish**, verify `/.well-known/agent-guide.json` on your public domain.

Full walkthrough: [Getting started](agent-guide-getting-started.md).

## What gets published

Published JSON (`agent_guide.v1`; legacy exports may show `ai_presence.v1`) includes:

- **`sources_of_truth[]`** — Resolved registry of canonical sources
- **`guide_meta`** — Product marker and summary-layer hints
- **identity**, **positioning**, **communication_guidance**
- **products_services[]** with resolved **`sources[]`** (public URLs)
- **claims[]** with **evidence** and optional direct **sources[]**
- **media_context**, **relationships**
- **`usage_hints`** — Guidance for AI consumers (authoritative vs marketing fields; follow sources of truth)
- **`identity.derived_from`** — Which sources ground summary fields
- Optional **`json_ld`** — Schema.org graph (Organization, Product/Service, Claim)

Legacy field **`connected_content[]`** is migrated into `sources_of_truth[]` on save.

## Related Birkly features

- **Settings → Site delivery** — Public site URL where well-known and API are served
- **Settings → AI connections** — Optional BYOAI for Assist (rules-only mode works without AI)
- **MCP connections** — Inbound agents can read published guide context via MCP tools
- **Admin AI chat** — Complementary; Agent Guide is structured publish, not conversational UI

## FAQ

**Does Agent Guide replace my About page or product CMS entries?**  
No. Content stays in Content; Agent Guide references and summarizes for agents.

**Why sources of truth first?**  
Agents need a declared reading order. Summaries without sources invite hallucination; the registry tells consumers where to verify.

**Will ChatGPT automatically use my guide?**  
Not guaranteed. You make discovery easier via well-known JSON, public API, and JSON-LD. Adoption depends on crawlers, integrations, and MCP-connected tools.

**Can I hide draft work from the public?**  
Yes. Only **published** snapshots are public. Disable publishing or don’t publish until ready.

**Do I need AI connections configured?**  
No. Rule-based suggestions and manual editing work without AI. Connections unlock Copilot, Fill 100%, and smarter suggestions.

**Do I need a vector database?**  
No. Agent Guide is **curated truth + evidence + source links** — the right model for “what is officially true about our brand?” Vector search helps **fuzzy search over huge unstructured archives** (e.g. support tickets). That is a different product problem; optional semantic search could be a **future plugin**, not core CMS.

**Legacy paths and tools?**  
`/.well-known/brand-context.json`, MCP `get_brand_context`, and admin `?page=ai-presence` remain supported as aliases.

## Further reading

- [Content model overview](content-model-overview.md)
- [Collections](collections.md)
- [Entries & collections](entries.md)
- [Collection groups](collection-groups.md)
