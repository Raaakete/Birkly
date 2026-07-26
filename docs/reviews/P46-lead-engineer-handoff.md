# Phase 46 — Lead-engineer handoff (AI Presence)

**Status:** Approved (2026-07-26) — **execute P46-M1→M14**  
**Human rule:** Execute autonomously. Notify human **once** at **P46-M14** closure.

**Master plan:** `docs/reviews/P46-ai-presence-plan.md`  
**Gap register:** `docs/reviews/P46-gap-register.md`  
**Matrix:** `docs/reviews/P46-verification-matrix.md`

**Baseline:** P43 hosting · P44 admin UX · P45 templating complete on `main`. No AI Presence feature yet.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 46 — AI Presence.

Human approved P46 (2026-07-26). Execute P46-M1→M14 autonomously in order.
Notify human ONCE at P46-M14 with V-AI matrix green.

GOAL: Thin layer on existing CMS — define brand context, claims, evidence, media
context, communication guidance; publish canonical machine-readable representation
on the PUBLIC site domain. NOT a chatbot builder. NOT duplicate Content/Media.

PRODUCT PROMISE: "Authoritative structured representation easier for AI systems
to discover and use" — NOT "every AI will know your brand."

DO NOT build: vector DB, embeddings index, autonomous crawlers, OpenAI-only stack.
DO NOT auto-publish AI suggestions — user must Accept.
DO NOT replace Settings → AI connections / MCP (complementary).

LOCKED (P46-D1–D14):
- Core nav: Dashboard · Content · Media · AI Presence · Marketplace · Settings
- Single page, tabs: Overview | Context | Claims & Evidence | Media | Publish
- Canonical internal JSON (ai_presence.v1) — exports are adapters
- Reference CMS entities by id/slug — minimize duplication
- Draft vs Published — explicit Publish
- Public: /.well-known/brand-context.json + GET api/public/ai_presence.php
- Well-known on PUBLIC site host (P43 hosting_urls)
- Context Preview = Q&A on PUBLISHED snapshot with visible sources
- Completeness score = meaningful checklist
- Staleness warnings when linked CMS changes after publish
- manage_settings for edit/publish (M2 may add ai_presence permission)

READ FIRST:
1. docs/reviews/P46-ai-presence-plan.md
2. docs/reviews/P46-gap-register.md
3. docs/reviews/P46-verification-matrix.md
4. admin/partials/sidebar.php — insert nav after Media
5. core/http_routes.php — well-known route pattern
6. core/hosting_urls.php — public site URL
7. api/public.php — CORS + public read patterns
8. api/ai_connections.php + api/ai_helpers.php — suggestions only
9. admin/js/tabs-v2.js, admin/js/ui-feedback.js — UX patterns from P44
10. docs/admin-design-system.md

═══ WAVE 1 — FOUNDATION (M1–M2) ═══

P46-M1:
- schema/ai_presence.schema.json
- core/ai_presence.php (load/save draft, validate, completeness weights)
- Storage: settings/ai_presence/ draft, published, meta, versions/
- api/ai_presence.php skeleton (get, save_draft)
- tests/ai_presence_storage_test.php

P46-M2:
- admin/templates/ai-presence.html + admin/js/ai-presence.js
- sidebar nav "AI Presence" (icon: sparkles or brain — use birkly_icon)
- admin/index.php page registration + permissions
- tabs-v2: Overview, Context, Claims & Evidence, Media, Publish
- Matrix: V-AI-05, V-AI-06, V-AI-04

═══ WAVE 2 — OVERVIEW & SETUP (M3–M4) ═══

P46-M3:
- Completeness API + Overview UI (bar, status cards, last published, public URL)
- Recommended next steps with deep links
- Matrix: V-AI-07

P46-M4:
- Empty state + skippable 5-step setup (brand, connect content, known-for,
  language guidance, review) — prefill from admin.json / branding / collections
- Matrix: V-AI-08

═══ WAVE 3 — CONTEXT (M5–M6) ═══

P46-M5:
- Context tab sections: Identity, Positioning, Communication guidance
- Sticky save / draft save via api/ai_presence.php

P46-M6:
- Connected content pickers: entry + collection refs
- Reuse entries list / collection APIs; store refs only
- Matrix: V-AI-09

═══ WAVE 4 — CLAIMS, MEDIA (M7–M8) ═══

P46-M7:
- Claims & Evidence CRUD in draft
- Statuses: supported, pending_review, unsupported, conflicting, expired
- fetch_url_meta for external evidence; user verification toggle
- Matrix: V-AI-10, V-AI-11

P46-M8:
- Media context tab — media picker, represents, intended_use, restrictions
- Matrix: V-AI-12

═══ WAVE 5 — PUBLISH & PUBLIC (M9–M11) ═══

P46-M9:
- publish action: validate → published.json → meta version++
- Optional versions/ history cap 50
- Matrix: V-AI-02, V-AI-03, V-AI-15

P46-M10:
- Export generator: canonical JSON + JSON-LD fragment
- Route /.well-known/brand-context.json (http_routes + nginx)
- api/public/ai_presence.php read-only, cache headers, CORS
- project/.well-known/ write or proxy on publish when mode=project
- Matrix: V-AI-13–17

P46-M11:
- Staleness: compare linked entry updated_at vs last_published_at
- Publish tab: draft/published UI, warnings, endpoint display
- Matrix: V-AI-18

═══ WAVE 6 — PREVIEW & AI (M12–M13) ═══

P46-M12:
- Context Preview panel: preset questions + ask
- Retrieval from published snapshot only; list sources
- Matrix: V-AI-19, V-AI-20

P46-M13:
- suggest action: analyze connected CMS via ai_connections (optional)
- Accept / Edit / Reject UI — never silent save
- Matrix: V-AI-21–23

═══ WAVE 7 — CLOSURE (M14) ═══

P46-M14:
- Birkly-Docs: 02-content/ai-presence.md + 04-integration/ai-presence-public-api.md
- lang/en.json + de.json keys
- tests/ai_presence_test.php full suite
- ci-check green
- Human notify once
- Matrix: V-AI-24–27

DELEGATION:
- cms-developer: M1, M6–M7, M9–M10, M12–M13, tests
- frontend-developer: M2–M5, M8, M11
- docs-writer: M14 Birkly-Docs
- qa-verifier: V-AI matrix
- design-reviewer: optional sign-off on AI Presence UI at M14
```

---

## Milestone checklist

| M | Done when |
|---|-----------|
| M1 | Schema + storage + core helpers + unit tests |
| M2 | Nav + page + tabs shell |
| M3 | Overview + completeness |
| M4 | Skippable setup |
| M5 | Context sections |
| M6 | Connected content refs |
| M7 | Claims + evidence |
| M8 | Media context |
| M9 | Publish pipeline |
| M10 | Public well-known + API |
| M11 | Staleness UX |
| M12 | Context preview |
| M13 | AI suggestions approve flow |
| M14 | Docs, i18n, closure |

---

## Implementation notes

| Topic | Guidance |
|-------|----------|
| Nav order | Insert after Media `<li>` in sidebar.php before plugin injection |
| Save model | Per-tab or global "Save draft" on AI Presence page — match P44 sticky save |
| Evidence URL fetch | Server-side HEAD/GET with timeout; no SSRF to internal IPs — reuse webhook URL validation patterns if exist |
| Preview engine | Simple: keyword + field match on published JSON first; optional AI summarize in M13 only with sources |
| Public file size | Cap published export (e.g. 2MB); truncate long CMS excerpts with `ref` links |
