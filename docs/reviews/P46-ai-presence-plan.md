# P46 — AI Presence (brand context for AI systems)

**Status:** Approved (2026-07-26) — **next active phase**  
**Repos:** Birkly (core) · Birkly-Docs (product + API docs)  
**Handoff:** `docs/reviews/P46-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P46-verification-matrix.md`  
**Gap register:** `docs/reviews/P46-gap-register.md`

---

## Problem

Brands manage website content in Birkly, but **AI systems and agents** need a **structured, evidence-aware, machine-readable** representation of who the brand is — separate from scattered pages and media.

Today Birkly offers **admin AI** (chat, MCP, connections) for *operators building the site*. There is no product surface for **publishing authoritative brand context outward** for machines.

Users should not need LLMs, RAG, vector DBs, or protocol expertise. They should **define what is true, link evidence, and publish**.

---

## Vision

**One canonical internal model → multiple export formats.**

```
Existing CMS (Content, Media)
        │
        ▼
   AI Presence (thin layer — references, not duplicates)
        │
        ├── Brand identity & positioning
        ├── Claims + evidence
        ├── Media context
        ├── Communication guidance
        └── Connected content refs
        │
        ▼
Canonical representation (draft | published)
        │
        ├── /.well-known/brand-context.json (public site)
        ├── /api/public/ai_presence.php (read API)
        ├── JSON-LD / Schema.org where natural
        └── Future protocol adapters (not in v1)
```

**Not in scope:** chatbot builder, replacement Content/Media, OpenAI-only stack, promise that every AI will understand the brand.

**Product promise:** *Publish an authoritative, structured, evidence-aware representation that is easier for AI systems and agents to discover and use.*

---

## Locked decisions (P46-D1–D14)

| ID | Decision |
|----|----------|
| **P46-D1** | **Core platform feature** — not a plugin; primary nav item |
| **P46-D2** | User-facing label: **AI Presence** (nav between Media and Marketplace) |
| **P46-D3** | Single workspace page with tabs: Overview · Context · Claims & Evidence · Media · Publish |
| **P46-D4** | Internal canonical JSON model — exports are adapters, not the source of truth |
| **P46-D5** | **Reference CMS entities** (collection, entry id/slug, media id) — minimize duplication |
| **P46-D6** | **Draft vs Published** — explicit Publish; public endpoints serve **published** only |
| **P46-D7** | Public canonical file: `/.well-known/brand-context.json` on **public site domain** (P43 hosting + `project/.well-known/` or route) |
| **P46-D8** | Read-only public API: `GET /api/public/ai_presence.php` (versioned JSON) |
| **P46-D9** | AI suggestions use existing `ai_connections` — **never auto-publish**; user approves each suggestion |
| **P46-D10** | Context Preview = **retrieval Q&A over published context** with visible sources — not a general chatbot |
| **P46-D11** | Completeness score = weighted checklist (meaningful, not vanity %) |
| **P46-D12** | Permissions: `manage_settings` for edit/publish; `read` for view-only (optional dedicated permission in M2) |
| **P46-D13** | Staleness: warn when linked CMS entries/media changed after `last_published_at` |
| **P46-D14** | Notify human **once** at P46-M14 closure |

---

## Data model (canonical internal)

Stored under `settings/ai_presence/` (or `data/ai_presence/` in runtime tree — **not committed**):

| File | Purpose |
|------|---------|
| `draft.json` | Working copy |
| `published.json` | Last published snapshot |
| `meta.json` | `last_published_at`, `published_version`, checksums |
| `versions/{n}.json` | Optional publish history (cap 50) |

**Schema version:** `ai_presence.v1` — `schema/ai_presence.schema.json`

```json
{
  "schema_version": "ai_presence.v1",
  "identity": {
    "official_name": "",
    "short_description": "",
    "full_description": "",
    "what_we_do": "",
    "industry": [],
    "categories": [],
    "locations": [],
    "website": "",
    "social_profiles": []
  },
  "positioning": {
    "best_known_for": [],
    "differentiators": [],
    "best_suited_for": [],
    "not_ideal_for": [],
    "competitive_positioning": ""
  },
  "communication_guidance": {
    "preferred_terms": [{ "term": "", "meaning": "" }],
    "avoided_terms": [{ "term": "", "reason": "" }],
    "required_qualifications": [{ "claim": "", "preferred_wording": "" }],
    "tone": [],
    "custom_guidance": ""
  },
  "connected_content": [{
    "role": "brand_description",
    "ref_type": "entry|collection",
    "collection": "",
    "entry_id": "",
    "slug": "",
    "label": ""
  }],
  "claims": [{
    "id": "",
    "statement": "",
    "status": "supported|pending_review|unsupported|conflicting|expired",
    "evidence_ids": [],
    "related_content": [],
    "last_reviewed_at": "",
    "expires_at": "",
    "notes": ""
  }],
  "evidence": [{
    "id": "",
    "type": "cms_content|external_url|upload|certification|publication|other",
    "title": "",
    "url": "",
    "media_id": "",
    "publisher": "",
    "published_at": "",
    "reviewed_at": "",
    "verification_status": "user_verified|unverified",
    "claim_ids": [],
    "notes": ""
  }],
  "media_context": [{
    "media_id": "",
    "represents": { "type": "brand|product|person|place|campaign|concept|claim", "ref": "" },
    "description": "",
    "intended_use": "",
    "restrictions": ""
  }],
  "relationships": [],
  "publication_settings": {
    "enabled": false,
    "public_path": "/.well-known/brand-context.json",
    "include_json_ld": true
  }
}
```

