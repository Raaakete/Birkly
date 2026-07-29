# Agent Guide — getting started

## Summary

This tutorial walks you from an empty Agent Guide draft to a **published** guide on your public site domain. On first visit, a **guided setup wizard** is the default path — most teams can reach first publish in **~15 minutes** using Essentials view and the wizard alone, without opening Advanced tabs.

**Prerequisites:**

- Admin access with `manage_settings`
- At least one CMS collection with content (e.g. About, Products)
- **Settings → Site delivery** configured with your public site URL (for well-known verification)

## First visit — guided setup wizard

1. Sidebar → **Agent Guide** (or `/admin/?page=agent-guide`).
2. If the draft is empty and setup has not been completed or skipped, the **Set up your Agent Guide** wizard opens automatically after the page loads.
3. On **Welcome**, choose:
   - **Guided setup** — step-by-step flow, best for first publish (~15 min), or
   - **Advanced editing** — skip the wizard and land in Essentials or full tabs.

The wizard has **7 steps**:

| Step | What you do |
|------|-------------|
| **Welcome** | Choose guided setup or advanced editing |
| **Identity** | Official name, short description, website |
| **Sources** | At least one narrative source (CMS entry/collection or external URL) |
| **Enrich** | Optional positioning — best known for, differentiators |
| **Claims** | 1–3 factual claims with proof URL |
| **Publish** | Enable publishing and publish |
| **Success** | Live URL, version, copy/open public JSON |

Each step explains **what** you are filling in, **why** it matters, and **what appears in public JSON** after publish. You can **skip individual steps** or close the wizard — it is not blocking. Closing with unsaved changes prompts for confirmation.

After wizard completion (or skip), the page defaults to **Essentials** view.

## Essentials vs Advanced view

Use the **Essentials / Advanced** toggle at the top of the page:

| View | Best for |
|------|----------|
| **Essentials** (default) | Day-to-day edits — identity, source/claim previews, key messages, publish controls |
| **Advanced** | Full tab set — Overview, Sources, Context, Products, Claims, Media, Publish |

**Essentials** sections:

- **Who we are** — official name, short description, website
- **Sources** — preview of declared sources (link to Sources tab for full registry)
- **Key messages** — best known for, differentiators
- **Claims** — preview of claims (link to Claims tab for evidence workflow)
- **Publish** — enable publishing, **Publish**, **Save draft**

Your view choice is remembered in the browser. The **AI Assist** sidebar (floating button) appears only in **Advanced** view.

For first publish, you do **not** need Advanced tabs — the wizard and Essentials cover identity, sources, positioning, claims, and publish.

## Status strip and publish feedback

A **status strip** at the top of the page shows live state at a glance:

| Badge | Meaning |
|-------|---------|
| **Not live** | Publishing enabled but never published |
| **Live · vN** | Published version N is on your public URL |
| **Unpublished changes** | Draft differs from the live snapshot |
| **Publishing off** | Public endpoints return 404 until enabled |

The strip also shows **last published** time and the well-known URL when configured. **Set up guide** re-opens the wizard; **Publish updates** appears when live with draft changes.

### Unmissable publish success

Publish feedback is designed to be obvious — not only a small toast:

- **First publish** — a **success modal** with version, timestamp, public JSON URL, and actions to copy URL or open JSON.
- **Republish** — a **banner** below the sticky save bar with the same details (dismissible), plus a toast.

The sticky save bar message also updates: warnings when not live or when draft has unpublished changes; success when draft matches the published snapshot.

## Wizard walkthrough (technical detail)

### Identity

| Field | Example |
|-------|---------|
| Official name | Acme Co |
| Short description | One sentence: what you are and who you serve |
| Website | `https://example.com` |

Required for the wizard to continue. These fields appear in public JSON under `identity`.

### Sources of truth

Before writing summaries elsewhere, declare where agents should read the truth.

Pick at least one **narrative** source in the wizard (CMS collection + optional entry, or external URL):

| Type | Example | Role |
|------|---------|------|
| `cms_entry` | Your About page entry | `narrative` |
| `cms_collection` | All blog posts collection | `reference` |
| `website` | `https://example.com` | `narrative` |
| `document` | PDF spec in Media library | `reference` |

**Source types:** `cms_entry`, `cms_collection`, `website`, `document`, `media_asset`, `marketplace`, `review_platform`, `other`

