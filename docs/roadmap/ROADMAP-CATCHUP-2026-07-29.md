# Roadmap catch-up (2026-07-29)

Inventory of work shipped on `main` that was **not fully reflected** in `ROADMAP.md` until this update. Use this when planning the next formal phase.

---

## Summary

| Phase | Planned? | Shipped | Roadmap status (now) |
|-------|----------|---------|----------------------|
| P43 Hosting URLs | Yes | Yes | Complete |
| P44 Admin UX | Yes | Yes | Complete |
| P45 Templating / stacks | Yes | Yes | Complete |
| P38 AI Project Gateway | Partial (AI docs only) | Yes | **Documented here** |
| P39 Content UX | PRs only (#258–268) | Yes | **Documented here** |
| P46 AI Presence | Yes | Yes (+ rebrand) | Complete → **Agent Guide** |
| P47 AI Presence Pro | No formal plan on `main` | Yes (#336+) | **Documented here** |
| Marketplace CMS UI wave | No | Yes (#402–423) | **Documented here** |
| Admin UX audit 2026-07 | Ad hoc plan | Yes (#314+) | Complete |

---

## Phase 38 — AI Project Gateway (unplanned closure)

**What:** MCP + admin chat tools to read/write `project/` site files safely.

| Artifact | Location |
|----------|----------|
| MCP project tools | `core/project_site_filesystem.php`, MCP resources |
| Docs | `docs/ai/BYOAI-ARCHITECTURE.md`, context pack P38 section |
| PR | #246 |

**Outcome:** AI can edit HTML in `project/` without duplicating CMS content logic.

---

## Phase 39 — Content & editor UX (executed without phase docs on `main`)

**What:** ContentEditor toolbar, collection overview, nested groups, entries table polish, AI chat context/streaming.

| PR | Focus |
|----|--------|
| #258 | Wave A — ContentEditor toolbar, entries hotfixes |
| #259 | M5–M10 — Collection overview, nested groups, theme |
| #260 | M11–M13 — AI chat context, streaming, quick actions |
| #261–268 | Entries table UX (sticky actions, list view, gaps) |

**Outcome:** Primary **Content** workflow is materially better; no `docs/reviews/P39-*` on `main` (acceptable — catch-up only).

---

## Phase 46 + 47 — Agent Guide (formerly “AI Presence”)

**User-facing name:** **Agent Guide** (nav + i18n). Internal code paths still use `ai_presence` in many filenames.

**What shipped beyond original P46 plan:**

| Addition | Source |
|----------|--------|
| Sources of truth tab + `agent_guide_sources.php` | #354 |
| Products & services tab | P47 |
| AI Assist sidebar (auto/advanced) | #337–349 |
| Cross-links + link health | #353 |
| CMS sync / staleness hints | Overview |
| Public path `/.well-known/agent-guide.json` | P46-M10 |
| Legacy `brand-context.json` route | compatibility |

| PR cluster | Examples |
|------------|----------|
| Core feature | #331, #334–335 |
| UX iterations | #337–349, #354, #356 |
| Pro layer | #336 |

**Tests:** `tests/ai_presence_test.php`, `tests/ai_presence_storage_test.php` — matrix `P46-verification-matrix.md` marked complete.

**Birkly-Docs gap (closed in catch-up PR):** `02-content/agent-guide.md`

---

## Marketplace integration wave (CMS admin, unphased)

Roughly **2026-07-29**, many PRs improved the **Marketplace** tab inside CMS (not Birkly-Marketplace repo alone):

- Offer cards (cover, type badges, stats, typography)
- Provider info on cards + modal
- Price filter, sort, display currency (EUR default)
- Multi-plan “From X/month” pricing
- Coming soon offers (no install)
- Installed plugin reviews modal
- Automation plugin path fixes on `BIRKLY_DATA`

**Suggestion:** If more marketplace work is planned, formalize as **P48 Marketplace CMS client** or fold into marketplace roadmap in `Birkly-Marketplace`.

---

## Other notable merges (unphased)

| Area | PR / note |
|------|-----------|
| Settings Project tab | #253 (P38 overlap) |
| Project file ops | #255–256 |
| Avatars / BYOAI on user cards | #251–252 |
| Public form submission fix | #399 |
| AI playbooks expansion | #400–401, #366 |
| Admin UX audit tracks A–E | `docs/reviews/admin-ux-fix-plan-2026-07.md` |

---

## Still open / stale

| Item | Notes |
|------|--------|
| PR #289 | nginx marketing domain — consider merge or close |
| Draft plans | P28 beta launch, P26 Studio, P31/P32 dashboard, P42 template pack |
| PR #169 | Reference demo client fixes (draft) |

---

## Architectural note — vectors vs Agent Guide

**Decision (product):** Birkly core does **not** require a vector database.

| Need | Approach |
|------|----------|
| Official brand truth for AI/agents | **Agent Guide** — structured JSON, claims, evidence, sources |
| Admin help / MCP | Existing chat + tools over CMS API |
| Future support-bot RAG over huge corpora | Optional **plugin or separate product** — not core CMS |

---

## Next planning candidates

1. **P48** — Formal marketplace CMS client closure + docs  
2. **P42** — Marketing starter template pack  
3. **P26** — Studio creative suite  
4. **P28** — Beta launch automation  
5. **Agent Guide polish** — ongoing UX, docs, production smoke (not a full phase unless scope grows)

---

## How to keep roadmap honest

After any multi-PR wave without a phase plan:

1. Add a short section to this catch-up file **or** mark complete in `ROADMAP.md`
2. Update **Current state** date
3. List PR numbers for audit trail
