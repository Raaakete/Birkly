# Phase 24 — Lead-engineer handoff (Studio visual excellence)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P23 complete; **production render bug** — raw template tags on `studio_view.php`  
**Human rule:** Execute **P24-M0→M20** autonomously after approval. Notify human **once** at **P24-M20** only.

**Master plan:** `docs/reviews/P24-studio-visual-excellence-plan.md`  
**Matrix:** `docs/reviews/P24-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 24 — Studio visual excellence.

Human approved P24. Execute P24-M0→M20 autonomously in one wave (scope ~4 phases).
Notify human ONCE at P24-M20 closure only.

READ FIRST:
1. docs/reviews/P24-lead-engineer-handoff.md
2. docs/reviews/P24-studio-visual-excellence-plan.md
3. docs/reviews/P24-studio-render-gap-register.md (create at M0)
4. plugins/studio/ — entire codebase
5. reference-projects/blog/website/index.html — CANONICAL template syntax
6. docs/reviews/P20-js-php-parity-matrix.md
7. api/studio_view.php · plugins/studio/includes/StudioRenderer.php · StudioCompiler.php

P0 PRODUCTION BUG (fix in M1–M3 before UI polish):
studio_view.php shows RAW template tags like {for each entry in 'about'} {title} {end}
Root cause: StudioCompiler emits {end} but canonical syntax is {endfor} / {endget};
StudioRenderer uses admin TemplateParser which does not match birkly-client.js syntax.

MISSION:
Build Studio into a FULL ADVANCED VISUAL EDITOR with professional UI/UX:
- FIX render pipeline (compiler + StudioTemplateEngine + SSR tests)
- Visual layout tools (grid/flex resize, box model, align)
- CSS control (rules panel, class manager, stylesheet tabs)
- Binding wizard with field picker + live client preview pane
- Navigation builder, page settings
- UI redesign (design-reviewer sign-off)

HUMAN REQUIREMENTS (carry forward from P23):
- Visual view + code view + split view
- File system + file tabs
- Open project ↗ in new browser tab — MUST show RENDERED content, not raw tags

LOCKED: P24-D1–D12 in master plan

CRITICAL PATH:
P24-M1 Fix StudioCompiler canonical syntax
P24-M2 StudioTemplateEngine.php (plugin, not admin TemplateParser)
P24-M3 Integration tests — curl studio_view must NOT contain "{for each"
THEN: M4–M20 UI, layout, CSS, bindings, nav, QA

MILESTONES M0–M20 — see master plan

Branches: cursor/p24-m[N]-<slug>-030a
CI: ./scripts/ci-check.sh && php tests/run.php

DELEGATION:
cms-developer:     M0–M3 (render), M13, M15–M18, M19 smoke
frontend-developer:  M4–M12, M14, M16 UI
design-reviewer:     M4–M6, M8–M10 (mandatory before M12)
docs-writer:         M20
qa-verifier:         M19–M20

CLOSURE:
[ ] studio_view + /site/ render real collection data — zero raw template tags
[ ] Open project ↗ on production scenario passes
[ ] Layout + CSS tools usable without writing code
[ ] P23 IDE stories still pass
[ ] performance_budget_test.php green
[ ] V-STU-03 evidence
[ ] Single digest notification
```

---

## Execution order

1. **M0** — Gap register + UX spec + fixture project  
2. **M1 → M2 → M3** — **Render pipeline (P0)**  
3. **M4 → M5 → M6** — UI shell redesign  
4. **M7 → M8 → M9** — Layout tools  
5. **M10 → M11 → M12** — CSS studio  
6. **M13 → M14** — Bindings + live preview  
7. **M15 → M16 → M17** — Nav + page settings + open project  
8. **M18 → M19 → M20** — Blocks polish, smoke, closure  

---

## Human approval checklist

- [ ] P24-D1–D12 accepted
- [ ] M20 single-wave execution approved
- [ ] P0 render fix prioritized before UI (M1–M3)
