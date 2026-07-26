# Phase 45 — Lead-engineer handoff (templating & multi-stack)

**Status:** Proposed (2026-07-26) — **awaiting human approval before M1**  
**Human rule:** After approval, execute P45-M1→M12 autonomously. Notify human **once** at **P45-M12** closure.

**Master plan:** `docs/reviews/P45-templating-and-stacks-plan.md`  
**Gap register:** `docs/reviews/P45-gap-register.md`  
**Matrix:** `docs/reviews/P45-verification-matrix.md`

**Baseline:** P43 + P44 complete on `main`. Templating engine works for plain HTML but web components and AI docs mislead users.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 45 — Templating, web components, and multi-stack integration.

Human approved P45 (date TBD). Execute P45-M1→M12 autonomously in order.
Notify human ONCE at P45-M12 with V-TPL matrix green.

GOAL: Real out-of-the-box integration for HTML, web components, SPAs, and SSR —
not workarounds. One-line <birkly-region> for shared CMS blocks. Truth in AI/docs.

DO NOT rewrite admin PHP template parser (eval removal is separate).
DO NOT add npm build requirement for project/ sites.
DO NOT implement shadow-DOM template scanning — document API path instead.

LOCKED DECISIONS (P45-D1–D12):
- <birkly-region collection entry list ordered> shipped in client
- processTemplates({ root }) + auto-process on region connect
- Light DOM = template language; Shadow DOM = fetch + Birkly.render()
- Fix phantom syntax in AI pack + get_project_info BEFORE engine milestones
- Birkly-Docs: choose-your-stack hub + 4 integration guides
- Birkly-Website reference migration at M12
- Template spec v1.0 at closure

READ FIRST:
1. docs/reviews/P45-templating-and-stacks-plan.md
2. docs/reviews/P45-gap-register.md
3. docs/reviews/P45-verification-matrix.md
4. birkly-client.js — discoverPageRegions, processTemplatesV3, init
5. birkly-client-modules/birkly-render.js (if split)
6. docs/ai/birkly-context-pack.md (wrong syntax)
7. core/project_site_filesystem.php — get_project_info templating cheat sheet
8. Birkly-Website/js/components.js (anti-pattern reference)
9. Birkly-Docs/04-integration/website-integration.md

═══ WAVE 1 — TRUTH (M1) ═══

P45-M1:
- Replace {entry}{endentry} with {from…get}{endget} everywhere in AI/MCP
- Remove or flag truncate/strip_tags until implemented
- Fix Birkly-Docs canonical-syntax data-birkly-region note (pending M4 impl)
- Matrix: V-TPL-14, V-TPL-15

═══ WAVE 2 — ENGINE (M2–M5) ═══

P45-M2:
- Birkly.processTemplates({ root?: Element, silent?: boolean })
- discoverPageRegions(doc, root?) — optional subtree
- Tests: template_regions_test.js extend

P45-M3:
- Define <birkly-region> custom element in birkly-client.js or birkly-region.js
- Modes: entry (collection+entry), list (collection+list), child template optional
- Default markup registry for site_elements/header, footer (optional attrs)

P45-M4:
- connectedCallback → processTemplates({ root: this })
- data-birkly-region on any element triggers same
- Event: birkly:region-processed
- Matrix: V-TPL-01–10

P45-M5:
- birkly-components.js OR enhance-only helpers: nav active state without innerHTML wipe
- Document anti-pattern in docs

═══ WAVE 3 — SPEC & TOOLING (M6–M7) ═══

P45-M6:
- docs/TEMPLATE_LANGUAGE_SPEC.md → v1.0
- site_elements prefetch, nested regions, ordering fields

P45-M7:
- scripts/validate-project-templates.php or Project tab validator
- Phantom syntax detection

═══ WAVE 4 — DOCS (M8–M10) ═══

Repo: Birkly-Docs

P45-M8:
- 04-integration/choose-your-stack.md
- 04-integration/web-components.md
- 03-templating/web-components.md
- Sync HTML exports

P45-M9:
- 04-integration/spa-react-vue.md
- Expand client-library.md with processTemplates + getEntryBySlug

P45-M10:
- 04-integration/ssr-and-build-time.md

═══ WAVE 5 — QA & REFERENCE (M11–M12) ═══

P45-M11:
- tests/birkly_region_test.js
- ci-check green
- V-TPL-22–24

P45-M12:
- Migrate Birkly-Website to <birkly-region> or enhance-only header
- Update reference-projects.md
- Closure + human notify
- V-TPL-20–21, V-TPL-26

DELEGATION:
- cms-developer: M2–M5, M7 engine parts
- frontend-developer: M5, Birkly-Website M12
- docs-writer: M1 docs, M6, M8–M10
- qa-verifier: M11 matrix
```

---

## Milestone checklist

| M | Done when |
|---|-----------|
| M1 | No phantom syntax in AI/MCP; grep clean |
| M2 | Scoped processTemplates + tests |
| M3 | `<birkly-region>` entry + list modes |
| M4 | Auto lifecycle + data-birkly-region |
| M5 | Enhance-only helpers documented |
| M6 | Spec v1.0 |
| M7 | Validator catches wrong syntax |
| M8 | Stack hub + web components docs |
| M9 | SPA guide |
| M10 | SSR guide |
| M11 | CI + node tests green |
| M12 | Birkly-Website + closure |

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Region default templates hard to customize | Allow child innerHTML override; project partials in M3 follow-up |
| Double fetch on full page + regions | Mark processed regions; bundle prefetch dedupe |
| Breaking existing sites with custom header JS | Enhance-only migration guide; Birkly-Website proves pattern |