---

## Admin UI (tabs)

### Tab 1 — Overview

- Completeness bar (weighted: identity, description, connected content, claims reviewed, evidence, publish enabled)
- Status cards: brand context, claims count, evidence, media context, publishing
- Last published + public URL link
- **Recommended next steps** with deep links
- Empty state: skippable **Get started** setup (5 short steps per product spec)

### Tab 2 — Context

Sections: Identity · Positioning · Communication guidance · Connected content (pickers linking CMS)

### Tab 3 — Claims & Evidence

Claim list + detail; evidence linker; statuses; external URL metadata fetch (title, domain, date) — **user confirms** verification

### Tab 4 — Media

Reuse Media library IDs; per-asset context (represents, use, restrictions)

### Tab 5 — Publish

- Draft vs published indicator
- Save draft · Publish changes
- Staleness warnings (linked CMS changed)
- Endpoint preview URLs
- Optional JSON-LD toggle for `project/` injection

### Context Preview (panel or Publish sub-section)

- Preset questions + free ask
- Answers from **published** snapshot only
- **Sources listed** for every answer

---

## Public delivery

| Channel | Implementation |
|---------|----------------|
| Well-known JSON | Route `/.well-known/brand-context.json` via `http_routes.php` + nginx; body from `published.json` export |
| Public API | `api/public/ai_presence.php` — `action=brand` full doc; cache headers, CORS via Domains |
| Project site | Copy or proxy to `project/.well-known/` on publish; link in `birkly-client` optional meta injection (M12) |
| JSON-LD | Generator maps identity to Schema.org `Organization` where fit; custom `@graph` for claims/evidence |

**Versioning:** `published_version` integer + `generated_at` in every export.

---

## API (admin)

`api/ai_presence.php` (authenticated):

| Action | Purpose |
|--------|---------|
| `get` | Draft + published + meta + completeness |
| `save_draft` | Partial or full draft update |
| `publish` | Validate → snapshot published → write well-known → bump version |
| `suggest` | Optional AI analysis of connected CMS (returns suggestions, does not save) |
| `preview_query` | Context preview against published snapshot |
| `fetch_url_meta` | External evidence URL metadata |
| `completeness` | Score + next steps |
| `staleness` | Linked content change detection |

---

## Milestones

| ID | Focus | Owner | Status |
|----|--------|-------|--------|
| **P46-M0** | Plan, gap register, matrix, handoff | product-planner | **this PR** |
| **P46-M1** | Schema + storage + `core/ai_presence.php` | cms-developer | pending |
| **P46-M2** | Nav + page shell + tabs + permissions | cms-developer | pending |
| **P46-M3** | Overview + completeness + next steps | frontend-developer | pending |
| **P46-M4** | First-time setup flow (skippable) | frontend-developer | pending |
| **P46-M5** | Context tab (identity, positioning, guidance) | frontend-developer | pending |
| **P46-M6** | Connected content pickers (entries, collections) | cms-developer | pending |
| **P46-M7** | Claims & Evidence tab + admin API | cms-developer | pending |
| **P46-M8** | Media context tab | frontend-developer | pending |
| **P46-M9** | Draft/publish pipeline + version snapshots | cms-developer | pending |
| **P46-M10** | Public well-known + `api/public/ai_presence.php` + JSON-LD export | cms-developer | pending |
| **P46-M11** | Staleness detection + Publish tab UX | frontend-developer | pending |
| **P46-M12** | Context Preview (sources visible) | cms-developer | pending |
| **P46-M13** | AI suggestions (approve-to-save) via `ai_connections` | cms-developer | pending |
| **P46-M14** | Birkly-Docs, i18n, tests, V-AI matrix, closure | lead-engineer | pending |

**Depends on:** P43 hosting URLs (public domain), P44 admin UX patterns (tabs-v2, toast, sticky save)  
**Parallel OK:** P46 does not block on P45 (complete).

---

## UX copy principles

- Say **“publish for AI systems and agents”** — not embeddings, RAG, vectors
- Say **“supported by these sources”** — not “objectively true”
- Say **“may be outdated”** — not silent staleness
- Disclaimer in Publish: does not guarantee every AI will consume the data

---

## Out of scope (P46)

- Vector database / embeddings index
- Autonomous web crawling of brand site
- Legal/compliance certification engine
- Replacing Settings → AI (connections/MCP) — complementary
- Marketplace plugin distribution of AI Presence
- Studio P26 · P42 marketing pack

---

## Success criteria

1. User completes setup without duplicating Product X — links to existing entry
2. Claim + 2 evidence sources → status Supported → appears in published JSON
3. `https://{public-site}/.well-known/brand-context.json` returns valid published doc
4. Context Preview answers “What is this brand known for?” with source list
5. CMS entry edit triggers staleness warning on Overview/Publish
6. AI suggestion requires Accept before appearing in draft
7. V-AI matrix green in CI

---

## Branch template

`cursor/p46-m[N]-<slug>-030a`