**Source roles:** `narrative` (who you are), `proof` (evidence), `reference` (background), `archive` (historical)

Completeness awards full credit when you have at least one **narrative** source.

### Enrich (optional)

Add positioning messages agents can quote:

- **Best known for** — one per line
- **Differentiators** — one per line

**AI Assist in this step is optional.** Click **Suggest from sources** when you have an AI connection — suggestions appear in a list with **Accept** and **Reject** for each item. Nothing is applied until you accept explicitly; you can continue manually without AI.

### Claims

Add 1–3 factual statements with a **proof URL** (press release, case study, review page, etc.). Supported claims with evidence appear in the public summary view. In Advanced editing, use the Claims tab for full evidence workflow and status management.

### Publish

Enable **Publishing**, confirm the public path (default: `/.well-known/agent-guide.json`), and click **Publish**. The wizard **Success** step shows your live URL and version. Legacy well-known path `/.well-known/brand-context.json` is also listed when applicable.

## Optional — Advanced tabs

Use **Advanced** view when you need the full editor. Recommended workflow: **Sources → Context → Products → Claims → Publish**.

### Context tab (identity depth)

Beyond Essentials fields, fill full description, what we do, industry, locations, social profiles, terminology, and voice. Use **Generate** per field when AI Assist is available — it reads **sources of truth** first.

If your short description comes from a CMS About entry, accept a **derived_from** suggestion so published JSON includes the resolved source URL.

### Products tab

1. **Add product or service** — name, type (product / service / bundle), short description.
2. **Source links** (bottom of modal):
   - **Canonical detail** → CMS entry *or* public product URL on your site
   - Optional: **Marketplace** → listing URL; **Review** → G2, Trustpilot, etc.

Completeness gives partial credit for having products and **full product credit when at least one product has a grounded source**.

### Claims tab (full evidence workflow)

Claims are **statements you’re willing to stand behind**. Evidence is **proof**.

1. **Add claim** → write a factual statement; start with `pending_review`.
2. Attach evidence — external URL, CMS entry, marketplace listing, or review platform.
3. Mark **Supported** only when you have solid evidence (typically ≥2 sources for high-stakes claims).

### Link health (Overview tab)

1. **Suggest cross-links** (AI Assist or Overview) — review suggestions; accept product→CMS links, evidence URLs, derived-from mappings.
2. **Check all links** — validates external URLs, updates link status on draft.

Fix any broken links before republishing.

### Save and publish (Advanced)

1. Edit any tab → **Save draft** (validates schema).
2. Review completeness / staleness on Overview.
3. **Publish** from the sticky bar or Publish tab.

Never rely on auto-save for AI suggestions — accept explicitly in AI Assist (or wizard Enrich step) first, then save draft.

## Verify on your public domain

On your **public site** (not necessarily the admin host):

```bash
curl -s "https://YOUR-PUBLIC-SITE/.well-known/agent-guide.json" | head
curl -s "https://YOUR-PUBLIC-SITE/api/public/ai_presence.php?action=brand&view=summary"
```

Expect JSON with `schema_version: agent_guide.v1`, `published_version`, `sources_of_truth`, `identity`, and your products/claims.

If you get **404**:

- Publishing disabled, or never published
- Wrong domain — check Settings → Site delivery
- Hosting mode — for `project` mode, file is also written under `project/.well-known/`

## Preview how AI might answer (Publish tab)

Use **Ask about your published guide** with a test question:

- “What is [name] known for?”
- “What products or services are offered?”

Without an AI connection, preview uses keyword matching on published fields. With a connection, preview can use full published JSON retrieval.

## What’s next?

| Goal | Read |
|------|------|
| Deeper tab workflows | [Workflows](agent-guide-workflows.md) |
| Evidence & marketplace strategy | [Claims, evidence & cross-links](agent-guide-claims-evidence-and-links.md) |
| Fill profile faster with AI | [AI Assist](agent-guide-ai-assist.md) |
| Governance & quality | [Best practices](agent-guide-best-practices.md) |
| Build an integration | [Public API](../04-integration/agent-guide-public-api.md) |
| Connect Claude Desktop / MCP | [MCP & agents](../04-integration/agent-guide-mcp-and-agents.md) |

## Further reading

- [Content model overview](content-model-overview.md)
- [Collections](collections.md)
- [Entries & collections](entries.md)
- [Collection groups](collection-groups.md)
